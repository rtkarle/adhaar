<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    require_once __DIR__ . '/../config/db.php';
    csrf_verify();

    $email = trim($_POST['email'] ?? '');
    $pass  = trim($_POST['password'] ?? '');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!$email || !$pass) { header("Location: login.php?error=1"); exit; }

    // ── Rate limiting: 5 attempts per 5 minutes per email ────
    $window  = date('Y-m-d H:i:s', time() - 300); // 5 min window
    $chk_tbl = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($chk_tbl && $chk_tbl->num_rows > 0) {
        $attempts = (int)$conn->query(
            "SELECT COUNT(*) c FROM login_attempts WHERE email='".mysqli_real_escape_string($conn,$email)."' AND attempted_at > '$window'"
        )->fetch_assoc()['c'];

        if ($attempts >= 5) {
            // Log the blocked attempt
            $ins = $conn->prepare("INSERT INTO login_attempts (email,ip,attempted_at) VALUES (?,?,NOW())");
            $ins->bind_param("ss",$email,$ip); $ins->execute();
            header("Location: login.php?error=1&locked=1"); exit;
        }
    }

    $stmt = $conn->prepare("SELECT * FROM register WHERE email=? AND verified=1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        if (password_verify($pass, $user['password'])) {
            // Clear login attempts on success
            $chk_tbl2 = $conn->query("SHOW TABLES LIKE 'login_attempts'");
            if ($chk_tbl2 && $chk_tbl2->num_rows > 0) {
                $conn->query("DELETE FROM login_attempts WHERE email='".mysqli_real_escape_string($conn,$email)."'");
            }
            // Regenerate session ID on login to prevent session fixation
            session_regenerate_id(true);
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['role']       = $user['role'];
            switch ($user['role']) {
                case 'volunteer': header("Location: ../volunteer/volunteer_dashboard.php"); break;
                case 'seller':    header("Location: ../seller/seller_dashboard.php");    break;
                default:          header("Location: ../donor/donor_dashboard.php");
            }
            exit;
        }
    }
    // Track failed login attempt
    $chk_tbl3 = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($chk_tbl3 && $chk_tbl3->num_rows > 0) {
        $ins2 = $conn->prepare("INSERT INTO login_attempts (email,ip,attempted_at) VALUES (?,?,NOW())");
        $ins2->bind_param("ss",$email,$ip); $ins2->execute();
    }
    header("Location: login.php?error=1"); exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
$error      = isset($_GET['error']);
$registered = isset($_GET['registered']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login | Adhaar – The SoulServe</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
  :root{ --accent:#7a7d3f; --accent2:#9a8f5c; --text:#2f2e26; --muted:#5a594d; }
  *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
  body{
    min-height:100vh;
    background:linear-gradient(135deg,#2f2e26 0%,#4a4a30 50%,#2f2e26 100%);
    display:flex;align-items:center;justify-content:center;padding:20px;
  }
  .card{
    background:#fff;width:100%;max-width:420px;
    padding:48px 44px;border-radius:28px;
    box-shadow:0 40px 100px rgba(0,0,0,.35);
    animation:fadeUp .5s ease;
  }
  @keyframes fadeUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
  .brand{text-align:center;margin-bottom:32px}
  .brand-icon{
    width:56px;height:56px;border-radius:16px;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    display:flex;align-items:center;justify-content:center;
    font-size:26px;margin:0 auto 14px;
  }
  .brand h1{font-size:22px;font-weight:800;color:var(--text)}
  .brand p{font-size:13px;color:var(--muted);margin-top:4px}
  .google-btn{
    width:100%;height:46px;padding:0 16px;
    border-radius:12px;border:2px solid #e5e3d8;
    background:#fafaf6;
    display:flex;align-items:center;justify-content:center;gap:10px;
    font-size:14px;font-weight:600;color:var(--text);
    cursor:pointer;transition:.2s ease;text-decoration:none;margin-bottom:20px;
  }
  .google-btn img{width:18px;height:18px}
  .google-btn:hover{background:#f0ede4;border-color:var(--accent)}
  .divider{display:flex;align-items:center;gap:12px;margin-bottom:20px;color:var(--muted);font-size:12px;}
  .divider::before,.divider::after{content:"";flex:1;height:1px;background:#e5e3d8;}
  .field{margin-bottom:18px}
  .field label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px}
  .field input{
    width:100%;padding:13px 16px;
    border:2px solid #e5e3d8;border-radius:12px;
    font-size:14px;color:var(--text);background:#fafaf6;
    transition:.25s ease;outline:none;
  }
  .field input:focus{border-color:var(--accent);background:#fff}
  .forgot{display:block;text-align:right;font-size:12px;color:var(--accent);text-decoration:none;margin-top:-10px;margin-bottom:18px;font-weight:600}
  .forgot:hover{text-decoration:underline}
  .btn{
    width:100%;padding:14px;border:none;border-radius:50px;
    background:linear-gradient(135deg,var(--accent),var(--accent2));
    color:#fff;font-size:15px;font-weight:700;cursor:pointer;
    box-shadow:0 12px 30px rgba(122,125,63,.4);transition:.3s ease;
  }
  .btn:hover{transform:translateY(-2px);box-shadow:0 18px 40px rgba(122,125,63,.55)}
  .switch{text-align:center;margin-top:20px;font-size:13px;color:var(--muted)}
  .switch a{color:var(--accent);font-weight:600;text-decoration:none}
  .error-msg{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;text-align:center;}
  .success-msg{background:#d1fae5;color:#065f46;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:18px;text-align:center;font-weight:600;}
  .role-hint{display:flex;gap:8px;justify-content:center;margin-top:16px;flex-wrap:wrap}
  .rh{font-size:11px;background:#f6f5f0;padding:4px 10px;border-radius:20px;color:var(--muted);font-weight:600}
  </style>
</head>
<body>
<div class="card">
  <div class="brand">
    <div class="brand-icon">🌿</div>
    <h1>Welcome Back</h1>
    <p>Login to your Adhaar account</p>
  </div>

  <?php if ($error): ?>
    <div class="error-msg">
      <?php if(isset($_GET['locked'])): ?>
        🔒 Too many failed attempts. Please wait 5 minutes before trying again.
      <?php else: ?>
        Invalid email or password. Please try again.
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($registered): ?>
    <div class="success-msg">✅ Account created! Please login.</div>
  <?php endif; ?>

  <a href="google_login.php" class="google-btn">
    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google">
    Continue with Google
  </a>

  <div class="divider">or login with email</div>

  <form action="login.php" method="POST">
    <?= csrf_field() ?>
    <div class="field">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="you@example.com" required autocomplete="email">
    </div>
    <div class="field">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>
    <a href="forgot.php" class="forgot">Forgot password?</a>
    <button class="btn" type="submit">Login →</button>
  </form>

  <div class="role-hint">
    <span class="rh">🎁 Donor</span>
    <span class="rh">🤝 Volunteer</span>
    <span class="rh">🏪 Seller</span>
  </div>

  <p class="switch">Don't have an account? <a href="register.php">Create one</a></p>
</div>
</body>
</html>
