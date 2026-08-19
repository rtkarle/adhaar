<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") { header("Location: ../pages/contact.html"); exit; }
csrf_verify();

$name    = trim($_POST["name"]    ?? '');
$email   = trim($_POST["email"]   ?? '');
$message = trim($_POST["message"] ?? '');

if (!$name || !$email || !$message || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: ../pages/contact.html?error=1"); exit;
}

$stmt = $conn->prepare("INSERT INTO contact_messages (name,email,message) VALUES (?,?,?)");
$stmt->bind_param("sss", $name, $email, $message);
if (!$stmt->execute()) { header("Location: ../pages/contact.html?error=1"); exit; }
$stmt->close();

$body = '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f6f5f0;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f5f0;padding:40px 20px;"><tr><td align="center">
<table width="100%" style="max-width:520px;background:#fff;border-radius:24px;overflow:hidden;">
<tr><td style="background:linear-gradient(135deg,#7a7d3f,#9a8f5c);padding:32px 40px;text-align:center;">
<h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;">🌿 Adhaar – The SoulServe</h1>
<p style="margin:8px 0 0;color:rgba(255,255,255,.85);font-size:13px;">We received your message</p>
</td></tr>
<tr><td style="padding:36px 40px;">
<p style="margin:0 0 16px;font-size:16px;color:#2f2e26;font-weight:600;">Hello ' . htmlspecialchars($name) . ' 👋</p>
<p style="margin:0 0 20px;font-size:14px;color:#5a594d;line-height:1.7;">Thank you for reaching out. We will get back to you within <strong>24–48 hours</strong>.</p>
<div style="background:#f6f5f0;border-left:4px solid #7a7d3f;border-radius:8px;padding:16px 20px;margin-bottom:20px;">
<p style="margin:0;font-size:13px;color:#5a594d;font-style:italic;">"' . htmlspecialchars($message) . '"</p>
</div>
<p style="margin:0;font-size:13px;color:#5a594d;">Warm regards,<br><strong style="color:#7a7d3f;">Team Adhaar – The SoulServe</strong></p>
</td></tr>
<tr><td style="background:#f6f5f0;padding:20px 40px;border-top:1px solid #ede9df;text-align:center;">
<p style="margin:0;font-size:12px;color:#9a8f5c;">© 2026 Adhaar – The SoulServe | Kopargaon, Maharashtra</p>
</td></tr></table></td></tr></table></body></html>';

sendMail($email, "We received your message – Adhaar", $body);
sendAdminContactAlert($name, $email, $message);  // Notify admin
header("Location: ../pages/contact.html?sent=1");
exit;
