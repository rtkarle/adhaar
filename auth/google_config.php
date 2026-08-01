<?php
/**
 * Google OAuth Config
 * Credentials loaded from environment variables — never hardcoded.
 * Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in Render dashboard.
 *
 * Local dev: set in config/config.php or directly below as fallback.
 */
require __DIR__ . '/../vendor/autoload.php';

$client = new Google\Client();

// Read from env vars (Render) — fallback to config values for local dev
$googleClientId     = getenv('GOOGLE_CLIENT_ID')     ?: '';
$googleClientSecret = getenv('GOOGLE_CLIENT_SECRET') ?: '';

$client->setClientId($googleClientId);
$client->setClientSecret($googleClientSecret);

// Redirect URI based on environment
if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] === 'localhost') {
    $redirect = "http://localhost/adhaar/auth/google_callback.php";
} else {
    $host     = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    $redirect = $host . "/auth/google_callback.php";
}

$client->setRedirectUri($redirect);
$client->addScope("email");
$client->addScope("profile");
?>
