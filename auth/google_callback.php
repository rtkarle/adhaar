<?php
session_start();
require_once __DIR__ . '/google_config.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_GET['code'])) { header("Location: login.php"); exit; }

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
if (isset($token['error'])) die("Google Login Failed: " . $token['error']);

$client->setAccessToken($token['access_token']);
$google_oauth = new Google\Service\Oauth2($client);
$guser = $google_oauth->userinfo->get();

$email = $guser->email;
$name  = $guser->name;

$stmt = $conn->prepare("SELECT role FROM register WHERE email=?");
$stmt->bind_param("s", $email); $stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $_SESSION['user_email'] = $email;
    $_SESSION['role']       = $row['role'];
    switch ($row['role']) {
        case 'seller':    header("Location: ../seller/seller_dashboard.php");    exit;
        case 'volunteer': header("Location: ../volunteer/volunteer_dashboard.php"); exit;
        default:          header("Location: ../donor/donor_dashboard.php");      exit;
    }
}

$_SESSION['google_name']  = $name;
$_SESSION['google_email'] = $email;
header("Location: complete_user_profile.php");
exit;
