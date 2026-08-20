<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
$email = $_SESSION['user_email'];

$u = $conn->prepare("SELECT * FROM register WHERE email=? AND role='donor' AND verified=1");
$u->bind_param("s",$email); $u->execute();
$user = $u->get_result()->fetch_assoc();
if (!$user) { header("Location: ../auth/login.php"); exit; }

/* ── safe count helper ── */
function sc($conn,$sql,$p=null,$t=null){
  try{
    if($p){$s=$conn->prepare($sql);$s->bind_param($t,...$p);$s->execute();$r=$s->get_result();$row=$r->fetch_assoc();return(int)($row['c']??0);}
    $r=$conn->query($sql);return($r?(int)$r->fetch_assoc()['c']:0);
  }catch(Throwable $e){return 0;}
}

$food  = sc($conn,"SELECT COUNT(*) c FROM food_donations  WHERE donor_email=?",[$email],"s");
$cloth = sc($conn,"SELECT COUNT(*) c FROM cloth_donations WHERE donor_email=?",[$email],"s");
$total = $food + $cloth;
$goal  = 20;
$percent = min(100, ($total / max(1,$goal)) * 100);

/* ── recent donations with donation_id ── */
$recent = [];
try {
  $rf=$conn->prepare(
    "SELECT COALESCE(donation_id,CONCAT('DON-FOOD-',LPAD(id,6,'0'))) AS don_id,
            'Food' AS type,id,quantity,pickup_address,status,created_at,
            pickup_date,pickup_time,volunteer_email,priority
     FROM food_donations WHERE donor_email=? ORDER BY created_at DESC LIMIT 10");
  $rf->bind_param("s",$email);$rf->execute();$recent_food=$rf->get_result()->fetch_all(MYSQLI_ASSOC);
} catch(Throwable $e){$recent_food=[];}
try {
  $rc=$conn->prepare(
    "SELECT COALESCE(donation_id,CONCAT('DON-CLO-',LPAD(id,6,'0'))) AS don_id,
            'Clothes' AS type,id,quantity,pickup_address,status,created_at,
            pickup_date,pickup_time,volunteer_email,NULL AS priority
     FROM cloth_donations WHERE donor_email=? ORDER BY created_at DESC LIMIT 10");
  $rc->bind_param("s",$email);$rc->execute();$recent_cloth=$rc->get_result()->fetch_all(MYSQLI_ASSOC);
} catch(Throwable $e){$recent_cloth=[];}
$recent = array_merge($recent_food,$recent_cloth);
usort($recent,fn($a,$b)=>strtotime($b['created_at'])-strtotime($a['created_at']));
$recent = array_slice($recent,0,10);

/* ── active donations for timeline ── */
$active = array_filter($recent, fn($r)=>!in_array($r['status'],['delivered','rejected']));

/* ── counters ── */
$cart_count   = sc($conn,"SELECT COUNT(*) c FROM cart WHERE user_email='".mysqli_real_escape_string($conn,$email)."'");
$orders_count = sc($conn,"SELECT COUNT(*) c FROM orders WHERE buyer_email='".mysqli_real_escape_string($conn,$email)."'");

/* ── shop products ── */
$featured = [];
try {
  $fq=$conn->query("SELECT p.*,s.store_name FROM products p JOIN seller_stores s ON s.seller_email=p.seller_email WHERE p.is_active=1 AND s.is_active=1 ORDER BY p.total_sold DESC,p.avg_rating DESC LIMIT 4");
  $featured = $fq ? $fq->fetch_all(MYSQLI_ASSOC) : [];
} catch(Throwable $e){}

/* ── AI ── */
$ai_suggestions=$ai_products=$ai_impact_data=[];
try {
  require_once __DIR__.'/../api/ai_engine.php';
  $ai=$conn ? adhaar_ai() : null;
  if($ai){
    $ai_suggestions = $ai->getDonorSuggestions($email) ?: [];
    $ai_products    = $ai->getProductRecommendations($email,0,4) ?: [];
    $ai_impact_data = $ai->predictImpact() ?: [];
  }
} catch(Throwable $e){}
$ai_people_fed   = $ai_impact_data['people_fed']     ?? ($total * 3);
$ai_co2          = $ai_impact_data['co2_saved_kg']   ?? ($total * 1.2);
$ai_eco_value    = $ai_impact_data['economic_value'] ?? ($total * 45);

$success = $_GET['success'] ?? '';
$don_id  = $_GET['don_id']  ?? '';

/* ── status helpers ── */
$STATUS_STEPS  = ['pending','accepted','scheduled','out_for_pickup','picked_up','delivered'];
$STATUS_LABELS = ['Submitted','Verified','Scheduled','Out for Pickup','Picked Up','Delivered'];
$STATUS_ICONS  = ['📝','✅','📅','🚚','📦','🎉'];
function stepIndex(string $s, array $steps): int {
  $i = array_search($s,$steps); return $i===false ? 0 : (int)$i;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donor Dashboard — SoulServe</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#102A43">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css">
<style>
/* ══ DONOR DASHBOARD EXTRA STYLES ══ */
:root{
  --d-teal:#006D77; --d-green:#2E8B57; --d-orange:#FF8A00;
  --d-pink:#F72585; --d-purple:#7B2CBF; --d-blue:#2563EB;
  --d-grad:linear-gradient(135deg,#006D77,#2E8B57);
  --d-hero:linear-gradient(135deg,#102A43 0%,#006D77 55%,#2E8B57 100%);
}
/* ── Welcome band ── */
.welcome-band{
  background:var(--d-hero);border-radius:24px;padding:28px 30px;
  color:#fff;margin-bottom:24px;position:relative;overflow:hidden;
  display:flex;justify-content:space-between;align-items:center;
  flex-wrap:wrap;gap:14px;
  box-shadow:0 16px 48px rgba(0,109,119,.25);
  animation:fadeUp .5s ease;
}
.welcome-band::before{
  content:'';position:absolute;right:-50px;top:-50px;width:200px;height:200px;
  border-radius:50%;background:rgba(255,255,255,.06);pointer-events:none;
}
.wb-title{font-size:20px;font-weight:800;margin-bottom:4px}
.wb-sub{font-size:13px;opacity:.75}
.wb-btns{display:flex;gap:10px;flex-wrap:wrap;position:relative;z-index:1}
.wb-btn{padding:9px 20px;border-radius:20px;font-size:13px;font-weight:700;cursor:pointer;
  text-decoration:none;border:none;transition:.2s}
.wb-btn.white{background:#fff;color:var(--d-teal)}
.wb-btn.white:hover{background:#e8fdf5;transform:translateY(-1px)}
.wb-btn.glass{background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.3)}
.wb-btn.glass:hover{background:rgba(255,255,255,.25)}

/* ── KPI cards ── */
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.kpi-card{
  background:#fff;border-radius:18px;padding:20px 18px;
  box-shadow:0 4px 20px rgba(16,42,67,.07);border:1px solid rgba(16,42,67,.06);
  transition:.3s;position:relative;overflow:hidden;cursor:default;
}
.kpi-card:hover{transform:translateY(-4px);box-shadow:0 12px 36px rgba(16,42,67,.12)}
.kpi-card::after{
  content:'';position:absolute;bottom:0;left:0;right:0;height:3px;
  border-radius:0 0 18px 18px;opacity:0;transition:.3s;
}
.kpi-card:hover::after{opacity:1}
.kpi-card.c1::after{background:var(--d-grad)}
.kpi-card.c2::after{background:linear-gradient(135deg,var(--d-orange),var(--d-pink))}
.kpi-card.c3::after{background:linear-gradient(135deg,var(--d-blue),var(--d-purple))}
.kpi-card.c4::after{background:linear-gradient(135deg,var(--d-green),var(--d-teal))}
.kpi-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;
  justify-content:center;font-size:20px;margin-bottom:12px}
.kpi-val{font-size:30px;font-weight:900;color:var(--navy);line-height:1;margin-bottom:4px}
.kpi-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}

/* ── AI panel ── */
.ai-panel{
  background:linear-gradient(135deg,#0a1f30,#0d3858 40%,#006d77 100%);
  border-radius:22px;padding:0;overflow:hidden;margin-bottom:24px;
  box-shadow:0 12px 40px rgba(0,109,119,.2);
}
.ai-header{
  padding:18px 24px;display:flex;align-items:center;gap:12px;
  border-bottom:1px solid rgba(255,255,255,.1);
}
.ai-header-icon{
  width:42px;height:42px;border-radius:12px;
  background:linear-gradient(135deg,#FF8A00,#F72585);
  display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;
}
.ai-header-text h3{font-size:16px;font-weight:800;color:#fff;margin-bottom:2px}
.ai-header-text p{font-size:12px;color:rgba(255,255,255,.55)}
.ai-live-dot{display:inline-block;width:8px;height:8px;border-radius:50%;
  background:#10b981;animation:livePulse 1.6s ease infinite;margin-right:5px}
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.4)}}
.ai-body{padding:18px 24px;display:flex;flex-direction:column;gap:10px}
.ai-sug{
  display:flex;align-items:flex-start;gap:12px;padding:13px 16px;
  border-radius:14px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.08);
  transition:.2s;
}
.ai-sug:hover{background:rgba(255,255,255,.12)}
.ai-sug-icon{font-size:20px;flex-shrink:0;margin-top:1px}
.ai-sug-text{font-size:13px;color:rgba(255,255,255,.82);line-height:1.65}
.ai-sug-text strong{color:#fff}
.ai-impact-strip{
  display:grid;grid-template-columns:repeat(3,1fr);
  border-top:1px solid rgba(255,255,255,.1);
}
.ai-imp{padding:16px;text-align:center;border-right:1px solid rgba(255,255,255,.1)}
.ai-imp:last-child{border-right:none}
.ai-imp-val{font-size:22px;font-weight:900;color:#fff;line-height:1}
.ai-imp-lbl{font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.5px;color:rgba(255,255,255,.5);margin-top:4px}

/* ── Section header ── */
.sec-head{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:16px;flex-wrap:wrap;gap:8px;
}
.sec-head h3{font-size:16px;font-weight:800;color:var(--navy);
  display:flex;align-items:center;gap:8px}
.sec-head a{font-size:12px;font-weight:700;color:var(--teal);text-decoration:none;
  padding:6px 14px;border-radius:20px;background:rgba(0,109,119,.08);transition:.2s}
.sec-head a:hover{background:rgba(0,109,119,.15)}

/* ── Progress timeline (donation tracking) ── */
.track-list{display:flex;flex-direction:column;gap:16px;margin-bottom:24px}
.track-card{
  background:#fff;border-radius:18px;padding:20px 22px;
  box-shadow:0 4px 20px rgba(16,42,67,.07);border:1px solid rgba(16,42,67,.06);
  border-left:4px solid var(--d-teal);
  animation:fadeUp .4s ease;
}
.track-card.rejected{border-left-color:#ef4444}
.track-card.delivered{border-left-color:var(--d-green)}
.track-top{display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:8px;margin-bottom:14px}
.track-don-id{font-size:13px;font-weight:800;color:var(--navy);
  background:rgba(0,109,119,.08);padding:4px 12px;border-radius:20px;
  font-family:'Inter',monospace;letter-spacing:.3px}
.track-type{font-size:12px;font-weight:700;padding:4px 12px;border-radius:20px}
.track-type.food{background:#fef3c7;color:#92400e}
.track-type.cloth{background:#dbeafe;color:#1e40af}
.track-status-badge{
  font-size:11px;font-weight:700;padding:5px 13px;border-radius:20px;
  text-transform:uppercase;letter-spacing:.4px;
}
.track-meta{font-size:12px;color:var(--muted);margin-bottom:14px;
  display:flex;gap:16px;flex-wrap:wrap}
/* Progress bar */
.prog-bar-wrap{margin-bottom:12px}
.prog-bar-track{height:6px;background:rgba(16,42,67,.08);border-radius:6px;overflow:hidden}
.prog-bar-fill{height:100%;background:var(--d-grad);border-radius:6px;
  transition:width 1.2s cubic-bezier(.22,1,.36,1)}
.prog-pct{font-size:11px;font-weight:700;color:var(--teal);text-align:right;margin-top:4px}
/* Steps */
.tl-steps{display:flex;align-items:flex-start;overflow-x:auto;
  padding-bottom:4px;-webkit-overflow-scrolling:touch;gap:0;margin-top:4px}
.tl-step{display:flex;flex-direction:column;align-items:center;
  flex:1;min-width:52px;text-align:center;gap:4px;position:relative}
.tl-dot{
  width:28px;height:28px;border-radius:50%;
  background:#e2ebe9;border:2.5px solid #e2ebe9;
  display:flex;align-items:center;justify-content:center;
  font-size:12px;transition:.4s;flex-shrink:0;z-index:1;
}
.tl-step.done .tl-dot{
  background:var(--d-grad);border-color:transparent;color:#fff;
  box-shadow:0 4px 12px rgba(0,109,119,.3);
}
.tl-step.active .tl-dot{
  background:#fff;border-color:var(--d-teal);color:var(--d-teal);
  box-shadow:0 0 0 4px rgba(0,109,119,.15);
  animation:stepPulse 1.8s ease infinite;
}
@keyframes stepPulse{
  0%,100%{box-shadow:0 0 0 4px rgba(0,109,119,.15)}
  50%{box-shadow:0 0 0 8px rgba(0,109,119,.06)}
}
.tl-label{font-size:9px;font-weight:600;color:var(--muted);line-height:1.3;
  max-width:52px;word-break:break-word}
.tl-step.done .tl-label{color:var(--d-teal);font-weight:700}
.tl-step.active .tl-label{color:var(--navy);font-weight:800}
.tl-line{flex:1;height:2px;background:#e2ebe9;margin-top:13px;
  transition:.4s;min-width:10px;align-self:flex-start}
.tl-line.done{background:var(--d-grad)}

/* ── Donations history table ── */
.don-table-wrap{background:#fff;border-radius:18px;overflow:hidden;
  box-shadow:0 4px 20px rgba(16,42,67,.07);border:1px solid rgba(16,42,67,.06);margin-bottom:24px}
.don-table-scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.don-table{width:100%;border-collapse:collapse;min-width:540px}
.don-table th{background:rgba(16,42,67,.03);padding:12px 16px;
  font-size:10px;font-weight:700;text-transform:uppercase;
  letter-spacing:.5px;color:var(--muted);border-bottom:1px solid rgba(16,42,67,.06);
  text-align:left;white-space:nowrap}
.don-table td{padding:13px 16px;font-size:13px;border-bottom:1px solid rgba(16,42,67,.04);
  vertical-align:middle}
.don-table tbody tr:last-child td{border-bottom:none}
.don-table tbody tr{transition:.15s}
.don-table tbody tr:hover td{background:rgba(0,109,119,.03)}
.don-id-badge{
  font-size:11px;font-weight:700;background:rgba(0,109,119,.08);
  color:var(--d-teal);padding:3px 10px;border-radius:20px;
  white-space:nowrap;font-family:'Inter',monospace;
}

/* ── Status pills ── */
.pill{display:inline-block;padding:4px 11px;border-radius:20px;
  font-size:10px;font-weight:700;text-transform:uppercase;white-space:nowrap;letter-spacing:.3px}
.pill.pending{background:#fef3c7;color:#92400e}
.pill.accepted{background:#dbeafe;color:#1e40af}
.pill.scheduled{background:#ede9fe;color:#5b21b6}
.pill.out_for_pickup{background:#fce7f3;color:#9d174d}
.pill.picked_up,.pill.delivered{background:#d1fae5;color:#065f46}
.pill.rejected{background:#fee2e2;color:#991b1b}

/* ── Shop preview ── */
.shop-strip{
  background:linear-gradient(135deg,#102A43,#006D77);
  border-radius:22px;padding:20px 24px;color:#fff;
  display:flex;align-items:center;justify-content:space-between;
  gap:12px;flex-wrap:wrap;margin-bottom:20px;
}
.shop-strip h3{font-size:18px;font-weight:800;margin-bottom:4px}
.shop-strip p{font-size:13px;opacity:.75}
.shop-strip-btn{
  padding:10px 20px;border-radius:20px;background:#fff;
  color:var(--d-teal);font-size:13px;font-weight:800;
  text-decoration:none;white-space:nowrap;transition:.2s;flex-shrink:0;
}
.shop-strip-btn:hover{background:#e8fdf5;transform:translateY(-1px)}
.pm-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.pm-card{
  background:#fff;border-radius:16px;overflow:hidden;cursor:pointer;
  box-shadow:0 4px 16px rgba(16,42,67,.07);border:1px solid rgba(16,42,67,.05);
  transition:.3s;
}
.pm-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(16,42,67,.12)}
.pm-img,.pm-img-ph{
  width:100%;height:140px;object-fit:cover;
  background:linear-gradient(135deg,#edf5f2,#eef2ff);
  display:flex;align-items:center;justify-content:center;font-size:36px;
}
.pm-body{padding:12px 14px 14px}
.pm-name{font-size:13px;font-weight:700;color:var(--navy);margin-bottom:3px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pm-store{font-size:11px;color:var(--muted);margin-bottom:5px}
.pm-price{font-size:15px;font-weight:900;color:var(--d-teal)}

/* ── How it works steps ── */
.how-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px}
.how-step{
  background:#fff;border-radius:16px;padding:20px 16px;text-align:center;
  box-shadow:0 4px 16px rgba(16,42,67,.06);border:1px solid rgba(16,42,67,.05);
  transition:.3s;
}
.how-step:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(16,42,67,.1)}
.how-step-num{
  width:32px;height:32px;border-radius:50%;background:var(--d-grad);
  color:#fff;font-size:13px;font-weight:900;display:flex;align-items:center;
  justify-content:center;margin:0 auto 10px;box-shadow:0 4px 12px rgba(0,109,119,.3);
}
.how-step-icon{font-size:26px;margin-bottom:8px}
.how-step h4{font-size:13px;font-weight:800;color:var(--navy);margin-bottom:5px}
.how-step p{font-size:12px;color:var(--muted);line-height:1.6}

/* ── Skeleton loader ── */
.skel{
  background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);
  background-size:200% 100%;animation:skel 1.4s infinite;border-radius:8px;
}
@keyframes skel{0%{background-position:200% 0}100%{background-position:-200% 0}}
.skel-line{height:14px;margin-bottom:8px}
.skel-card{height:80px;border-radius:16px}

/* ── Donate CTA card ── */
.donate-cta{
  background:linear-gradient(135deg,#FF8A00,#F72585);
  border-radius:22px;padding:24px 28px;color:#fff;margin-bottom:24px;
  display:flex;align-items:center;justify-content:space-between;
  flex-wrap:wrap;gap:14px;box-shadow:0 12px 36px rgba(247,37,133,.2);
}
.donate-cta h3{font-size:20px;font-weight:800;margin-bottom:5px}
.donate-cta p{font-size:13px;opacity:.82}
.donate-cta-btn{
  padding:12px 28px;border-radius:20px;background:#fff;
  color:#F72585;font-size:14px;font-weight:800;
  text-decoration:none;white-space:nowrap;transition:.2s;flex-shrink:0;
}
.donate-cta-btn:hover{background:#fff3f7;transform:translateY(-1px)}

/* ── Success banner ── */
.success-notice{
  background:linear-gradient(135deg,#d1fae5,#a7f3d0);
  border:1px solid #6ee7b7;border-radius:16px;padding:16px 20px;
  margin-bottom:20px;display:flex;align-items:flex-start;gap:14px;
  animation:fadeUp .4s ease;
}
.success-notice-icon{font-size:22px;flex-shrink:0;margin-top:1px}
.success-notice h4{font-size:14px;font-weight:800;color:#065f46;margin-bottom:3px}
.success-notice p{font-size:12px;color:#047857}
.success-notice-id{
  display:inline-block;background:#fff;border:1px solid #6ee7b7;
  color:#065f46;font-weight:800;font-family:'Inter',monospace;
  font-size:13px;padding:4px 12px;border-radius:20px;margin-top:6px;
}

/* ── Mobile topbar ── */
.mobile-topbar{background:#fff;border-bottom:1px solid rgba(16,42,67,.08)}
.m-logo img{height:30px;object-fit:contain;vertical-align:middle}

/* ── Responsive ── */
@media(max-width:900px){
  .kpi-row{grid-template-columns:repeat(2,1fr)}
  .pm-grid{grid-template-columns:repeat(2,1fr)}
  .how-grid{grid-template-columns:repeat(2,1fr)}
  .ai-impact-strip{grid-template-columns:1fr 1fr}
  .ai-imp:last-child{grid-column:1/-1;border-right:none;border-top:1px solid rgba(255,255,255,.1)}
}
@media(max-width:600px){
  .kpi-row{grid-template-columns:repeat(2,1fr);gap:10px}
  .pm-grid{grid-template-columns:repeat(2,1fr);gap:10px}
  .how-grid{grid-template-columns:1fr 1fr;gap:10px}
  .welcome-band{padding:20px;border-radius:18px}
  .wb-title{font-size:17px}
  .track-card{padding:16px}
  .tl-label{font-size:8px}
  .tl-dot{width:24px;height:24px;font-size:10px}
  .donate-cta{padding:20px}
  .ai-panel{border-radius:18px}
  .don-table-wrap{border-radius:14px}
  .page{padding:14px}
}
@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
</style>
</head>
<body>
<!-- ══ MOBILE TOPBAR ══ -->
<div class="mobile-topbar">
  <div style="max-width:100%;padding:0 16px;height:58px;display:flex;align-items:center;justify-content:space-between">
    <span class="m-logo">
      <img src="../assets/logo.png" alt="SoulServe" loading="eager">
    </span>
    <button class="hamburger" id="hamburger" aria-label="Menu" aria-expanded="false"
      style="display:flex;flex-direction:column;gap:5px;cursor:pointer;padding:8px;background:none;border:none">
      <span style="display:block;width:22px;height:2px;background:var(--navy);border-radius:2px;transition:.3s"></span>
      <span style="display:block;width:22px;height:2px;background:var(--navy);border-radius:2px;transition:.3s"></span>
      <span style="display:block;width:22px;height:2px;background:var(--navy);border-radius:2px;transition:.3s"></span>
    </button>
  </div>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app">
<!-- ══ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-mark">
      <img src="../assets/logo.png" alt="SoulServe"
        style="width:28px;height:28px;object-fit:contain;border-radius:6px;filter:brightness(0)invert(1)">
    </div>
    <div class="sidebar-logo-text">
      <strong>SoulServe</strong>
      <span>Donor Portal</span>
    </div>
  </div>

  <!-- Donor info chip -->
  <div style="margin:8px 10px 4px;padding:12px 14px;background:rgba(255,255,255,.06);
    border-radius:12px;border:1px solid rgba(255,255,255,.08)">
    <div style="width:36px;height:36px;border-radius:50%;background:var(--gradient-teal);
      display:flex;align-items:center;justify-content:center;color:#fff;
      font-size:15px;font-weight:800;margin-bottom:8px">
      <?=strtoupper(substr($user['name'],0,1))?>
    </div>
    <div style="font-size:13px;font-weight:700;color:#fff;white-space:nowrap;
      overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($user['name'])?></div>
    <div style="font-size:11px;color:rgba(255,255,255,.45);margin-top:1px">Donor</div>
  </div>

  <div class="nav-sec">Dashboard</div>
  <a href="donor_dashboard.php" class="nav-btn active">
    <span class="nav-icon">🏠</span> Dashboard
  </a>
  <a href="donate.php" class="nav-btn">
    <span class="nav-icon">🎁</span> New Donation
  </a>
  <a href="history.php" class="nav-btn">
    <span class="nav-icon">📋</span> Donation History
  </a>
  <a href="track.php" class="nav-btn">
    <span class="nav-icon">📍</span> Track Donations
  </a>

  <div class="nav-sec">Shop</div>
  <a href="../shop/shop.php" class="nav-btn">
    <span class="nav-icon">🛍️</span> Browse Shop
  </a>
  <a href="../shop/cart.php" class="nav-btn">
    <span class="nav-icon">🛒</span> My Cart
    <?php if($cart_count>0):?>
    <span class="nav-badge"><?=$cart_count?></span>
    <?php endif;?>
  </a>
  <a href="../shop/my_orders.php" class="nav-btn">
    <span class="nav-icon">📦</span> My Orders
    <?php if($orders_count>0):?>
    <span class="nav-badge green"><?=$orders_count?></span>
    <?php endif;?>
  </a>

  <div class="nav-sec">Account</div>
  <a href="edit_profile.php" class="nav-btn">
    <span class="nav-icon">👤</span> My Profile
  </a>
  <a href="../pages/impact.php" class="nav-btn">
    <span class="nav-icon">🌍</span> Impact Board
  </a>

  <div class="sidebar-footer">
    <a href="../auth/logout.php" class="logout-link">
      <span style="font-size:16px">⇦</span> Logout
    </a>
  </div>
</aside>

<!-- ══ MAIN ══ -->
<main class="main">
  <div class="page">

    <!-- Success notice after donation -->
    <?php if($success && $don_id): ?>
    <div class="success-notice">
      <span class="success-notice-icon"><?=$success==='food'?'🍱':'👕'?></span>
      <div>
        <h4>Donation submitted successfully!</h4>
        <p>Your <?=$success==='food'?'food':'clothing'?> donation has been received. We'll notify you once it's verified.</p>
        <span class="success-notice-id"><?=htmlspecialchars($don_id)?></span>
      </div>
    </div>
    <?php elseif($success): ?>
    <div class="success-notice">
      <span class="success-notice-icon"><?=$success==='food'?'🍱':'👕'?></span>
      <div>
        <h4>Donation submitted!</h4>
        <p>Your <?=$success==='food'?'food':'clothing'?> donation has been received and is pending review.</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ WELCOME BAND ══ -->
    <div class="welcome-band">
      <div style="position:relative;z-index:1">
        <div class="wb-title"><span id="greetTime">Hello</span>, <?=htmlspecialchars($user['name'])?> 👋</div>
        <div class="wb-sub">You've made <?=$total?> donation<?=$total!=1?'s':''?> so far. Keep going!</div>
      </div>
      <div class="wb-btns">
        <a href="donate.php" class="wb-btn white">🎁 Donate Now</a>
        <a href="../shop/shop.php" class="wb-btn glass">🛍️ Shop</a>
        <a href="track.php" class="wb-btn glass">📍 Track</a>
      </div>
    </div>

    <!-- ══ KPI CARDS ══ -->
    <div class="kpi-row">
      <div class="kpi-card c1">
        <div class="kpi-icon" style="background:rgba(0,109,119,.1)">🎁</div>
        <div class="kpi-val" data-count="<?=$total?>" data-suffix=""><?=$total?></div>
        <div class="kpi-label">Total Donations</div>
      </div>
      <div class="kpi-card c2">
        <div class="kpi-icon" style="background:rgba(255,138,0,.1)">🍱</div>
        <div class="kpi-val" data-count="<?=$food?>" data-suffix=""><?=$food?></div>
        <div class="kpi-label">Food Donations</div>
      </div>
      <div class="kpi-card c3">
        <div class="kpi-icon" style="background:rgba(37,99,235,.1)">👕</div>
        <div class="kpi-val" data-count="<?=$cloth?>" data-suffix=""><?=$cloth?></div>
        <div class="kpi-label">Clothing Donations</div>
      </div>
      <div class="kpi-card c4">
        <div class="kpi-icon" style="background:rgba(46,139,87,.1)">🌍</div>
        <div class="kpi-val" data-count="<?=(int)round($ai_people_fed)?>" data-suffix=""><?=(int)round($ai_people_fed)?></div>
        <div class="kpi-label">People Helped</div>
      </div>
    </div>

    <!-- ══ AI SMART PANEL ══ -->
    <div class="ai-panel">
      <div class="ai-header">
        <div class="ai-header-icon">🤖</div>
        <div class="ai-header-text">
          <h3>AI Smart Insights</h3>
          <p><span class="ai-live-dot"></span>Personalised analysis based on your history &amp; platform trends</p>
        </div>
      </div>
      <div class="ai-body">
        <?php if(!empty($ai_suggestions)): ?>
          <?php foreach(array_slice($ai_suggestions,0,3) as $sug): ?>
          <div class="ai-sug">
            <span class="ai-sug-icon"><?=htmlspecialchars($sug['icon']??'💡')?></span>
            <span class="ai-sug-text"><?=$sug['text']??''?></span>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="ai-sug">
            <span class="ai-sug-icon">💡</span>
            <span class="ai-sug-text">
              <strong>Keep donating!</strong> Your contributions are creating real impact in your community.
              <?php if($total===0): ?> Make your first donation today to unlock personalised AI insights.<?php endif; ?>
            </span>
          </div>
          <div class="ai-sug">
            <span class="ai-sug-icon">📊</span>
            <span class="ai-sug-text">
              <strong>Demand Alert:</strong> Food donations are most needed on weekends. Consider scheduling your next donation for Saturday or Sunday.
            </span>
          </div>
          <div class="ai-sug">
            <span class="ai-sug-icon">🎯</span>
            <span class="ai-sug-text">
              <strong>High Impact Areas:</strong> Donations with "High" priority are delivered 2x faster. Use priority tagging when food is very perishable.
            </span>
          </div>
        <?php endif; ?>
      </div>
      <div class="ai-impact-strip">
        <div class="ai-imp">
          <div class="ai-imp-val"><?=number_format($ai_people_fed)?></div>
          <div class="ai-imp-lbl">People Fed</div>
        </div>
        <div class="ai-imp">
          <div class="ai-imp-val"><?=number_format($ai_co2,1)?><small style="font-size:.7rem">kg</small></div>
          <div class="ai-imp-lbl">CO₂ Saved</div>
        </div>
        <div class="ai-imp">
          <div class="ai-imp-val">₹<?=number_format($ai_eco_value)?></div>
          <div class="ai-imp-lbl">Economic Value</div>
        </div>
      </div>
    </div>

    <!-- ══ ACTIVE DONATION TRACKING (Timeline) ══ -->
    <?php $active_arr = array_values($active); ?>
    <?php if(!empty($active_arr)): ?>
    <div class="sec-head">
      <h3>📍 Active Donations <span style="background:rgba(0,109,119,.1);color:var(--d-teal);font-size:11px;padding:2px 10px;border-radius:20px;font-weight:700"><?=count($active_arr)?> live</span></h3>
      <a href="track.php">View All →</a>
    </div>
    <div class="track-list">
      <?php foreach(array_slice($active_arr,0,3) as $d):
        $sidx = stepIndex($d['status'],$STATUS_STEPS);
        $pct  = round((($sidx+1)/count($STATUS_STEPS))*100);
        $isFood = $d['type']==='Food';
      ?>
      <div class="track-card <?=in_array($d['status'],['delivered','picked_up'])?'delivered':($d['status']==='rejected'?'rejected':'')?>">
        <div class="track-top">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span class="track-don-id"><?=htmlspecialchars($d['don_id']??('#'.$d['id']))?></span>
            <span class="track-type <?=$isFood?'food':'cloth'?>"><?=$isFood?'🍱 Food':'👕 Clothes'?></span>
          </div>
          <span class="track-status-badge pill <?=htmlspecialchars($d['status'])?>"><?=ucfirst(str_replace('_',' ',$d['status']))?></span>
        </div>
        <div class="track-meta">
          <span>📦 <?=htmlspecialchars($d['quantity']??'—')?></span>
          <?php if(!empty($d['priority'])): ?>
          <span>🔥 <?=ucfirst($d['priority'])?> priority</span>
          <?php endif; ?>
          <?php if(!empty($d['pickup_date'])): ?>
          <span>📅 Pickup: <?=htmlspecialchars($d['pickup_date'])?></span>
          <?php endif; ?>
          <span>🕐 <?=date('d M Y',strtotime($d['created_at']))?></span>
        </div>
        <!-- Progress bar -->
        <div class="prog-bar-wrap">
          <div class="prog-bar-track">
            <div class="prog-bar-fill" style="width:<?=$pct?>%"></div>
          </div>
          <div class="prog-pct"><?=$pct?>% complete</div>
        </div>
        <!-- Step timeline -->
        <div class="tl-steps">
          <?php foreach($STATUS_STEPS as $i=>$s):
            $done   = ($i <  $sidx);
            $active_step = ($i === $sidx);
          ?>
          <div class="tl-step <?=$done?'done':''?> <?=$active_step?'active':''?>">
            <div class="tl-dot"><?=$done?'✓':($active_step?$STATUS_ICONS[$i]:($i+1))?></div>
            <span class="tl-label"><?=$STATUS_LABELS[$i]?></span>
          </div>
          <?php if($i < count($STATUS_STEPS)-1): ?>
          <div class="tl-line <?=$done?'done':''?>"></div>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══ DONATE CTA (if no donations yet) ══ -->
    <?php if($total === 0): ?>
    <div class="donate-cta">
      <div>
        <h3>Make Your First Donation</h3>
        <p>Donate surplus food or clothing — we pick it up, you change a life.</p>
      </div>
      <a href="donate.php" class="donate-cta-btn">🎁 Donate Now →</a>
    </div>
    <?php endif; ?>

    <!-- ══ DONATION HISTORY TABLE ══ -->
    <?php if(!empty($recent)): ?>
    <div class="sec-head">
      <h3>📋 Recent Donations</h3>
      <a href="history.php">View All →</a>
    </div>
    <div class="don-table-wrap">
      <div class="don-table-scroll">
        <table class="don-table">
          <thead>
            <tr>
              <th>Donation ID</th>
              <th>Type</th>
              <th>Qty</th>
              <th>Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach(array_slice($recent,0,7) as $r): ?>
            <tr>
              <td><span class="don-id-badge"><?=htmlspecialchars($r['don_id']??('#'.$r['id']))?></span></td>
              <td><?=$r['type']==='Food'?'🍱 Food':'👕 Clothes'?></td>
              <td><?=htmlspecialchars($r['quantity']??'—')?></td>
              <td style="white-space:nowrap;color:var(--muted)"><?=date('d M Y',strtotime($r['created_at']))?></td>
              <td><span class="pill <?=htmlspecialchars($r['status'])?>"><?=ucfirst(str_replace('_',' ',$r['status']))?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- ══ SHOP STRIP + AI PRODUCTS ══ -->
    <div class="shop-strip">
      <div>
        <h3>🛍️ Support Rural Artisans</h3>
        <p>Every purchase empowers women, artisans &amp; rural entrepreneurs directly.</p>
      </div>
      <a href="../shop/shop.php" class="shop-strip-btn">Browse Shop →</a>
    </div>

    <?php if(!empty($ai_products) || !empty($featured)): ?>
    <div class="sec-head">
      <h3><?=!empty($ai_products)?'✨ AI Recommended':'⭐ Featured Products'?></h3>
      <a href="../shop/shop.php">See All →</a>
    </div>
    <div class="pm-grid">
      <?php $display_products = !empty($ai_products) ? $ai_products : $featured; ?>
      <?php foreach(array_slice($display_products,0,4) as $p):
        $img = !empty($p['image1']) ? image_url($p['image1']) : null;
      ?>
      <div class="pm-card" onclick="location.href='../shop/product.php?id=<?=(int)$p['id']?>'">
        <?php if($img): ?>
          <img data-src="<?=htmlspecialchars($img)?>" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=" class="pm-img" alt="" loading="lazy">
        <?php else: ?>
          <div class="pm-img-ph">🛍️</div>
        <?php endif; ?>
        <div class="pm-body">
          <div class="pm-name"><?=htmlspecialchars($p['name'])?></div>
          <div class="pm-store">🏪 <?=htmlspecialchars($p['store_name']??'SoulServe Shop')?></div>
          <div class="pm-price">₹<?=number_format((float)$p['price'],0)?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══ HOW IT WORKS ══ -->
    <div class="sec-head" style="margin-top:8px">
      <h3>⚙️ How Your Donation Works</h3>
    </div>
    <div class="how-grid">
      <div class="how-step">
        <div class="how-step-num">1</div>
        <div class="how-step-icon">📝</div>
        <h4>Submit</h4>
        <p>Fill the form with photo, quantity &amp; address.</p>
      </div>
      <div class="how-step">
        <div class="how-step-num">2</div>
        <div class="how-step-icon">✅</div>
        <h4>Verify</h4>
        <p>Admin reviews &amp; approves within hours.</p>
      </div>
      <div class="how-step">
        <div class="how-step-num">3</div>
        <div class="how-step-icon">🚚</div>
        <h4>Pickup</h4>
        <p>AI assigns nearest volunteer for collection.</p>
      </div>
      <div class="how-step">
        <div class="how-step-num">4</div>
        <div class="how-step-icon">🤝</div>
        <h4>Delivered</h4>
        <p>Delivered with dignity. Photo proof sent to you.</p>
      </div>
    </div>

  </div><!-- .page -->
</main>
</div><!-- .app -->

<div id="dashToast"></div>
<script src="../js/dashboard.js"></script>
<script defer src="../js/ai_chat.js"></script>
<script>
/* Lazy-load product images */
document.querySelectorAll('img[data-src]').forEach(img=>{
  const io=new IntersectionObserver(entries=>{
    entries.forEach(e=>{
      if(e.isIntersecting){
        img.src=img.dataset.src;
        img.removeAttribute('data-src');
        io.unobserve(img);
      }
    });
  },{rootMargin:'200px'});
  io.observe(img);
});
<?php if($success && $don_id): ?>
window.addEventListener('load',()=>{
  if(typeof showToast==='function')
    showToast('Donation <?=htmlspecialchars($don_id)?> submitted!','success',5000);
});
<?php endif; ?>
</script>
</body>
</html>
