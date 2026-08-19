<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
$email = $_SESSION['user_email'];

$u = $conn->prepare("SELECT name FROM register WHERE email=? AND role='donor' AND verified=1");
$u->bind_param("s",$email); $u->execute();
$user = $u->get_result()->fetch_assoc();
if (!$user) { header("Location: ../auth/login.php"); exit; }

function countDonations($conn,$table,$email){$q=$conn->prepare("SELECT COUNT(*) c FROM $table WHERE donor_email=?");$q->bind_param("s",$email);$q->execute();return(int)$q->get_result()->fetch_assoc()['c'];}
$food  = countDonations($conn,"food_donations",$email);
$cloth = countDonations($conn,"cloth_donations",$email);
$total = $food+$cloth; $goal=20;
$percent = min(100,($total/max(1,$goal))*100);

$rf=$conn->prepare("SELECT 'Food' type,quantity,pickup_address,status,created_at FROM food_donations WHERE donor_email=? ORDER BY created_at DESC LIMIT 5");
$rf->bind_param("s",$email);$rf->execute();$recentFood=$rf->get_result()->fetch_all(MYSQLI_ASSOC);
$rc=$conn->prepare("SELECT 'Clothes' type,quantity,pickup_address,status,created_at FROM cloth_donations WHERE donor_email=? ORDER BY created_at DESC LIMIT 5");
$rc->bind_param("s",$email);$rc->execute();$recentCloth=$rc->get_result()->fetch_all(MYSQLI_ASSOC);
$recent=array_merge($recentFood,$recentCloth);
usort($recent,fn($a,$b)=>strtotime($b['created_at'])-strtotime($a['created_at']));
$recent=array_slice($recent,0,5);

$success=$_GET['success']??'';
$cart_count=(int)$conn->query("SELECT COUNT(*) c FROM cart WHERE user_email='".mysqli_real_escape_string($conn,$email)."'")->fetch_assoc()['c'];
$orders_count=(int)$conn->query("SELECT COUNT(*) c FROM orders WHERE buyer_email='".mysqli_real_escape_string($conn,$email)."'")->fetch_assoc()['c'];
$featured=$conn->query("SELECT p.*, s.store_name FROM products p JOIN seller_stores s ON s.seller_email=p.seller_email WHERE p.is_active=1 AND s.is_active=1 ORDER BY p.total_sold DESC, p.avg_rating DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);

// ── AI Suggestions (personalised) ────────────────────────────
require_once __DIR__ . '/../api/ai_engine.php';
$ai_suggestions = adhaar_ai()->getDonorSuggestions($email);
$ai_impact      = adhaar_ai()->predictImpact();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard | Adhaar – The SoulServe</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css">
<style>
/* Donor-only extra styles */
.greeting-bar{background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:var(--radius);padding:20px 26px;color:#fff;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.greeting-bar h3{font-size:18px;font-weight:800}
.greeting-bar p{font-size:13px;opacity:.88;margin-top:2px}
.greeting-actions{display:flex;gap:10px;flex-wrap:wrap}
.g-btn{padding:9px 18px;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;transition:.25s;border:none;cursor:pointer}
.g-btn.white{background:#fff;color:var(--accent)}
.g-btn.white:hover{background:#f0f0e0}
.g-btn.outline{background:rgba(255,255,255,.18);color:#fff;border:1.5px solid rgba(255,255,255,.4)}
.g-btn.outline:hover{background:rgba(255,255,255,.28)}
/* AI Panel */
.ai-panel{background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:28px;overflow:hidden}
.ai-panel-header{background:linear-gradient(135deg,#1e1d18,#2f2e26);padding:16px 22px;display:flex;align-items:center;gap:10px}
.ai-panel-header h3{font-size:15px;font-weight:800;color:#fff;margin:0}
.ai-panel-header span{font-size:11px;color:rgba(255,255,255,.55);font-weight:500}
.ai-brain{font-size:1.4rem}
.ai-suggestions{padding:16px 20px;display:flex;flex-direction:column;gap:10px}
.ai-suggestion{display:flex;align-items:flex-start;gap:12px;padding:13px 16px;border-radius:12px;background:#f8f7f2;border:1px solid #ede9df;transition:.2s}
.ai-suggestion:hover{background:#f0efe8;border-color:var(--accent2)}
.ai-sug-icon{font-size:1.2rem;flex-shrink:0;margin-top:1px}
.ai-sug-text{font-size:13px;color:var(--muted);line-height:1.6}
.ai-sug-text strong{color:var(--text)}
.ai-impact-strip{display:grid;grid-template-columns:repeat(3,1fr);gap:0;border-top:1px solid #ede9df}
.ai-imp-item{padding:14px 16px;text-align:center;border-right:1px solid #ede9df}
.ai-imp-item:last-child{border-right:none}
.ai-imp-val{font-size:20px;font-weight:900;color:var(--accent);line-height:1}
.ai-imp-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-top:3px}
@media(max-width:600px){.ai-impact-strip{grid-template-columns:1fr 1fr}.ai-imp-item:nth-child(2){border-right:none}.ai-imp-item:nth-child(3){border-top:1px solid #ede9df;border-right:none;grid-column:1/-1}}
</style>
</head>
<body>
<!-- Mobile topbar -->
<div class="mobile-topbar">
  <span class="m-logo">🌿 Adhaar</span>
  <button class="hamburger" id="hamburger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="logo">🌿 Adhaar<span class="logo-sub">Donor Portal</span></div>
    <a href="donor_dashboard.php" class="nav-btn active">🏠 Dashboard</a>
    <a href="donate.php" class="nav-btn">🎁 Donate</a>
    <a href="history.php" class="nav-btn">📋 History</a>
    <a href="track.php" class="nav-btn">📍 Track Donations</a>
    <span class="nav-sec">Shop</span>
    <a href="../shop/shop.php" class="nav-btn">🛍️ Browse Shop</a>
    <a href="../shop/cart.php" class="nav-btn">🛒 My Cart<?php if($cart_count>0):?><span class="nav-badge"><?=$cart_count?></span><?php endif;?></a>
    <a href="../shop/my_orders.php" class="nav-btn">📦 My Orders<?php if($orders_count>0):?><span class="nav-badge green"><?=$orders_count?></span><?php endif;?></a>
    <span class="nav-sec">Account</span>
    <a href="edit_profile.php" class="nav-btn">👤 My Profile</a>
    <div class="sidebar-footer">
      <a href="../auth/logout.php" class="logout-link">⇦ Logout</a>
    </div>
  </aside>

  <main class="main">
    <?php if($success): ?>
    <div class="success-banner">✅ Your <?=$success==='food'?'food':'clothing'?> donation was submitted! We'll review it shortly.</div>
    <?php endif; ?>

    <!-- Greeting banner -->
    <div class="greeting-bar">
      <div>
        <h3><span id="greetTime">Hello</span>, <?=htmlspecialchars($user['name'])?> 👋</h3>
        <p>Track your impact and make a difference today.</p>
      </div>
      <div class="greeting-actions">
        <a href="donate.php" class="g-btn white">🎁 Donate Now</a>
        <a href="../shop/shop.php" class="g-btn outline">🛍️ Visit Shop</a>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="cards">
      <div class="card highlight">
        <p>Total Contributions</p>
        <h1 data-count="<?=$total?>" data-suffix="">0</h1>
        <span class="card-sub">Food + Clothing combined</span>
      </div>
      <div class="card">
        <p>Food Donations</p>
        <h1 data-count="<?=$food?>" data-suffix="">0</h1>
        <span class="card-sub">Meals supported 🍱</span>
      </div>
      <div class="card">
        <p>Clothing Donations</p>
        <h1 data-count="<?=$cloth?>" data-suffix="">0</h1>
        <span class="card-sub">Lives warmed 👕</span>
      </div>
    </div>

    <!-- 🤖 AI Smart Suggestions Panel -->
    <div class="ai-panel">
      <div class="ai-panel-header">
        <span class="ai-brain">🤖</span>
        <div>
          <h3>AI Smart Suggestions</h3>
          <span>Personalised insights based on your donation history &amp; platform data</span>
        </div>
      </div>
      <div class="ai-suggestions">
        <?php foreach($ai_suggestions as $sug): ?>
        <div class="ai-suggestion">
          <span class="ai-sug-icon"><?=$sug['icon']?></span>
          <span class="ai-sug-text"><?=$sug['text']?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="ai-impact-strip">
        <div class="ai-imp-item">
          <div class="ai-imp-val"><?=number_format($ai_impact['people_fed'])?></div>
          <div class="ai-imp-label">People Fed</div>
        </div>
        <div class="ai-imp-item">
          <div class="ai-imp-val"><?=number_format($ai_impact['co2_saved_kg'])?><small style="font-size:.75rem"> kg</small></div>
          <div class="ai-imp-label">CO₂ Saved</div>
        </div>
        <div class="ai-imp-item">
          <div class="ai-imp-val">₹<?=number_format($ai_impact['economic_value'])?></div>
          <div class="ai-imp-label">Economic Value</div>
        </div>
      </div>
    </div>

    <!-- Recent donations -->
    <div class="recent-section">
      <div class="section-title">
        Recent Donations
        <a href="history.php" style="font-size:12px;font-weight:600;color:var(--accent);text-decoration:none">View All →</a>
      </div>
      <?php if(empty($recent)): ?>
        <p style="color:var(--muted);font-size:14px;padding:12px 0">No donations yet. <a href="donate.php" style="color:var(--accent);font-weight:600">Make your first →</a></p>
      <?php else: ?>
      <div style="overflow-x:auto">
      <table class="recent-table">
        <thead><tr><th>Type</th><th>Quantity</th><th>Address</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($recent as $r): ?>
          <tr>
            <td><?=htmlspecialchars($r['type'])?></td>
            <td><?=htmlspecialchars($r['quantity'])?></td>
            <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($r['pickup_address']??'—')?></td>
            <td style="white-space:nowrap"><?=date("d M Y",strtotime($r['created_at']))?></td>
            <td><span class="pill <?=htmlspecialchars($r['status'])?>"><?=ucfirst(str_replace('_',' ',$r['status']))?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Shop promo -->
    <div class="shop-promo">
      <div>
        <h3>🛍️ Support Rural Artisans</h3>
        <p>Every purchase from Adhaar Shop empowers women, artisans &amp; rural entrepreneurs.</p>
      </div>
      <a href="../shop/shop.php" class="shop-promo-btn">Browse Shop →</a>
    </div>

    <?php if(!empty($featured)): ?>
    <div class="section-title">⭐ Featured Products</div>
    <div class="product-mini-grid">
      <?php foreach($featured as $p): $img=!empty($p['image1'])?image_url($p['image1']):null; ?>
      <div class="pm-card" onclick="location.href='../shop/product.php?id=<?=(int)$p['id']?>'">
        <?php if($img): ?><img src="<?=htmlspecialchars($img)?>" class="pm-img" alt="">
        <?php else: ?><div class="pm-img-ph">🛍️</div><?php endif; ?>
        <div class="pm-body">
          <div class="pm-name"><?=htmlspecialchars($p['name'])?></div>
          <div class="pm-store">🏪 <?=htmlspecialchars($p['store_name'])?></div>
          <div class="pm-price">₹<?=number_format($p['price'],2)?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Impact jar -->
    <div class="impact-wrap">
      <div class="impact-info">
        <h2>Your Impact</h2>
        <p>Every donation is verified, tracked, and delivered through trusted volunteers.</p>
        <ul class="impact-points">
          <li>Verified donation approval</li>
          <li>Pickup coordinated by volunteers</li>
          <li>Status: Pending → Accepted → Delivered</li>
          <li>Full transparency at every step</li>
        </ul>
        <a href="donate.php" class="primary-btn">Donate Now →</a>
      </div>
      <div class="jar-container">
        <div class="jar-neck"></div>
        <div class="jar-body"><div class="jar-liquid" data-percent="<?=(int)$percent?>"></div></div>
        <p class="jar-text"><?=$total?> / <?=$goal?> Goal</p>
      </div>
    </div>

    <section class="how-it-works">
      <h2>How Adhaar Works</h2>
      <p class="how-sub">A transparent, verified, and dignified donation journey.</p>
      <div class="steps">
        <div class="step"><div class="icon">📦</div><h4>You Donate</h4><p>Submit food or clothing details with a photo.</p></div>
        <div class="step"><div class="icon">🛡️</div><h4>Verification</h4><p>Admin reviews and approves your donation.</p></div>
        <div class="step"><div class="icon">🚚</div><h4>Pickup</h4><p>Volunteer collects from your address.</p></div>
        <div class="step"><div class="icon">🤝</div><h4>Delivered</h4><p>Reaches people in need with dignity.</p></div>
      </div>
    </section>
  </main>
</div>
<div id="dashToast"></div>
<script src="../js/dashboard.js"></script>
<script src="../js/ai_chat.js"></script>
</body>
</html>
