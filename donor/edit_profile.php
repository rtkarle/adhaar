<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
$email = $_SESSION['user_email'];
$role  = $_SESSION['role'] ?? 'donor';

$stmt = $conn->prepare("SELECT name,email,mobile,address,volunteer_reason FROM register WHERE email=? AND verified=1");
$stmt->bind_param("s",$email); $stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows !== 1) { header("Location: ../auth/login.php"); exit; }
$user = $res->fetch_assoc();

$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim($_POST['name']    ?? '');
    $mobile  = trim($_POST['mobile']  ?? '');
    $address = trim($_POST['address'] ?? '');
    $reason  = trim($_POST['volunteer_reason'] ?? '');
    if (!$name || !$mobile) {
        $error = "Name and mobile are required.";
    } else {
        $up = $conn->prepare("UPDATE register SET name=?,mobile=?,address=?,volunteer_reason=? WHERE email=?");
        $up->bind_param("sssss",$name,$mobile,$address,$reason,$email);
        if ($up->execute()) {
            $_SESSION['user_name'] = $name;
            $success = "Profile updated successfully.";
            $user['name']=$name; $user['mobile']=$mobile;
            $user['address']=$address; $user['volunteer_reason']=$reason;
        } else { $error = "Update failed. Please try again."; }
    }
}

$dashboards = ['donor'=>'donor_dashboard.php','volunteer'=>'../volunteer/volunteer_dashboard.php','seller'=>'../seller/seller_dashboard.php'];
$dash = $dashboards[$role] ?? 'donor_dashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Profile | Adhaar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#f6f5f0;--accent:#7a7d3f;--accent2:#9a8f5c;--text:#2f2e26;--muted:#5a594d}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
body{min-height:100vh;background:var(--bg);display:flex;align-items:center;justify-content:center;padding:24px}
.card{background:#fff;width:100%;max-width:500px;padding:48px 44px;border-radius:28px;box-shadow:0 30px 80px rgba(60,55,35,.14);animation:fadeUp .5s ease}
@keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.card-header{margin-bottom:28px;text-align:center}
.avatar{width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;font-size:24px;color:#fff;font-weight:800;margin:0 auto 14px}
.card-header h2{font-size:22px;font-weight:800;color:var(--text)}
.card-header p{font-size:13px;color:var(--muted);margin-top:4px}
.role-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;text-transform:uppercase;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;margin-top:8px}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px}
.field input,.field textarea{width:100%;padding:12px 15px;border:2px solid #e5e3d8;border-radius:12px;font-size:14px;color:var(--text);background:#fafaf6;transition:.25s;outline:none;font-family:'Inter',sans-serif}
.field input:focus,.field textarea:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 3px rgba(122,125,63,.1)}
.field input:disabled{opacity:.55;cursor:not-allowed;background:#f0ede5}
.field textarea{resize:vertical;min-height:80px}
.btn{width:100%;padding:14px;border:none;border-radius:50px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 12px 30px rgba(122,125,63,.4);transition:.3s}
.btn:hover{transform:translateY(-2px);box-shadow:0 18px 40px rgba(122,125,63,.55)}
.back{display:block;text-align:center;margin-top:16px;color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;transition:.2s}
.back:hover{color:var(--accent)}
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;font-weight:600;display:flex;align-items:center;gap:8px}
.alert.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7}
.alert.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <div class="avatar"><?=strtoupper(substr($user['name'],0,1))?></div>
    <h2>Edit Profile</h2>
    <p>Update your personal information</p>
    <span class="role-badge"><?=ucfirst($role)?></span>
  </div>

  <?php if($success): ?><div class="alert success">✅ <?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if($error):   ?><div class="alert error">⚠️ <?=htmlspecialchars($error)?></div><?php endif; ?>

  <form method="POST">
    <?=csrf_field()?>
    <div class="field"><label>Full Name *</label><input type="text" name="name" value="<?=htmlspecialchars($user['name'])?>" required></div>
    <div class="field"><label>Email (cannot change)</label><input type="email" value="<?=htmlspecialchars($user['email'])?>" disabled></div>
    <div class="field"><label>Mobile Number *</label><input type="tel" name="mobile" value="<?=htmlspecialchars($user['mobile']??'')?>" required></div>
    <div class="field"><label>Address</label><textarea name="address"><?=htmlspecialchars($user['address']??'')?></textarea></div>
    <?php if($role==='volunteer'): ?>
    <div class="field"><label>Why I Volunteer</label><textarea name="volunteer_reason"><?=htmlspecialchars($user['volunteer_reason']??'')?></textarea></div>
    <?php endif; ?>
    <button class="btn" type="submit">💾 Save Changes →</button>
  </form>
  <a class="back" href="<?=$dash?>">← Back to Dashboard</a>
</div>
</body>
</html>
