<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
if (!isset($_SESSION['user_email']) && !isset($_SESSION['admin_id'])) {
    header("Location: ../auth/login.php"); exit;
}
csrf_verify();

// ── Return request status (admin) ──────────────────────────
if (isset($_POST['return_id'])) {
    if (!isset($_SESSION['admin_id'])) die("Unauthorized");
    $rid    = (int)$_POST['return_id'];
    $status = in_array($_POST['return_status'],['approved','rejected','pickup_scheduled','item_received','refund_initiated','refund_completed'])
              ? $_POST['return_status'] : null;
    if ($status) {
        $conn->prepare("UPDATE return_requests SET status=?,updated_at=NOW() WHERE id=?")->execute([$status,$rid]);
    }
    header("Location: ../admin/admin_dashboard.php?tab=returns"); exit;
}

// ── Order status (seller) ──────────────────────────────────
if (isset($_POST['order_id'])) {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'] ?? '';
    $valid  = ['confirmed','processing','shipped','out_for_delivery','delivered','cancelled'];
    if (!in_array($status,$valid)) { header("Location: ../seller/seller_dashboard.php?tab=orders&err=invalid"); exit; }

    $seller = $_SESSION['user_email'] ?? null;
    if (isset($_SESSION['admin_id'])) {
        // Admin can update any order
        $stmt = $conn->prepare("UPDATE orders SET order_status=?,updated_at=NOW() WHERE id=?");
        $stmt->bind_param("si",$status,$oid);
    } else {
        // Seller can only update their own orders
        if (!$seller) { header("Location: ../auth/login.php"); exit; }
        $stmt = $conn->prepare("UPDATE orders SET order_status=?,updated_at=NOW() WHERE id=? AND seller_email=?");
        $stmt->bind_param("sis",$status,$oid,$seller);
    }

    if ($status === 'shipped' && !empty($_POST['tracking_id'])) {
        $tid = trim($_POST['tracking_id']);
        if (isset($_SESSION['admin_id'])) {
            $ts = $conn->prepare("UPDATE orders SET order_status=?,tracking_id=?,updated_at=NOW() WHERE id=?");
            $ts->bind_param("ssi",$status,$tid,$oid);
        } else {
            $ts = $conn->prepare("UPDATE orders SET order_status=?,tracking_id=?,updated_at=NOW() WHERE id=? AND seller_email=?");
            $ts->bind_param("ssis",$status,$tid,$oid,$seller);
        }
        $ts->execute();
    } else {
        $stmt->execute();
    }

    // ── Notify buyer of status change ────────────────────────
    $orow = $conn->query("SELECT buyer_email, order_number, shipping_name, tracking_id FROM orders WHERE id=$oid")->fetch_assoc();
    if ($orow && $orow['buyer_email']) {
        $tid_val = !empty($_POST['tracking_id']) ? trim($_POST['tracking_id']) : ($orow['tracking_id'] ?? '');
        sendOrderStatusUpdate($orow['buyer_email'], $orow['shipping_name'] ?? 'Customer', $orow['order_number'], $status, $tid_val);
    }

    $back = isset($_SESSION['admin_id']) ? '../admin/admin_dashboard.php?tab=orders' : '../seller/seller_dashboard.php?tab=orders&success=updated';
    header("Location: $back"); exit;
}

// ── Seller verification (admin) ────────────────────────────
if (isset($_POST['action']) && $_POST['action']==='verify_seller') {
    if (!isset($_SESSION['admin_id'])) die("Unauthorized");
    $semail = trim($_POST['email']);
    $conn->prepare("UPDATE seller_stores SET is_verified=1 WHERE seller_email=?")->execute([$semail]);

    // Notify seller
    $sr = $conn->query("SELECT r.name, ss.store_name FROM register r JOIN seller_stores ss ON ss.seller_email=r.email WHERE r.email='".mysqli_real_escape_string($conn,$semail)."'")->fetch_assoc();
    if ($sr) sendSellerVerified($semail, $sr['name'], $sr['store_name']);

    header("Location: ../admin/admin_dashboard.php?tab=sellers"); exit;
}

header("Location: ../index.html"); exit;
