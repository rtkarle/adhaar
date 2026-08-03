<?php
/**
 * Google OAuth Login â€” Adhaar SoulServe
 * Redirects user to Google login page
 */
session_start();
require_once __DIR__ . '/google_config.php';

// Store redirect URI in session for callback verification
$_SESSION['oauth_redirect'] = $redirect_uri;

$login_url = $client->createAuthUrl();
header('Location: ' . $login_url);
exit;
?>
