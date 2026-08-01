<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

// Railway uses non-standard port — mysqli_connect supports port as 4th param via ini
// Use mysqli with explicit port for Railway compatibility
$conn = mysqli_init();
if (!$conn) {
    die("Service temporarily unavailable. Please try again later.");
}

// Connect with explicit port (Railway MySQL uses standard 3306 internally,
// but public proxy uses a different port set via DB_PORT env var)
$connected = mysqli_real_connect(
    $conn,
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    DB_PORT,
    null,
    MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT  // Required for Railway SSL
);

if (!$connected) {
    error_log("DB connection failed [" . DB_HOST . ":" . DB_PORT . "]: " . mysqli_connect_error());
    die("Service temporarily unavailable. Please try again later.");
}
mysqli_set_charset($conn, 'utf8mb4');

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}
function csrf_verify(): void {
    $submitted = trim($_POST['csrf_token'] ?? '');
    if (!$submitted || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        die("Invalid request. Please go back and try again.");
    }
}
