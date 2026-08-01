<?php
/**
 * Live AI Stats JSON endpoint
 * GET /api/ai_stats.php
 * Returns live counts from DB for index.html, impact.php, dashboards.
 * Public endpoint — no auth required (read-only aggregate data).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/ai_engine.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

$ai = adhaar_ai();

// ── Core counts ───────────────────────────────────────────────
$food_del   = (int)$conn->query("SELECT COALESCE(SUM(quantity),0) c FROM food_donations WHERE status='delivered'")->fetch_assoc()['c'];
$cloth_del  = (int)$conn->query("SELECT COALESCE(SUM(quantity),0) c FROM cloth_donations WHERE status='delivered'")->fetch_assoc()['c'];
$food_total = (int)$conn->query("SELECT COUNT(*) c FROM food_donations")->fetch_assoc()['c'];
$cloth_total= (int)$conn->query("SELECT COUNT(*) c FROM cloth_donations")->fetch_assoc()['c'];
$volunteers = (int)$conn->query("SELECT COUNT(*) c FROM register WHERE role='volunteer' AND verified=1")->fetch_assoc()['c'];
$donors     = (int)$conn->query("SELECT COUNT(*) c FROM register WHERE role='donor' AND verified=1")->fetch_assoc()['c'];
$sellers    = (int)$conn->query("SELECT COUNT(*) c FROM register WHERE role='seller' AND verified=1")->fetch_assoc()['c'];
$orders     = (int)$conn->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'];
$revenue    = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) r FROM orders WHERE order_status NOT IN ('cancelled','returned')")->fetch_assoc()['r'];
$pending    = (int)$conn->query("SELECT COUNT(*) c FROM food_donations WHERE status='pending'")->fetch_assoc()['c']
             +(int)$conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE status='pending'")->fetch_assoc()['c'];

// ── Areas covered (distinct city fragments from delivered donations) ──
$areas_q = $conn->query("SELECT DISTINCT TRIM(SUBSTRING_INDEX(pickup_address,',',-1)) AS city FROM food_donations WHERE status='delivered' UNION SELECT DISTINCT TRIM(SUBSTRING_INDEX(pickup_address,',',-1)) FROM cloth_donations WHERE status='delivered'");
$areas   = max(1, $areas_q->num_rows);

// ── AI Impact prediction ──────────────────────────────────────
$impact = $ai->predictImpact();

// ── Demand forecast ───────────────────────────────────────────
$forecast = $ai->demandForecast();

// ── Weekly chart data (last 8 weeks) ─────────────────────────
$weekly = [];
for ($i = 7; $i >= 0; $i--) {
    $from = date('Y-m-d', strtotime("-".($i+1)." weeks"));
    $to   = date('Y-m-d', strtotime("-$i weeks"));
    $f    = (int)$conn->query("SELECT COUNT(*) c FROM food_donations WHERE created_at BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
    $c    = (int)$conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE created_at BETWEEN '$from' AND '$to'")->fetch_assoc()['c'];
    $weekly[] = ['label'=>"W-$i",'food'=>$f,'cloth'=>$c,'total'=>$f+$c];
}

// ── Delivery rate ─────────────────────────────────────────────
$total_don = $food_total + $cloth_total;
$total_del = (int)$conn->query("SELECT COUNT(*) c FROM food_donations WHERE status='delivered'")->fetch_assoc()['c']
            +(int)$conn->query("SELECT COUNT(*) c FROM cloth_donations WHERE status='delivered'")->fetch_assoc()['c'];
$del_rate  = $total_don > 0 ? round($total_del/$total_don*100) : 0;

echo json_encode([
    // Hero stats (index.html count-up)
    'meals_distributed'  => $food_del,
    'clothing_delivered' => $cloth_del,
    'active_volunteers'  => $volunteers,
    'areas_covered'      => $areas,
    'donors'             => $donors,
    'sellers'            => $sellers,
    'total_donations'    => $total_don,
    'pending_donations'  => $pending,
    'delivery_rate'      => $del_rate,
    'shop_orders'        => $orders,
    'shop_revenue'       => $revenue,

    // AI impact predictions
    'people_fed'         => $impact['people_fed'],
    'co2_saved_kg'       => $impact['co2_saved_kg'],
    'water_saved_ltr'    => $impact['water_saved_ltr'],
    'economic_value_inr' => $impact['economic_value'],

    // Charts
    'weekly_data'        => $weekly,
    'trend'              => $forecast['trend'],
    'predicted_next_week'=> $forecast['predicted_next'],
    'avg_per_week'       => $forecast['avg_per_week'],

    'generated_at'       => date('c'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
