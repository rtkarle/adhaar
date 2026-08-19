<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
csrf_verify();

$donor_email = $_SESSION['user_email'];

$uploadDir = __DIR__ . '/../uploads/';
$dbPath = secure_upload($_FILES['image'] ?? [], $uploadDir, 'food');
// Image is required for food donations
if (!$dbPath) { header("Location: ../donor/donate.php?error=upload"); exit; }

$prepared_at    = trim($_POST['prepared_at'] ?? '');
$safe_hours     = (int)($_POST['safe_hours'] ?? 0);
$quantity       = (int)($_POST['quantity'] ?? 0);
$priority       = trim($_POST['priority'] ?? 'medium');
$pickup_address = trim($_POST['pickup_address'] ?? '');
$contact        = trim($_POST['contact'] ?? '');

if (!$prepared_at || !$safe_hours || !$quantity || !$pickup_address || !$contact) {
    header("Location: ../donor/donate.php?error=fields"); exit;
}

$stmt = $conn->prepare("INSERT INTO food_donations (donor_email,food_time,safe_hours,quantity,priority,pickup_address,contact,image,status,created_at) VALUES (?,?,?,?,?,?,?,?,'pending',NOW())");
$stmt->bind_param("ssiissss", $donor_email, $prepared_at, $safe_hours, $quantity, $priority, $pickup_address, $contact, $dbPath);
if (!$stmt->execute()) { die("DB Error: " . $stmt->error); }

// Notify donor — fetch their name first
require_once __DIR__ . '/../config/mail.php';
$nr = $conn->prepare("SELECT name FROM register WHERE email=?");
$nr->bind_param("s", $donor_email); $nr->execute();
$donor_name = $nr->get_result()->fetch_assoc()['name'] ?? 'Donor';
sendDonationReceived($donor_email, $donor_name, 'food', $quantity . ' units', $pickup_address);

header("Location: ../donor/donor_dashboard.php?success=food");
exit;
