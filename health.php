<?php
/**
 * /health — Render health check endpoint
 * Returns 200 JSON if PHP is running.
 * DB check is attempted but failure does NOT return 503
 * (prevents health check loop that causes 503 cascades).
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');

$status = ['php' => 'ok', 'time' => date('c')];

// Soft DB check — won't kill health if DB is slow
try {
    require_once __DIR__ . '/config/config.php';
    $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $status['db'] = $conn ? 'ok' : 'unavailable';
    if ($conn) mysqli_close($conn);
} catch (Throwable $e) {
    $status['db'] = 'unavailable';
}

http_response_code(200); // Always 200 — let Render see PHP is alive
echo json_encode($status, JSON_PRETTY_PRINT);
