<?php
/**
 * GET /api/shop_preview.php
 * Returns top 4 real products from DB for homepage preview.
 * Public endpoint — no auth required.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=120');
header('Access-Control-Allow-Origin: *');

$products = [];
try {
    $q = $conn->query(
        "SELECT p.id, p.name, p.price, p.image1, p.category,
                s.store_name, s.village, s.state
         FROM products p
         JOIN seller_stores s ON s.seller_email = p.seller_email
         WHERE p.is_active = 1 AND s.is_active = 1
         ORDER BY p.total_sold DESC, p.avg_rating DESC, p.created_at DESC
         LIMIT 4"
    );
    if ($q) {
        while ($row = $q->fetch_assoc()) {
            $products[] = [
                'id'         => (int)$row['id'],
                'name'       => $row['name'],
                'price'      => (float)$row['price'],
                'image'      => !empty($row['image1']) ? image_url($row['image1']) : null,
                'category'   => $row['category'],
                'store_name' => $row['store_name'],
                'location'   => trim(($row['village'] ? $row['village'].', ' : '') . ($row['state'] ?? '')),
            ];
        }
    }
} catch (Throwable $e) {
    // DB unavailable — return empty, JS handles gracefully
}

echo json_encode([
    'ok'       => true,
    'products' => $products,
    'count'    => count($products),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
