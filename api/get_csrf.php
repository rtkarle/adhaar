<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode(['token' => csrf_token()]);
