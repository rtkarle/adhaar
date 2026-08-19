<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['google_email'])) {
    header("Location: login.php"); exit;
}

if (isset($_POST['role'])) {
    csrf_verify();
    $name  = $_SESSION['google_name'];
    $email = $_SESSION['google_email'];
    $role  = in_array($_POST['role'], ['donor','volunteer','seller']) ? $_POST['role'] : 'donor';
    $randomPass = password_hash(uniqid('g_', true), PASSWORD_DEFAULT);

    $stmt = $conn->prepare("SELECT id FROM register WHERE email=?");
    $stmt->bind_param("s", $email); $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $ins = $conn->prepare("INSERT INTO register(name,email,mobile,password,role,verified) VALUES (?,?,'',?,?,1)");
        $ins->bind_param("ssss", $name, $email, $randomPass, $role);
        if (!$ins->execute()) die("Registration error: " . $ins->error);
    } else {
        $upd = $conn->prepare("UPDATE register SET role=?, verified=1 WHERE email=?");
        $upd->bind_param("ss", $role, $email); $upd->execute();
    }

    $_SESSION['user_email'] = $email;
    $_SESSION['user_name']  = $name;
    $_SESSION['role']       = $role;

    switch ($role) {
        case 'seller':    header("Location: ../seller/seller_dashboard.php");    break;
        case 'volunteer': header("Location: ../volunteer/volunteer_dashboard.php"); break;
        default:          header("Location: ../donor/donor_dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Select Role | Adhaar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
:root{--accent:#7a7d3f;--accent2:#9a8f5c;--text:#2f2e26;--muted:#5a594d}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
body{
  min-height:100vh;
  background:linear-gradient(135deg,#1e1d18,#2f2e26,#4a4838);
  display:flex;align-items:center;justify-content:center;padding:20px;
}
.card{
  background:#fff;width:100%;max-width:520px;
  padding:52px 48px;border-radius:28px;
  box-shadow:0 40px 100px rgba(0,0,0,.45);
  animation:fadeUp .5s ease;text-align:center;
}
@keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
.brand-icon{
  width:60px;height:60px;border-radius:16px;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-size:26px;margin:0 auto 16px;
  box-shadow:0 8px 24px rgba(122,125,63,.4);
}
h1{font-size:22px;font-weight:800;color:var(--text);margin-bottom:6px}
.sub{font-size:14px;color:var(--muted);margin-bottom:8px}
.name{font-size:15px;font-weight:700;color:var(--accent);margin-bottom:32px}
.role-group{display:flex;flex-direction:column;gap:12px}
.role-btn{
  width:100%;padding:16px 20px;
  border:2px solid #e5e3d8;border-radius:14px;
  background:#fafaf6;
  display:flex;align-items:center;gap:14px;
  font-size:15px;font-weight:700;color:var(--muted);
  cursor:pointer;transition:.25s;text-align:left;
  font-family:'Inter',sans-serif;
}
.role-btn:hover{
  border-color:var(--accent);
  background:linear-gradient(135deg,#7a7d3f,#9a8f5c);
  color:#fff;transform:translateX(4px);
}
.role-icon{font-size:1.6rem;flex-shrink:0}
.role-info{display:flex;flex-direction:column;gap:2px}
.role-title{font-size:15px;font-weight:800}
.role-desc{font-size:11px;opacity:.75;font-weight:500}
</style>
</head>
<body>
<div class="card">
  <div class="brand-icon">🌿</div>
  <h1>Welcome to Adhaar!</h1>
  <p class="sub">Signed in as</p>
  <p class="name">📧 <?= htmlspecialchars($_SESSION['google_email']) ?></p>
  <form method="POST">
    <?= csrf_field() ?>
    <div class="role-group">
      <button class="role-btn" name="role" value="donor" type="submit">
        <span class="role-icon">🎁</span>
        <div class="role-info">
          <span class="role-title">I am a Donor</span>
          <span class="role-desc">Donate surplus food &amp; clothing</span>
        </div>
      </button>
      <button class="role-btn" name="role" value="volunteer" type="submit">
        <span class="role-icon">🤝</span>
        <div class="role-info">
          <span class="role-title">I am a Volunteer</span>
          <span class="role-desc">Help with pickups &amp; deliveries</span>
        </div>
      </button>
      <button class="role-btn" name="role" value="seller" type="submit">
        <span class="role-icon">🏪</span>
        <div class="role-info">
          <span class="role-title">I am a Seller</span>
          <span class="role-desc">Sell handmade &amp; local products</span>
        </div>
      </button>
    </div>
  </form>
</div>
</body>
</html>
