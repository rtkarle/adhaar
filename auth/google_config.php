<?php
/**
 * Google OAuth Config — Adhaar SoulServe
 * Credentials from Render environment variables only.
 */
require __DIR__ . '/../vendor/autoload.php';

$GOOGLE_CLIENT_ID     = trim((string) getenv('GOOGLE_CLIENT_ID'));
$GOOGLE_CLIENT_SECRET = trim((string) getenv('GOOGLE_CLIENT_SECRET'));

// Fixed redirect URI — must match Google Console exactly
$redirect_uri = 'https://soulserves.onrender.com/auth/google_callback.php';

$client = new Google\Client();
$client->setClientId($GOOGLE_CLIENT_ID);
$client->setClientSecret($GOOGLE_CLIENT_SECRET);
$client->setRedirectUri($redirect_uri);
$client->addScope('email');
$client->addScope('profile');
$client->setAccessType('online');
$client->setPrompt('select_account');
