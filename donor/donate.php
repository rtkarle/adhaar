<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
$email = $_SESSION['user_email'];

$latest = $conn->prepare("SELECT status FROM food_donations WHERE donor_email=? UNION ALL SELECT status FROM cloth_donations WHERE donor_email=? ORDER BY 1 DESC LIMIT 1");
$latest->bind_param("ss",$email,$email); $latest->execute();
$row = $latest->get_result()->fetch_assoc();
$latestStatus = $row['status'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donate | Adhaar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f5f0;--accent:#7a7d3f;--accent2:#9a8f5c;--text:#2f2e26;--muted:#5a594d;--card:#fff;--shadow:0 20px 60px rgba(60,55,35,.12);--radius:24px}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
body{background:var(--bg);color:var(--text);min-height:100vh}
header{position:fixed;top:0;width:100%;background:rgba(255,255,255,.92);backdrop-filter:blur(12px);box-shadow:0 2px 20px rgba(0,0,0,.07);z-index:999}
.nav-container{max-width:1150px;margin:auto;padding:0 24px;height:76px;display:flex;justify-content:space-between;align-items:center}
.logo-text{font-size:22px;font-weight:900;color:var(--accent);text-decoration:none}
.nav a{margin-left:22px;font-weight:600;color:var(--text);text-decoration:none;font-size:14px;transition:.2s}
.nav a:hover{color:var(--accent)}
.btn-nav{border:2px solid var(--accent);padding:8px 16px;border-radius:8px;color:var(--accent)}
.btn-nav:hover{background:var(--accent);color:#fff!important}
.menu-icon{display:none;font-size:28px;color:var(--accent);cursor:pointer}
@media(max-width:850px){.menu-icon{display:block}.nav{display:none}.nav.show{display:flex;position:fixed;top:76px;right:0;width:230px;height:100vh;flex-direction:column;padding:24px;background:rgba(255,255,255,.97);border-left:3px solid var(--accent);box-shadow:-4px 0 20px rgba(0,0,0,.12)}}
.page{padding-top:100px;padding-bottom:80px}
.container{max-width:720px;margin:auto;padding:0 20px}
.success-banner{background:linear-gradient(135deg,#d1fae5,#a7f3d0);border:1.5px solid #6ee7b7;color:#065f46;padding:16px 24px;border-radius:14px;font-weight:600;font-size:14px;margin-bottom:28px;display:flex;align-items:center;gap:10px}
.timeline{display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:40px}
.tl-step{display:flex;flex-direction:column;align-items:center;font-size:12px;color:var(--muted);font-weight:500;flex:1;text-align:center}
.tl-dot{width:14px;height:14px;border-radius:50%;background:#d4d0c4;margin-bottom:8px;transition:.3s}
.tl-step.done .tl-dot{background:var(--accent);box-shadow:0 0 0 5px rgba(122,125,63,.2)}
.tl-step.done{color:var(--accent);font-weight:700}
.tl-line{flex:1;height:2px;background:#d4d0c4;margin-bottom:22px}
.tl-line.done{background:var(--accent)}
.choice-card{background:var(--card);padding:44px 40px;border-radius:var(--radius);box-shadow:var(--shadow);text-align:center;margin-bottom:28px}
.choice-card h2{font-size:26px;font-weight:800;margin-bottom:8px}
.choice-card p{color:var(--muted);font-size:14px;margin-bottom:32px}
.choice-btns{display:flex;gap:16px;justify-content:center;flex-wrap:wrap}
.choice-btn{padding:16px 36px;border-radius:50px;border:2px solid var(--accent);background:transparent;color:var(--accent);font-weight:700;font-size:15px;cursor:pointer;transition:.3s}
.choice-btn:hover{background:var(--accent);color:#fff;transform:translateY(-2px)}
.form-card{background:var(--card);padding:44px 40px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:28px}
.form-card h2{font-size:22px;font-weight:800;margin-bottom:6px}
.form-note{font-size:13px;color:var(--muted);margin-bottom:28px}
.field{margin-bottom:20px}
.field label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px}
.field input,.field select,.field textarea{width:100%;padding:13px 16px;border:2px solid #e5e3d8;border-radius:12px;font-size:14px;color:var(--text);background:#fafaf6;transition:.25s;outline:none;font-family:'Inter',sans-serif}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent);background:#fff}
.checkbox-row{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted);margin-bottom:20px}
.checkbox-row input{width:auto;accent-color:var(--accent)}
.impact-msg{text-align:center;font-size:13px;color:var(--accent);font-weight:600;margin-bottom:20px;background:#f0f0e4;padding:10px;border-radius:10px}
.submit-btn{width:100%;padding:15px;border:none;border-radius:50px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 12px 30px rgba(122,125,63,.4);transition:.3s}
.submit-btn:hover{transform:translateY(-2px);box-shadow:0 18px 40px rgba(122,125,63,.55)}
.back-link{display:block;text-align:center;margin-top:16px;color:var(--muted);font-size:13px;font-weight:600;cursor:pointer;transition:.2s}
.back-link:hover{color:var(--accent)}
.rules-card{background:var(--card);padding:32px 36px;border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:28px}
.rules-card h3{font-size:16px;font-weight:700;margin-bottom:16px;color:var(--accent)}
.rules-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.rule-item{display:flex;align-items:flex-start;gap:8px;font-size:13px;color:var(--muted);line-height:1.5}
.hidden{display:none!important}
@media(max-width:600px){.form-card,.choice-card{padding:32px 24px}.choice-btns{flex-direction:column}.rules-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header>
  <div class="nav-container">
    <div style="display:flex;align-items:center;gap:12px">
      <a href="donor_dashboard.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--accent);font-weight:700;font-size:13px;text-decoration:none;padding:6px 12px;border:1.5px solid rgba(122,125,63,.3);border-radius:8px">← Dashboard</a>
      <a href="../index.html" class="logo-text">🌿 Adhaar</a>
    </div>
    <nav class="nav" id="mobileMenu">
      <a href="../index.html">Home</a>
      <a href="donor_dashboard.php">Dashboard</a>
      <a href="history.php">History</a>
      <a href="track.php">Track</a>
      <a href="../auth/logout.php" class="btn-nav">Logout</a>
    </nav>
    <div class="menu-icon" id="menuToggle">☰</div>
  </div>
</header>
<div class="page"><div class="container">
  <?php if($success): ?>
  <div class="success-banner">✅ Your <?=$success==='food'?'food':'clothing'?> donation was submitted! We'll review it shortly.</div>
  <?php endif; ?>
  <div class="timeline">
    <div class="tl-step <?=$latestStatus?'done':''?>"><div class="tl-dot"></div>Submitted</div>
    <div class="tl-line <?=in_array($latestStatus,['accepted','scheduled','out_for_pickup','picked_up','delivered'])?'done':''?>"></div>
    <div class="tl-step <?=in_array($latestStatus,['accepted','scheduled','out_for_pickup','picked_up','delivered'])?'done':''?>"><div class="tl-dot"></div>Accepted</div>
    <div class="tl-line <?=in_array($latestStatus,['scheduled','out_for_pickup','picked_up','delivered'])?'done':''?>"></div>
    <div class="tl-step <?=in_array($latestStatus,['scheduled','out_for_pickup','picked_up','delivered'])?'done':''?>"><div class="tl-dot"></div>Scheduled</div>
    <div class="tl-line <?=in_array($latestStatus,['picked_up','delivered'])?'done':''?>"></div>
    <div class="tl-step <?=in_array($latestStatus,['picked_up','delivered'])?'done':''?>"><div class="tl-dot"></div>Delivered</div>
  </div>
  <div class="rules-card">
    <h3>📋 Donation Guidelines</h3>
    <div class="rules-grid">
      <div class="rule-item">✅ Fresh cooked food (same day)</div>
      <div class="rule-item">✅ Clean & wearable clothes</div>
      <div class="rule-item">❌ Expired or stale food</div>
      <div class="rule-item">❌ Torn or unusable clothes</div>
    </div>
  </div>
  <div id="donateChoice" class="choice-card">
    <h2>What would you like to donate?</h2>
    <p>Choose a category to get started.</p>
    <div class="choice-btns">
      <button class="choice-btn" onclick="openFood()">🍲 Donate Food</button>
      <button class="choice-btn" onclick="openCloth()">👕 Donate Clothes</button>
    </div>
  </div>
  <div id="foodForm" class="form-card hidden">
    <h2>🍲 Food Donation</h2>
    <p class="form-note">Food must be freshly prepared and properly packed.</p>
    <form action="../api/food_donate.php" method="POST" enctype="multipart/form-data">
      <?=csrf_field()?>
      <div class="field"><label>When was the food prepared?</label><input type="datetime-local" name="prepared_at" required></div>
      <div class="field"><label>Safe to eat for</label><select name="safe_hours" required><option value="">Select duration</option><option value="2">2 hours</option><option value="4">4 hours</option><option value="6">6 hours</option><option value="8">8+ hours</option></select></div>
      <div class="field"><label>Approx. quantity (serves how many people)</label><input type="number" name="quantity" min="1" placeholder="e.g. 20" required></div>
      <div class="field"><label>Urgency</label><select name="priority" required><option value="">Select urgency</option><option value="high">High – needs pickup today</option><option value="medium">Medium – within 24 hours</option><option value="low">Low – flexible</option></select></div>
      <div class="field"><label>Pickup Address</label><input type="text" name="pickup_address" placeholder="Full address for pickup" required></div>
      <div class="field"><label>Contact Number</label><input type="tel" name="contact" pattern="[0-9]{10}" placeholder="10-digit mobile number" required></div>
      <div class="checkbox-row"><input type="checkbox" id="foodSafe" required><label for="foodSafe">I confirm the food is safe and hygienic to consume</label></div>
      <div class="field"><label>Upload Photo</label><input type="file" name="image" accept="image/*" required></div>
      <div class="impact-msg">🌱 Your donation can feed 5–20 people today</div>
      <button class="submit-btn" type="submit">Submit Food Donation →</button>
    </form>
    <span class="back-link" onclick="backToChoice()">← Back to choices</span>
  </div>
  <div id="clothForm" class="form-card hidden">
    <h2>👕 Clothing Donation</h2>
    <p class="form-note">Clothes must be clean, wearable, and in good condition.</p>
    <form action="../api/cloth_donate.php" method="POST" enctype="multipart/form-data">
      <?=csrf_field()?>
      <div class="field"><label>When were the clothes purchased?</label><input type="text" name="purchase_time" placeholder="e.g. 2 years ago / 2022" required></div>
      <div class="field"><label>Number of clothes</label><input type="number" name="quantity" min="1" placeholder="e.g. 5" required></div>
      <div class="field"><label>Clothing type</label><select name="cloth_type" required><option value="">Select type</option><option value="Men">Men</option><option value="Women">Women</option><option value="Children">Children</option><option value="Mixed">Mixed</option></select></div>
      <div class="field"><label>Condition</label><select name="condition_type" required><option value="good">Good</option><option value="fair">Fair</option></select></div>
      <div class="checkbox-row"><input type="checkbox" name="is_clean" id="isClean" value="1"><label for="isClean">I confirm the clothes are clean and washed</label></div>
      <div class="field"><label>Pickup Address</label><input type="text" name="pickup_address" placeholder="Full address for pickup" required></div>
      <div class="field"><label>Contact Number</label><input type="tel" name="contact" pattern="[0-9]{10}" placeholder="10-digit mobile number" required></div>
      <div class="field"><label>Upload Photo</label><input type="file" name="image" accept="image/*" required></div>
      <div class="impact-msg">👕 Brings warmth and dignity to someone in need</div>
      <button class="submit-btn" type="submit">Submit Clothing Donation →</button>
    </form>
    <span class="back-link" onclick="backToChoice()">← Back to choices</span>
  </div>
</div></div>
<script>
const choice=document.getElementById("donateChoice"),food=document.getElementById("foodForm"),cloth=document.getElementById("clothForm");
function openFood(){choice.classList.add("hidden");food.classList.remove("hidden");cloth.classList.add("hidden");window.scrollTo({top:0,behavior:'smooth'})}
function openCloth(){choice.classList.add("hidden");food.classList.add("hidden");cloth.classList.remove("hidden");window.scrollTo({top:0,behavior:'smooth'})}
function backToChoice(){choice.classList.remove("hidden");food.classList.add("hidden");cloth.classList.add("hidden")}
document.getElementById("menuToggle").addEventListener("click",()=>document.getElementById("mobileMenu").classList.toggle("show"));
</script>
</body>
</html>
