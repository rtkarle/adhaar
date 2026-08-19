<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';

if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
csrf_verify();
$email = $_SESSION['user_email'];

$upload_dir = __DIR__ . '/../uploads/';

/* ── Toggle active (used by both seller and admin) ── */
if (isset($_POST['toggle_id'])) {
    $tid    = (int)$_POST['toggle_id'];
    $active = (int)$_POST['active'];
    if ($_SESSION['role'] === 'seller') {
        $tq = $conn->prepare("UPDATE products SET is_active=? WHERE id=? AND seller_email=?");
        $tq->bind_param("iis", $active, $tid, $email);
        $back = '../seller/seller_dashboard.php?tab=products';
    } elseif (isset($_SESSION['admin_id'])) {
        $tq = $conn->prepare("UPDATE products SET is_active=? WHERE id=?");
        $tq->bind_param("ii", $active, $tid);
        $back = '../admin/admin_dashboard.php?tab=products';
    } else {
        http_response_code(403);
        exit('Unauthorized');
    }
    $tq->execute();
    header("Location: $back"); exit;
}

/* ── Only sellers can add new products ── */
if ($_SESSION['role'] !== 'seller') { header("Location: ../auth/login.php"); exit; }

/* ── Verify store exists ── */
$sq = $conn->prepare("SELECT id FROM seller_stores WHERE seller_email=?");
$sq->bind_param("s", $email); $sq->execute();
$store = $sq->get_result()->fetch_assoc();
if (!$store) {
    header("Location: ../seller/seller_dashboard.php?tab=store&err=nostore"); exit;
}

$name        = trim($_POST['name']        ?? '');
$description = trim($_POST['description'] ?? '');
$category    = trim($_POST['category']    ?? 'other');
$price       = (float)($_POST['price']    ?? 0);
$mrp         = (!empty($_POST['mrp']))         ? (float)$_POST['mrp']         : null;
$stock       = (int)($_POST['stock']      ?? 0);
$weight      = (!empty($_POST['weight_grams'])) ? (int)$_POST['weight_grams']  : null;

if (!$name || !$description || !$price) {
    header("Location: ../seller/seller_dashboard.php?tab=add_product&err=fields"); exit;
}

$img1 = secure_upload($_FILES['image1'] ?? [], $upload_dir, 'prod');
$img2 = secure_upload($_FILES['image2'] ?? [], $upload_dir, 'prod');
$img3 = secure_upload($_FILES['image3'] ?? [], $upload_dir, 'prod');

if (!$img1) {
    header("Location: ../seller/seller_dashboard.php?tab=add_product&err=noimage"); exit;
}

/*
 * bind_param type string breakdown (12 params):
 *   s  seller_email
 *   i  store_id
 *   s  name
 *   s  description
 *   s  category
 *   d  price          (DECIMAL → float)
 *   d  mrp            (DECIMAL → float, nullable — MySQLi handles NULL fine with d)
 *   i  stock
 *   s  image1
 *   s  image2         (nullable string)
 *   s  image3         (nullable string)
 *   i  weight_grams   (nullable int — pass null, MySQLi will store NULL)
 */
$stmt = $conn->prepare(
    "INSERT INTO products
     (seller_email, store_id, name, description, category,
      price, mrp, stock, image1, image2, image3, weight_grams)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$store_id = (int)$store['id'];
$stmt->bind_param("sisssddisssi",
    $email, $store_id, $name, $description, $category,
    $price, $mrp, $stock, $img1, $img2, $img3, $weight
);

if (!$stmt->execute()) {
    error_log("add_product error: " . $stmt->error);
    header("Location: ../seller/seller_dashboard.php?tab=add_product&err=db"); exit;
}

header("Location: ../seller/seller_dashboard.php?tab=products&success=" . urlencode('Product listed successfully!'));
exit;
