<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

$error = "";

if (isset($_POST['send_otp'])) {
  csrf_verify();
  if ($_POST['password'] != $_POST['confirm']) {
    $error = "Passwords do not match.";
  } else {
    $email = trim($_POST['email']);
    $role  = in_array($_POST['role'], ['donor','volunteer','seller']) ? $_POST['role'] : 'donor';
    $check = $conn->prepare("SELECT id FROM register WHERE email=?");
    $check->bind_param("s", $email); $check->execute();
    if ($check->get_result()->num_rows > 0) {
      $error = "Email already registered.";
    } else {
      $_SESSION['regdata'] = [
        "name"     => trim($_POST['name']),
        "email"    => $email,
        "mobile"   => trim($_POST['mobile']),
        "password" => password_hash($_POST['password'], PASSWORD_DEFAULT),
        "role"     => $role,
        "volunteer_reason" => trim($_POST['volunteer_reason'] ?? ''),
      ];
      $otp = rand(100000, 999999);
      $del = $conn->prepare("DELETE FROM otps WHERE email=?");
      $del->bind_param("s", $email); $del->execute();
      $stmt = $conn->prepare("INSERT INTO otps(email,otp,created_at) VALUES(?,?,NOW())");
      $stmt->bind_param("ss", $email, $otp); $stmt->execute();
      $_SESSION['regdata']['otp'] = $otp;
      sendOTPMail($email, $otp);
      header("Location: verify_otp.php"); exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register | Adhaar – The SoulServe</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{ --accent:#7a7d3f; --accent2:#9a8f5c; --text:#2f2e26; --muted:#5a594d; }
*{ margin:0; padding:0; box-sizing:border-box; font-family:'Inter',system-ui,sans-serif; }
body{
  min-height:100vh;
  background:linear-gradient(135deg,#2f2e26 0%,#4a4838 40%,#7a7d3f 100%);
  display:flex; align-items:center; justify-content:center; padding:24px 16px;
}
.card{ width:100%; max-width:500px; background:#fff; border-radius:24px; box-shadow:0 24px 64px rgba(47,46,38,.28); overflow:hidden; }
.card-header{ background:linear-gradient(135deg,#7a7d3f,#9a8f5c); padding:28px 36px 24px; text-align:center; }
.card-header h1{ color:#fff; font-size:22px; font-weight:800; margin-bottom:4px; }
.card-header p{ color:rgba(255,255,255,.82); font-size:13px; }
.card-body{ padding:28px 32px 32px; }
.error-box{ background:#fee2e2; color:#991b1b; border-radius:10px; padding:11px 16px; font-size:13px; font-weight:600; margin-bottom:18px; }
.field{ margin-bottom:16px; }
.field label{ display:block; font-size:12px; font-weight:700; color:var(--muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:.4px; }
.field input, .field textarea, .field select{
  width:100%; padding:12px 14px; border:1.5px solid #e0ddd5;
  border-radius:10px; font-size:14px; font-family:inherit; color:var(--text);
  transition:.2s; background:#fafaf6;
}
.field input:focus, .field textarea:focus, .field select:focus{
  border-color:var(--accent); outline:none; background:#fff;
  box-shadow:0 0 0 3px rgba(122,125,63,.12);
}
.field textarea{ resize:vertical; min-height:80px; }
/* Role selector */
.role-group{ display:flex; gap:10px; flex-wrap:wrap; }
.role-option{ flex:1; min-width:120px; }
.role-option input[type="radio"]{ display:none; }
.role-option label{
  display:flex; align-items:center; justify-content:center; gap:7px;
  padding:11px 8px; border:1.5px solid #e0ddd5; border-radius:10px;
  font-size:13px; font-weight:600; color:var(--muted); cursor:pointer; transition:.25s;
  background:#fafaf6; text-align:center; flex-direction:column; line-height:1.3;
}
.role-option input[type="radio"]:checked + label{
  border-color:var(--accent); background:linear-gradient(135deg,#7a7d3f,#9a8f5c);
  color:#fff; box-shadow:0 4px 14px rgba(122,125,63,.3);
}
.role-option label:hover{ border-color:var(--accent); color:var(--accent); }
.role-icon{ font-size:22px; }
.role-desc{ font-size:10px; opacity:.8; font-weight:500; }
/* Conditional section */
.conditional-section{ display:none; background:#f8f7f0; border-radius:12px; padding:16px; margin-bottom:16px; border-left:3px solid var(--accent); }
.conditional-section.show{ display:block; }
.conditional-section p{ font-size:12px; color:var(--muted); margin-bottom:10px; font-weight:600; }
/* Password strength */
.strength-bar{ height:4px; border-radius:4px; background:#e0ddd5; margin-top:8px; overflow:hidden; }
.strength-fill{ height:100%; width:0%; border-radius:4px; transition:.3s; }
.strength-label{ font-size:11px; color:var(--muted); margin-top:4px; font-weight:600; }
.submit-btn{
  width:100%; padding:13px; background:linear-gradient(135deg,#7a7d3f,#9a8f5c);
  color:#fff; border:none; border-radius:12px; font-size:15px; font-weight:700;
  cursor:pointer; transition:.3s; box-shadow:0 4px 16px rgba(122,125,63,.3); margin-top:4px;
}
.submit-btn:hover{ opacity:.9; transform:translateY(-1px); box-shadow:0 8px 22px rgba(122,125,63,.38); }
.divider{ display:flex; align-items:center; gap:12px; margin:18px 0; }
.divider::before,.divider::after{ content:''; flex:1; height:1px; background:#e0ddd5; }
.divider span{ font-size:12px; color:var(--muted); font-weight:600; }
.google-btn{
  width:100%; padding:11px 14px; border:1.5px solid #e0ddd5; border-radius:12px;
  background:#fff; display:flex; align-items:center; justify-content:center;
  gap:10px; font-size:14px; font-weight:600; color:var(--text); text-decoration:none; transition:.25s;
}
.google-btn:hover{ background:#fafaf6; border-color:#bbb; }
.google-btn img{ width:18px; height:18px; }
.switch{ text-align:center; margin-top:20px; font-size:13px; color:var(--muted); }
.switch a{ color:var(--accent); font-weight:700; text-decoration:none; }
.switch a:hover{ text-decoration:underline; }
/* Role info banners */
.role-info{ display:none; background:linear-gradient(135deg,#f0f0e4,#e8e5d5); border-radius:10px; padding:12px 14px; margin-bottom:16px; font-size:12px; color:var(--muted); line-height:1.6; }
.role-info.show{ display:block; }
.role-info strong{ color:var(--accent); }
</style>
</head>
<body>
<div class="card">
  <div class="card-header">
    <h1>🌿 Create Account</h1>
    <p>Join Adhaar – The SoulServe and start contributing</p>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
      <div class="error-box">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="registerForm" novalidate>
      <?= csrf_field() ?>

      <!-- ROLE SELECTION FIRST -->
      <div class="field">
        <label>I want to join as</label>
        <div class="role-group">
          <div class="role-option">
            <input type="radio" name="role" id="role_donor" value="donor" checked>
            <label for="role_donor">
              <span class="role-icon">🎁</span>
              <span>Donor</span>
              <span class="role-desc">Donate food & clothes</span>
            </label>
          </div>
          <div class="role-option">
            <input type="radio" name="role" id="role_volunteer" value="volunteer">
            <label for="role_volunteer">
              <span class="role-icon">🤝</span>
              <span>Volunteer</span>
              <span class="role-desc">Help with pickups</span>
            </label>
          </div>
          <div class="role-option">
            <input type="radio" name="role" id="role_seller" value="seller">
            <label for="role_seller">
              <span class="role-icon">🏪</span>
              <span>Seller</span>
              <span class="role-desc">Sell your products</span>
            </label>
          </div>
        </div>
      </div>

      <!-- Role info banners -->
      <div id="info_donor" class="role-info show">
        <strong>Donor:</strong> Donate surplus food and clothing. Track every donation with live status updates.
      </div>
      <div id="info_volunteer" class="role-info">
        <strong>Volunteer:</strong> Pick up and deliver donations. Accept tasks assigned by admin and earn certificates.
      </div>
      <div id="info_seller" class="role-info">
        <strong>Seller:</strong> Sell your handmade, organic, or local products. Perfect for rural entrepreneurs, artisans, and women empowerment groups.
      </div>

      <div class="field">
        <label>Full Name</label>
        <input name="name" type="text" placeholder="Your full name" required>
      </div>
      <div class="field">
        <label>Email Address</label>
        <input name="email" type="email" placeholder="you@example.com" required>
      </div>
      <div class="field">
        <label>Mobile Number</label>
        <input name="mobile" type="tel" placeholder="+91 98765 43210" required>
      </div>
      <div class="field">
        <label>Password</label>
        <input name="password" type="password" id="pwdInput" placeholder="Create a strong password" required>
        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
        <div class="strength-label" id="strengthLabel"></div>
      </div>
      <div class="field">
        <label>Confirm Password</label>
        <input name="confirm" type="password" placeholder="Repeat your password" required>
      </div>

      <!-- Volunteer extra field -->
      <div id="volunteer_extra" class="conditional-section">
        <p>📝 Tell us why you want to volunteer</p>
        <textarea name="volunteer_reason" placeholder="Why do you want to volunteer with Adhaar?"></textarea>
      </div>

      <!-- Seller extra field -->
      <div id="seller_extra" class="conditional-section">
        <p>🏪 Tell us briefly about what you want to sell</p>
        <textarea name="seller_reason" placeholder="e.g. I make handmade jewelry, organic spices, handloom sarees..."></textarea>
      </div>

      <button type="submit" name="send_otp" class="submit-btn">Send OTP & Continue →</button>
    </form>

    <div class="divider"><span>OR</span></div>
    <a href="google_login.php" class="google-btn">
      <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
      Continue with Google
    </a>
    <p class="switch">Already have an account? <a href="login.php">Login</a></p>
  </div>
</div>
<script>
const pwdInput = document.getElementById('pwdInput');
const fill     = document.getElementById('strengthFill');
const label    = document.getElementById('strengthLabel');
const levels = [
  { re:/.{1,5}/,                                                          color:'#ef4444', w:'20%', text:'Very Weak' },
  { re:/.{6,7}/,                                                          color:'#f97316', w:'40%', text:'Weak' },
  { re:/^(?=.*[a-z])(?=.*[A-Z]).{8,}$/,                                  color:'#eab308', w:'60%', text:'Fair' },
  { re:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/,                         color:'#84cc16', w:'80%', text:'Good' },
  { re:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{10,}$/,             color:'#22c55e', w:'100%', text:'Strong' },
];
pwdInput.addEventListener('input', () => {
  const val = pwdInput.value;
  if (!val) { fill.style.width='0'; label.textContent=''; return; }
  let lvl = levels[0];
  for (const l of levels) { if (l.re.test(val)) lvl = l; }
  fill.style.width = lvl.w; fill.style.background = lvl.color;
  label.textContent = lvl.text; label.style.color = lvl.color;
});

// Role-based UI
const roles = ['donor','volunteer','seller'];
roles.forEach(r => {
  document.getElementById('role_'+r).addEventListener('change', function() {
    roles.forEach(x => {
      document.getElementById('info_'+x).classList.remove('show');
    });
    document.getElementById('info_'+r).classList.add('show');
    document.getElementById('volunteer_extra').classList.toggle('show', r==='volunteer');
    document.getElementById('seller_extra').classList.toggle('show', r==='seller');
  });
});
</script>
</body>
</html>
