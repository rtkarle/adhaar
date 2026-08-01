<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (!isset($_SESSION['user_email']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../auth/login.php"); exit;
}
csrf_verify();
$email = $_SESSION['user_email'];

$upload_dir = __DIR__ . '/../uploads/';

function uploadImg($file, $upload_dir, $prefix='store') {
    return secure_upload($file, $upload_dir, $prefix);
}

$bank_only = isset($_POST['bank_only']);

// Check if store exists
$sq = $conn->prepare("SELECT id FROM seller_stores WHERE seller_email=?");
$sq->bind_param("s",$email); $sq->execute();
$exists = $sq->get_result()->num_rows > 0;

if ($bank_only) {
    // Update bank details only
    if ($exists) {
        $uq = $conn->prepare("UPDATE seller_stores SET upi_id=?,bank_name=?,bank_account=?,bank_ifsc=?,bank_holder_name=? WHERE seller_email=?");
        $uq->bind_param("ssssss",
            $_POST['upi_id'],$_POST['bank_name'],$_POST['bank_account'],
            $_POST['bank_ifsc'],$_POST['bank_holder_name'],$email
        );
        $uq->execute();
    }
    header("Location: ../seller/seller_dashboard.php?tab=bank&success=".urlencode('Bank details updated!')); exit;
}

$store_name   = trim($_POST['store_name'] ?? '');
$tagline      = trim($_POST['store_tagline'] ?? '');
$category     = trim($_POST['store_category'] ?? 'other');
$description  = trim($_POST['store_description'] ?? '');
$whatsapp     = trim($_POST['whatsapp'] ?? '');
$village      = trim($_POST['village'] ?? '');
$district     = trim($_POST['district'] ?? '');
$state        = trim($_POST['state'] ?? '');

if (!$store_name || !$description) {
    header("Location: ../seller/seller_dashboard.php?tab=store&err=missing"); exit;
}

$logo   = uploadImg($_FILES['store_logo']   ?? [], $upload_dir, 'logo');
$banner = uploadImg($_FILES['store_banner'] ?? [], $upload_dir, 'banner');

if ($exists) {
    $sql = "UPDATE seller_stores SET store_name=?,store_tagline=?,store_category=?,store_description=?,whatsapp=?,village=?,district=?,state=?";
    $params = [$store_name,$tagline,$category,$description,$whatsapp,$village,$district,$state];
    $types  = "ssssssss";
    if ($logo)   { $sql .= ",store_logo=?";   $params[] = $logo;   $types .= "s"; }
    if ($banner) { $sql .= ",store_banner=?"; $params[] = $banner; $types .= "s"; }
    $sql .= " WHERE seller_email=?";
    $params[] = $email; $types .= "s";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
} else {
    $stmt = $conn->prepare("INSERT INTO seller_stores (seller_email,store_name,store_tagline,store_category,store_description,store_logo,store_banner,whatsapp,village,district,state) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssssssss",$email,$store_name,$tagline,$category,$description,$logo,$banner,$whatsapp,$village,$district,$state);
    $stmt->execute();
}
header("Location: ../seller/seller_dashboard.php?tab=store&success=".urlencode('Store saved successfully!')); exit;
