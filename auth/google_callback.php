<?php
/**
 * Google OAuth Callback — Adhaar SoulServe
 */
session_start();

// Load Google config first (before DB — avoids DB error on OAuth issues)
try {
    require_once __DIR__ . '/google_config.php';
} catch (Exception $e) {
    error_log("Google config load failed: " . $e->getMessage());
    header("Location: login.php?error=config_failed");
    exit;
}

// ── 1. Check for error from Google ──────────────────────────
if (isset($_GET['error'])) {
    $err = htmlspecialchars($_GET['error']);
    error_log("Google OAuth error: $err");
    header("Location: login.php?error=" . urlencode($err));
    exit;
}

// ── 2. Must have code ────────────────────────────────────────
if (empty($_GET['code'])) {
    header("Location: login.php?error=no_code");
    exit;
}

// ── 3. Exchange code for token ───────────────────────────────
try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
} catch (Exception $e) {
    error_log("Google token fetch failed: " . $e->getMessage());
    header("Location: login.php?error=token_failed");
    exit;
}

if (isset($token['error'])) {
    error_log("Google token error: " . ($token['error'] ?? '') . " — " . ($token['error_description'] ?? ''));
    header("Location: login.php?error=" . urlencode($token['error'] ?? 'token_error'));
    exit;
}

if (empty($token['access_token'])) {
    error_log("Google: empty access_token: " . json_encode($token));
    header("Location: login.php?error=empty_token");
    exit;
}

// ── 4. Get user info ─────────────────────────────────────────
try {
    $client->setAccessToken($token['access_token']);
    $google_service = new Google\Service\Oauth2($client);
    $guser          = $google_service->userinfo->get();
} catch (Exception $e) {
    error_log("Google userinfo failed: " . $e->getMessage());
    header("Location: login.php?error=userinfo_failed");
    exit;
}

$email = trim($guser->email ?? '');
$name  = trim($guser->name  ?? 'User');

if (empty($email)) {
    header("Location: login.php?error=no_email");
    exit;
}

// ── 5. Load DB ───────────────────────────────────────────────
try {
    require_once __DIR__ . '/../config/db.php';
} catch (Exception $e) {
    error_log("DB load failed in callback: " . $e->getMessage());
    header("Location: login.php?error=db_failed");
    exit;
}

// ── 6. Check if user exists ──────────────────────────────────
$stmt = $conn->prepare("SELECT id, role, verified FROM register WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();

    // Auto-verify Google users
    if (!$row['verified']) {
        $upd = $conn->prepare("UPDATE register SET verified = 1 WHERE email = ?");
        $upd->bind_param("s", $email);
        $upd->execute();
    }

    $_SESSION['user_email'] = $email;
    $_SESSION['user_name']  = $name;
    $_SESSION['role']       = $row['role'];

    switch ($row['role']) {
        case 'seller':    header("Location: ../seller/seller_dashboard.php");       exit;
        case 'volunteer': header("Location: ../volunteer/volunteer_dashboard.php"); exit;
        default:          header("Location: ../donor/donor_dashboard.php");         exit;
    }
}

// ── 7. New user — select role ────────────────────────────────
$_SESSION['google_name']  = $name;
$_SESSION['google_email'] = $email;
header("Location: complete_user_profile.php");
exit;
