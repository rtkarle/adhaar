<?php
/**
 * api/process_payout.php
 * Admin marks a seller as paid and creates a settlement record.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../admin/admin_login.php"); exit;
}
csrf_verify();

$seller_email = trim($_POST['seller_email'] ?? '');
$amount       = (float)($_POST['amount']       ?? 0);
$method       = in_array($_POST['method']??'upi', ['upi','bank','cash']) ? $_POST['method'] : 'upi';
$reference    = trim($_POST['reference']  ?? '');
$notes        = trim($_POST['notes']      ?? '');
$admin_email  = $_SESSION['admin_email']  ?? 'admin';

if (!$seller_email || $amount <= 0) {
    header("Location: ../admin/admin_dashboard.php?tab=payout&err=invalid"); exit;
}

// ── Count eligible delivered orders ───────────────────────────
$orders_q = $conn->prepare(
    "SELECT COUNT(*) cnt, COALESCE(SUM(total_amount),0) total
     FROM orders
     WHERE seller_email=? AND order_status='delivered' AND payment_status='pending'"
);
$orders_q->bind_param("s", $seller_email);
$orders_q->execute();
$ord = $orders_q->get_result()->fetch_assoc();
$orders_count = (int)$ord['cnt'];

// ── Create settlement record ─────────────────────────────────
$settled_exist = $conn->query("SHOW TABLES LIKE 'settlements'")->num_rows > 0;
if ($settled_exist) {
    $ins = $conn->prepare(
        "INSERT INTO settlements
         (seller_email, amount, method, reference, period_from, period_to,
          orders_count, status, notes, settled_by, created_at, paid_at)
         VALUES (?,?,?,?,?,?,?,'paid',?,?,NOW(),NOW())"
    );
    $today = date('Y-m-d');
    $month_start = date('Y-m-01');
    $ins->bind_param(
        "sdssssiss s",
        $seller_email, $amount, $method, $reference,
        $month_start, $today, $orders_count,
        $notes, $admin_email
    );
    $ins->execute();
}

// ── Mark all pending orders as paid ─────────────────────────
$upd = $conn->prepare(
    "UPDATE orders SET payment_status='paid'
     WHERE seller_email=? AND order_status='delivered' AND payment_status='pending'"
);
$upd->bind_param("s", $seller_email);
$upd->execute();

// ── Notify seller ─────────────────────────────────────────────
$seller_name_q = $conn->prepare("SELECT name FROM register WHERE email=?");
$seller_name_q->bind_param("s", $seller_email);
$seller_name_q->execute();
$seller_row = $seller_name_q->get_result()->fetch_assoc();
$seller_name = $seller_row['name'] ?? 'Seller';

$body = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f6f5f0;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px"><tr><td align="center">
<table style="max-width:520px;width:100%;background:#fff;border-radius:20px;overflow:hidden">
<tr><td style="background:linear-gradient(135deg,#7a7d3f,#9a8f5c);padding:28px 36px;text-align:center">
<h2 style="margin:0;color:#fff;font-size:20px;font-weight:800">💰 Payment Received!</h2>
<p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:13px">Adhaar Shop Settlement</p>
</td></tr>
<tr><td style="padding:32px 36px">
<p style="font-size:14px;color:#5a594d;margin-bottom:16px">Hi '.$seller_name.',</p>
<p style="font-size:14px;color:#5a594d;line-height:1.7;margin-bottom:20px">Your payment has been processed for your Adhaar Shop earnings.</p>
<table style="width:100%;border-collapse:collapse;margin-bottom:20px">
<tr style="border-bottom:1px solid #ede9df"><td style="padding:10px 0;font-size:13px;color:#5a594d;font-weight:600">Amount Paid</td><td style="padding:10px 0;font-size:16px;font-weight:800;color:#7a7d3f;text-align:right">₹'.number_format($amount,2).'</td></tr>
<tr style="border-bottom:1px solid #ede9df"><td style="padding:10px 0;font-size:13px;color:#5a594d;font-weight:600">Method</td><td style="padding:10px 0;font-size:13px;text-align:right">'.strtoupper($method).'</td></tr>
<tr style="border-bottom:1px solid #ede9df"><td style="padding:10px 0;font-size:13px;color:#5a594d;font-weight:600">Reference</td><td style="padding:10px 0;font-size:13px;text-align:right">'.htmlspecialchars($reference ?: '—').'</td></tr>
<tr><td style="padding:10px 0;font-size:13px;color:#5a594d;font-weight:600">Orders Settled</td><td style="padding:10px 0;font-size:13px;text-align:right">'.$orders_count.' orders</td></tr>
</table>
<p style="font-size:12px;color:#9a8f5c">Thank you for empowering communities through Adhaar Shop.</p>
</td></tr>
<tr><td style="background:#f6f5f0;padding:18px 36px;text-align:center;border-top:1px solid #ede9df">
<p style="margin:0;font-size:11px;color:#9a8f5c">© 2026 Adhaar – The SoulServe</p>
</td></tr></table></td></tr></table></body></html>';

sendMail($seller_email, '💰 Payment Processed – ₹'.number_format($amount,2).' from Adhaar Shop', $body);

// ── Log to AI logs ────────────────────────────────────────────
require_once __DIR__ . '/../api/ai_engine.php';
adhaar_ai()->log('payout_processed', [
    'seller' => $seller_email,
    'amount' => $amount,
    'method' => $method,
    'orders' => $orders_count,
], ['status'=>'paid','reference'=>$reference], 100, $admin_email);

header("Location: ../admin/admin_dashboard.php?tab=payout&success=" . urlencode("Payment of ₹".number_format($amount,2)." processed for {$seller_name}."));
exit;
