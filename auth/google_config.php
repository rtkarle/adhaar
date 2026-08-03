<?php
/**
 * Google OAuth Config
 * Credentials loaded from environment variables â€” never hardcoded.
 */
require __DIR__ . '/../vendor/autoload.php';

$client = new Google\Client();

$client->setClientId(getenv('GOOGLE_CLIENT_ID') ?: '');
$client->setClientSecret(getenv('GOOGLE_CLIENT_SECRET') ?: '');

// Auto-detect redirect URI based on environment
if (php_sapi_name() === 'cli') {
    $redirect = 'http://localhost/adhaar/auth/google_callback.php';
} elseif (!empty($_SERVER['HTTP_HOST'])) {
    $scheme   = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : (!empty($_SERVER['HTTPS']) ? 'https' : 'http'));
    $redirect = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/auth/google_callback.php';
} else {
    $redirect = rtrim(getenv('APP_URL') ?: 'http://localhost/adhaar', '/') . '/auth/google_callback.php';
}

$client->setRedirectUri($redirect);
$client->addScope("email");
$client->addScope("profile");
?>
