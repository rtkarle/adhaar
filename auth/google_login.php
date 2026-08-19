<?php
/**
 * Google OAuth Login — Adhaar SoulServe
 * Redirects user to Google login page
 */
session_start();

// Include google config — provides $client and $redirect_uri
require_once __DIR__ . '/google_config.php';

// Generate login URL and redirect
$login_url = $client->createAuthUrl();
header('Location: ' . filter_var($login_url, FILTER_SANITIZE_URL));
exit;
