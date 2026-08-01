<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    csrf_verify();
    $name     = trim($_POST["name"]     ?? '');
    $email    = trim($_POST["email"]    ?? '');
    $phone    = trim($_POST["phone"]    ?? '');
    $city     = trim($_POST["city"]     ?? '');
    $interest = trim($_POST["interest"] ?? '');
    $message  = trim($_POST["message"]  ?? '');

    if (!$name || !$email || !$phone || !$city || !$interest || !$message) {
        header("Location: ../index.html?vol_error=missing#volunteer"); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../index.html?vol_error=email#volunteer"); exit;
    }

    $stmt = $conn->prepare("INSERT INTO volunteers (name,email,phone,city,interest,message) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $name, $email, $phone, $city, $interest, $message);

    if ($stmt->execute()) {
        sendVolunteerWelcome($email, $name, $city);
        header("Location: ../index.html?vol_success=1#volunteer");
    } else {
        header("Location: ../index.html?vol_error=db#volunteer");
    }
    exit;
}
header("Location: ../index.html#volunteer");
exit;
