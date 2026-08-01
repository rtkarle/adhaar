<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
csrf_verify();

$donor_email = $_SESSION['user_email'];

$uploadDir = __DIR__ . '/../uploads/';
$dbPath = secure_upload($_FILES['image'] ?? [], $uploadDir, 'cloth');
if (!$dbPath) { header("Location: ../donor/donate.php?error=upload"); exit; }

$purchase_time  = trim($_POST['purchase_time'] ?? '');
$quantity       = (int)($_POST['quantity'] ?? 0);
$cloth_type     = trim($_POST['cloth_type'] ?? '');
$condition_type = trim($_POST['condition_type'] ?? 'good');
$is_clean       = (int)(!empty($_POST['is_clean']));
$pickup_address = trim($_POST['pickup_address'] ?? '');
$contact        = trim($_POST['contact'] ?? '');

if (!$quantity || !$cloth_type || !$pickup_address || !$contact) {
    header("Location: ../donor/donate.php?error=fields"); exit;
}

$stmt = $conn->prepare("INSERT INTO cloth_donations (donor_email,purchase_time,quantity,cloth_type,condition_type,is_clean,pickup_address,contact,image,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,'pending',NOW())");
$stmt->bind_param("ssississs", $donor_email, $purchase_time, $quantity, $cloth_type, $condition_type, $is_clean, $pickup_address, $contact, $dbPath);
if (!$stmt->execute()) { die("DB Error: " . $stmt->error); }

// Notify donor — fetch their name first
require_once __DIR__ . '/../config/mail.php';
$nr = $conn->prepare("SELECT name FROM register WHERE email=?");
$nr->bind_param("s", $donor_email); $nr->execute();
$donor_name = $nr->get_result()->fetch_assoc()['name'] ?? 'Donor';
sendDonationReceived($donor_email, $donor_name, 'cloth', $quantity . ' pieces of ' . $cloth_type, $pickup_address);

header("Location: ../donor/donor_dashboard.php?success=cloth");
exit;
