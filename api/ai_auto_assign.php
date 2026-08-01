<?php
/**
 * AI Auto-Assign Volunteer
 * POST: donation_id, donation_type (food|cloth)
 * Returns JSON: { success, volunteer_email, volunteer_name, score, reason }
 * Also inserts into volunteer_tasks and updates donation status to 'scheduled'.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../api/ai_engine.php';
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
}

csrf_verify();

$donation_id   = (int)($_POST['donation_id']   ?? 0);
$donation_type = in_array($_POST['donation_type']??'',['food','cloth']) ? $_POST['donation_type'] : '';
$pickup_date   = trim($_POST['pickup_date']   ?? date('Y-m-d', strtotime('+1 day')));
$pickup_time   = trim($_POST['pickup_time']   ?? '10:00 AM – 12:00 PM');

if (!$donation_id || !$donation_type) {
    echo json_encode(['success'=>false,'message'=>'Missing donation_id or donation_type']); exit;
}

$ai = adhaar_ai();

// ── Food validity check before assigning ─────────────────────
if ($donation_type === 'food') {
    $validity = $ai->checkFoodValidity($donation_id);
    if (!$validity['valid']) {
        echo json_encode(['success'=>false,'message'=>'AI rejected: '.$validity['reason'],'ai_reason'=>$validity['reason']]);
        $ai->log('validity_check', ['id'=>$donation_id,'type'=>$donation_type], $validity, 95, $_SESSION['admin_id']??'admin');
        exit;
    }
}

// ── Score volunteers using enhanced distance + availability scoring ─
$scored = $ai->scoreVolunteersWithDistance($donation_id, $donation_type);
if (empty($scored)) {
    // fallback to basic scoring
    $scored = $ai->scoreVolunteers($donation_id, $donation_type);
}
if (empty($scored)) {
    echo json_encode(['success'=>false,'message'=>'No volunteers available']); exit;
}

$best = $scored[0]; // highest score — already sorted by scoreVolunteersWithDistance

// ── Assign: update donation + insert volunteer_task ──────────
$table = ($donation_type === 'food') ? 'food_donations' : 'cloth_donations';
$vol_email = $best['email'];

$up = $conn->prepare("UPDATE $table SET status='scheduled', pickup_date=?, pickup_time=?, volunteer_email=? WHERE id=?");
$up->bind_param("sssi", $pickup_date, $pickup_time, $vol_email, $donation_id);
$up->execute();

$ti = $conn->prepare("INSERT INTO volunteer_tasks (volunteer_email,donation_type,donation_id,assigned_by,task_status,notes,assigned_at) VALUES (?,?,?,?,'pending_acceptance',?,NOW())");
$note = "AI Auto-assigned (v2 distance scoring). Score: {$best['score']}/100. "
      . "Location: ".($best['city_match']?'City match ✓':'No city match')
      . ". Pincode ".($best['vol_pincode']?'provided':'not set')
      . ". Completed: {$best['completed']} tasks. Active: {$best['active_tasks']}. "
      . "Response rate: ".($best['response_rate']??100)."%.";
$breakdown_json = json_encode($best['breakdown'] ?? [], JSON_UNESCAPED_UNICODE);$admin_email = $_SESSION['admin_email'] ?? 'admin';
$ti->bind_param("ssiss", $vol_email, $donation_type, $donation_id, $admin_email, $note);$ti->execute();

// ── Notify donor ─────────────────────────────────────────────
$donor_row = $conn->query("SELECT donor_email FROM $table WHERE id=$donation_id")->fetch_assoc();
if ($donor_row) {
    sendStatusNotification($donor_row['donor_email'], $donation_type, 'scheduled', [
        'pickup_date'     => $pickup_date,
        'pickup_time'     => $pickup_time,
        'volunteer_email' => $vol_email,
    ]);
}

// ── Log AI decision ───────────────────────────────────────────
$ai->log('auto_assign', [
    'donation_id'  => $donation_id,
    'type'         => $donation_type,
    'top_5'        => array_slice($scored, 0, 5),
], [
    'assigned_to'  => $vol_email,
    'score'        => $best['score'],
    'pickup_date'  => $pickup_date,
], $best['score'], $admin_email);

echo json_encode([
    'success'         => true,
    'volunteer_email' => $vol_email,
    'volunteer_name'  => $best['name'],
    'score'           => $best['score'],
    'city_match'      => $best['city_match'],
    'completed_tasks' => $best['completed'],
    'active_tasks'    => $best['active_tasks'],
    'pickup_date'     => $pickup_date,
    'pickup_time'     => $pickup_time,
    'top_candidates'  => array_slice($scored, 0, 3),
    'message'         => "AI assigned {$best['name']} (score: {$best['score']}/100)",
]);
