<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
$email = $_SESSION['user_email'];

$q = $conn->prepare("SELECT name,email,mobile,address,volunteer_reason FROM register WHERE email=? AND role='volunteer' AND verified=1");
$q->bind_param("s",$email); $q->execute(); $res=$q->get_result();
if($res->num_rows!==1){ header("Location: ../auth/login.php"); exit; }
$user = $res->fetch_assoc();

// Handle task accept/reject
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['task_action'])){
    csrf_verify();
    $tid    = (int)$_POST['task_id'];
    $action = in_array($_POST['task_action'],['accepted','rejected']) ? $_POST['task_action'] : 'rejected';
    $tq = $conn->prepare("UPDATE volunteer_tasks SET task_status=?,responded_at=NOW() WHERE id=? AND volunteer_email=?");
    $tq->bind_param("sis",$action,$tid,$email);
    $tq->execute();
    header("Location: volunteer_dashboard.php?tab=tasks"); exit;
}

$tab = $_GET['tab'] ?? 'assigned';

// Assigned donations
$af=$conn->prepare("SELECT id,'Food' AS type,quantity,pickup_address,contact,status,created_at,image,donor_email,notes FROM food_donations WHERE volunteer_email=? AND status NOT IN ('delivered','rejected') ORDER BY created_at DESC");
$af->bind_param("s",$email);$af->execute();$assigned_food=$af->get_result()->fetch_all(MYSQLI_ASSOC);
$ac=$conn->prepare("SELECT id,'Cloth' AS type,quantity,pickup_address,contact,status,created_at,image,donor_email,notes FROM cloth_donations WHERE volunteer_email=? AND status NOT IN ('delivered','rejected') ORDER BY created_at DESC");
$ac->bind_param("s",$email);$ac->execute();$assigned_cloth=$ac->get_result()->fetch_all(MYSQLI_ASSOC);
$assigned = array_merge($assigned_food,$assigned_cloth);
usort($assigned, fn($a,$b)=>strtotime($b['created_at'])-strtotime($a['created_at']));

// Completed
$cf=$conn->prepare("SELECT id,'Food' AS type,quantity,pickup_address,status,created_at,donor_email FROM food_donations WHERE volunteer_email=? AND status='delivered' ORDER BY created_at DESC");
$cf->bind_param("s",$email);$cf->execute();$comp_food=$cf->get_result()->fetch_all(MYSQLI_ASSOC);
$cc=$conn->prepare("SELECT id,'Cloth' AS type,quantity,pickup_address,status,created_at,donor_email FROM cloth_donations WHERE volunteer_email=? AND status='delivered' ORDER BY created_at DESC");
$cc->bind_param("s",$email);$cc->execute();$comp_cloth=$cc->get_result()->fetch_all(MYSQLI_ASSOC);
$completed = array_merge($comp_food,$comp_cloth);
usort($completed, fn($a,$b)=>strtotime($b['created_at'])-strtotime($a['created_at']));

// Pending tasks (accept/reject)
$tq=$conn->prepare("SELECT vt.* FROM volunteer_tasks vt WHERE vt.volunteer_email=? AND vt.task_status='pending_acceptance' ORDER BY vt.assigned_at DESC");
$tq->bind_param("s",$email);$tq->execute();$pending_tasks=$tq->get_result()->fetch_all(MYSQLI_ASSOC);

// Peer volunteers
$pvq=$conn->query("SELECT name,email,mobile,address FROM register WHERE role='volunteer' AND verified=1 AND email!='".mysqli_real_escape_string($conn,$email)."' ORDER BY name LIMIT 20");
$peers = $pvq->fetch_all(MYSQLI_ASSOC);

// Cart & orders
$cart_count   = (int)$conn->query("SELECT COUNT(*) c FROM cart WHERE user_email='".mysqli_real_escape_string($conn,$email)."'")->fetch_assoc()['c'];
$orders_count = (int)$conn->query("SELECT COUNT(*) c FROM orders WHERE buyer_email='".mysqli_real_escape_string($conn,$email)."'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Volunteer Dashboard | Adhaar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css">
<style>
/* Volunteer-specific extras */
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.top-bar h3{font-size:20px;font-weight:800}.top-bar h3 span{color:var(--accent)}
.logout-link-top{text-decoration:none;padding:8px 18px;border:1.5px solid var(--accent);color:var(--accent);border-radius:8px;font-size:13px;font-weight:600;transition:.25s}
.logout-link-top:hover{background:var(--accent);color:#fff}
/* Task card */
.task-card{background:var(--card);border-radius:14px;padding:20px;box-shadow:var(--shadow);margin-bottom:14px;border-left:4px solid #f59e0b;animation:tabIn .3s ease}
.task-card h4{font-size:15px;font-weight:700;margin-bottom:8px}
.task-meta{font-size:13px;color:var(--muted);margin-bottom:14px;line-height:1.7}
.task-actions{display:flex;gap:10px;flex-wrap:wrap}
.btn-accept-task{padding:9px 20px;border:none;border-radius:10px;background:#d1fae5;color:#065f46;font-size:13px;font-weight:700;cursor:pointer;transition:.2s}
.btn-accept-task:hover{background:#a7f3d0}
.btn-reject-task{padding:9px 20px;border:none;border-radius:10px;background:#fee2e2;color:#991b1b;font-size:13px;font-weight:700;cursor:pointer;transition:.2s}
.btn-reject-task:hover{background:#fca5a5}
/* Peers */
.peer-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
.peer-card{background:var(--card);border-radius:14px;padding:20px;box-shadow:var(--shadow);border-top:3px solid var(--accent2);transition:.3s}
.peer-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}
.peer-avatar{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;font-weight:800;margin-bottom:12px}
.peer-name{font-size:14px;font-weight:700;margin-bottom:4px}
.peer-meta{font-size:12px;color:var(--muted);line-height:1.65}
/* Delivery proof modal */
.proof-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;padding:20px}
.proof-modal-overlay.open{display:flex}
.proof-modal{background:#fff;border-radius:20px;padding:32px;max-width:480px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,.28);animation:modalIn .3s ease}
@keyframes modalIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:none}}
.proof-modal h3{font-size:18px;font-weight:800;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.proof-modal .sub{font-size:13px;color:var(--muted);margin-bottom:22px;line-height:1.6}
.proof-field{margin-bottom:16px}
.proof-field label{font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px}
.proof-field input,.proof-field textarea{width:100%;padding:11px 14px;border:1.5px solid #e0ddd5;border-radius:10px;font-size:13px;font-family:inherit;outline:none;transition:.2s;background:#fafaf6}
.proof-field input:focus,.proof-field textarea:focus{border-color:var(--accent);background:#fff}
.proof-field input[type=file]{padding:10px 12px;cursor:pointer}
.proof-img-preview{display:none;margin-top:10px;border-radius:10px;overflow:hidden;border:2px solid #e0ddd5}
.proof-img-preview img{width:100%;max-height:180px;object-fit:cover;display:block}
.proof-submit{width:100%;padding:13px;background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer;transition:.25s;margin-top:6px}
.proof-submit:hover{opacity:.88;transform:translateY(-1px)}
.proof-cancel{width:100%;padding:10px;background:#f0ede5;color:var(--muted);border:none;border-radius:12px;font-size:13px;font-weight:700;cursor:pointer;margin-top:8px;transition:.2s}
.proof-cancel:hover{background:#e8e4da}
/* Sidebar .nav-section (seller uses) = .nav-sec for vol */
.nav-section{font-size:9px;color:rgba(255,255,255,.32);font-weight:700;text-transform:uppercase;letter-spacing:.9px;padding:14px 14px 5px;margin-top:4px;display:block;pointer-events:none;user-select:none}
@media(max-width:600px){.task-actions{flex-direction:column}}
</style>
</head>
<body>
<!-- Mobile topbar -->
<div class="mobile-topbar">
  <span class="m-logo">🌿 Adhaar</span>
  <button class="hamburger" id="hamburger" aria-label="Open menu">
    <span></span><span></span><span></span>
  </button>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app">
  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar" id="sidebar">
    <div class="logo">🌿 Adhaar<span class="logo-sub">Volunteer Portal</span></div>

    <button class="nav-btn <?=$tab==='assigned'?'active':''?>" onclick="openTab('assigned')">📦 Assigned Pickups<?php if(count($assigned)>0):?><span class="nav-badge green"><?=count($assigned)?></span><?php endif;?></button>
    <button class="nav-btn <?=$tab==='tasks'?'active':''?>" onclick="openTab('tasks')">📋 Task Requests<?php if(count($pending_tasks)>0):?><span class="nav-badge"><?=count($pending_tasks)?></span><?php endif;?></button>
    <button class="nav-btn <?=$tab==='completed'?'active':''?>" onclick="openTab('completed')">✅ Completed</button>
    <button class="nav-btn <?=$tab==='peers'?'active':''?>"   onclick="openTab('peers')">👥 Peer Volunteers</button>

    <span class="nav-sec">Shop</span>
    <a href="../shop/shop.php"    class="nav-btn">🛍️ Browse Shop</a>
    <a href="../shop/cart.php"    class="nav-btn">🛒 My Cart<?php if($cart_count>0):?><span class="nav-badge"><?=$cart_count?></span><?php endif;?></a>
    <a href="../shop/my_orders.php" class="nav-btn">📦 My Orders<?php if($orders_count>0):?><span class="nav-badge green"><?=$orders_count?></span><?php endif;?></a>

    <span class="nav-sec">Account</span>
    <button class="nav-btn <?=$tab==='profile'?'active':''?>" onclick="openTab('profile')">👤 My Profile</button>

    <div class="sidebar-footer">
      <a href="../auth/logout.php" class="logout-link">⇦ Logout</a>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <main class="main">
    <div class="top-bar">
      <h3>Welcome, <span><?=htmlspecialchars($user['name'])?></span> 👋</h3>
      <a href="../auth/logout.php" class="logout-link-top">Logout</a>
    </div>

    <!-- Stats -->
    <div class="stat-row">
      <div class="stat-chip"><p>Assigned Pickups</p><h2><?=count($assigned)?></h2></div>
      <div class="stat-chip"><p>Completed</p><h2><?=count($completed)?></h2></div>
      <div class="stat-chip"><p>Task Requests</p><h2><?=count($pending_tasks)?></h2></div>
      <div class="stat-chip"><p>Peer Volunteers</p><h2><?=count($peers)?></h2></div>
    </div>

    <!-- ══ TAB: ASSIGNED ══ -->
    <div id="tab-assigned" class="tab-panel <?=$tab==='assigned'?'active':''?>">
      <div class="section-title">📦 Active Pickups &amp; Deliveries</div>
      <?php if(empty($assigned)): ?>
      <div class="empty-state"><span class="emoji">📭</span><p>No assigned pickups right now. Check back soon!</p></div>
      <?php else: ?>
      <div class="donation-grid">
        <?php foreach($assigned as $d):
          $tbl = ($d['type']==='Food') ? 'food_donations' : 'cloth_donations';
          $img = !empty($d['image']) ? image_url($d['image']) : null;
        ?>
        <div class="don-card">
          <?php if($img): ?><img src="<?=htmlspecialchars($img)?>" alt="" class="don-card-img">
          <?php else: ?><div class="don-card-img-ph"><?=$d['type']==='Food'?'🍱':'👕'?></div><?php endif; ?>
          <div class="don-card-body">
            <span class="don-card-type <?=$d['type']==='Food'?'type-food':'type-cloth'?>"><?=$d['type']?></span>
            <h4>Qty: <?=htmlspecialchars($d['quantity'])?></h4>
            <div class="don-card-meta">
              <strong>📍 Address:</strong> <?=htmlspecialchars($d['pickup_address']??'—')?><br>
              <strong>📞 Contact:</strong> <?=htmlspecialchars($d['contact']??'—')?><br>
              <strong>👤 Donor:</strong> <?=htmlspecialchars($d['donor_email'])?><br>
              <strong>Status:</strong> <span class="pill <?=htmlspecialchars($d['status'])?>"><?=ucfirst(str_replace('_',' ',$d['status']))?></span>
            </div>
          </div>
          <div class="don-card-footer">
            <form method="POST" action="../api/update_status.php" style="flex:1">
              <?=csrf_field()?>
              <input type="hidden" name="id"     value="<?=(int)$d['id']?>">
              <input type="hidden" name="table"  value="<?=$tbl?>">
              <input type="hidden" name="status" value="picked_up">
              <button type="submit" class="action-btn btn-pickup">📦 Mark Picked Up</button>
            </form>
            <button type="button" class="action-btn btn-delivered"
              onclick="openProofModal(<?=(int)$d['id']?>, '<?=$tbl?>', '<?=htmlspecialchars(addslashes($d['type']))?>')">
              ✅ Mark Delivered
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ TAB: TASKS ══ -->
    <div id="tab-tasks" class="tab-panel <?=$tab==='tasks'?'active':''?>">
      <div class="section-title">📋 Task Requests from Admin</div>
      <?php if(empty($pending_tasks)): ?>
      <div class="empty-state"><span class="emoji">✅</span><p>No pending task requests.</p></div>
      <?php else: foreach($pending_tasks as $t): ?>
      <div class="task-card">
        <h4>Task #<?=(int)$t['id']?> — <?=ucfirst($t['donation_type'])?> Pickup</h4>
        <div class="task-meta">
          <strong>Type:</strong> <?=ucfirst($t['donation_type'])?> Donation<br>
          <strong>Donation ID:</strong> #<?=(int)$t['donation_id']?><br>
          <strong>Assigned:</strong> <?=date('d M Y · h:i A',strtotime($t['assigned_at']))?><br>
          <?php if($t['notes']): ?><strong>Notes:</strong> <?=htmlspecialchars($t['notes'])?><?php endif; ?>
        </div>
        <div class="task-actions">
          <form method="POST">
            <?=csrf_field()?>
            <input type="hidden" name="task_id"     value="<?=(int)$t['id']?>">
            <input type="hidden" name="task_action" value="accepted">
            <button type="submit" class="btn-accept-task">✓ Accept Task</button>
          </form>
          <form method="POST">
            <?=csrf_field()?>
            <input type="hidden" name="task_id"     value="<?=(int)$t['id']?>">
            <input type="hidden" name="task_action" value="rejected">
            <button type="submit" class="btn-reject-task">✗ Decline</button>
          </form>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

    <!-- ══ TAB: COMPLETED ══ -->
    <div id="tab-completed" class="tab-panel <?=$tab==='completed'?'active':''?>">
      <div class="section-title">✅ Completed Deliveries (<?=count($completed)?>)</div>
      <?php if(empty($completed)): ?>
      <div class="empty-state"><span class="emoji">🏆</span><p>No completed deliveries yet. Keep going!</p></div>
      <?php else: ?>
      <div class="donation-grid">
        <?php foreach($completed as $c): ?>
        <div class="don-card">
          <div class="don-card-img-ph"><?=$c['type']==='Food'?'🍱':'👕'?></div>
          <div class="don-card-body">
            <span class="don-card-type <?=$c['type']==='Food'?'type-food':'type-cloth'?>"><?=$c['type']?></span>
            <h4>Qty: <?=htmlspecialchars($c['quantity'])?></h4>
            <div class="don-card-meta">
              <strong>📍 Address:</strong> <?=htmlspecialchars($c['pickup_address']??'—')?><br>
              <strong>👤 Donor:</strong> <?=htmlspecialchars($c['donor_email'])?><br>
              <strong>Date:</strong> <?=date("d M Y",strtotime($c['created_at']))?><br>
              <strong>Status:</strong> <span class="pill delivered">Delivered ✓</span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ TAB: PEERS ══ -->
    <div id="tab-peers" class="tab-panel <?=$tab==='peers'?'active':''?>">
      <div class="section-title">👥 Fellow Volunteers (<?=count($peers)?>)</div>
      <?php if(empty($peers)): ?>
      <div class="empty-state"><span class="emoji">👥</span><p>No other volunteers yet.</p></div>
      <?php else: ?>
      <div class="peer-grid">
        <?php foreach($peers as $pv): ?>
        <div class="peer-card">
          <div class="peer-avatar"><?=strtoupper(substr($pv['name'],0,1))?></div>
          <div class="peer-name"><?=htmlspecialchars($pv['name'])?></div>
          <div class="peer-meta">
            📧 <?=htmlspecialchars($pv['email'])?><br>
            <?php if($pv['mobile']): ?>📞 <?=htmlspecialchars($pv['mobile'])?><br><?php endif; ?>
            <?php if($pv['address']): ?>📍 <?=htmlspecialchars(mb_substr($pv['address'],0,60))?><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ TAB: PROFILE ══ -->
    <div id="tab-profile" class="tab-panel <?=$tab==='profile'?'active':''?>">
      <div class="section-title">👤 My Profile</div>
      <div class="profile-card">
        <div class="profile-avatar"><?=strtoupper(substr($user['name'],0,1))?></div>
        <div class="profile-row"><label>Full Name</label><p><?=htmlspecialchars($user['name'])?></p></div>
        <div class="profile-row"><label>Email</label><p><?=htmlspecialchars($user['email'])?></p></div>
        <div class="profile-row"><label>Mobile</label><p><?=htmlspecialchars($user['mobile']??'—')?></p></div>
        <?php if($user['address']): ?><div class="profile-row"><label>Address</label><p><?=htmlspecialchars($user['address'])?></p></div><?php endif; ?>
        <?php if($user['volunteer_reason']): ?><div class="profile-row"><label>Why I Volunteer</label><p><?=htmlspecialchars($user['volunteer_reason'])?></p></div><?php endif; ?>
        <a href="../donor/edit_profile.php" style="display:inline-block;margin-top:16px;padding:11px 24px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border-radius:10px;font-weight:700;font-size:14px;text-decoration:none">Edit Profile</a>
      </div>
    </div>

  </main>
</div>

<div id="dashToast"></div>

<!-- ══ DELIVERY PROOF MODAL ══ -->
<div class="proof-modal-overlay" id="proofModalOverlay">
  <div class="proof-modal">
    <h3>✅ Mark as Delivered</h3>
    <p class="sub">Upload a photo as proof of delivery before confirming. The donor will receive an email with this photo. 📧</p>
    <form method="POST" action="../api/update_status.php" enctype="multipart/form-data" id="proofForm">
      <?=csrf_field()?>
      <input type="hidden" name="id"     id="proofDonId">
      <input type="hidden" name="table"  id="proofTable">
      <input type="hidden" name="status" value="delivered">

      <div class="proof-field">
        <label>📸 Delivery Proof Photo *</label>
        <input type="file" name="delivery_proof" id="proofFileInput" accept="image/*" required
               onchange="previewProofImg(this)">
        <div class="proof-img-preview" id="proofImgPreview">
          <img id="proofImgThumb" src="" alt="Proof preview">
        </div>
      </div>

      <div class="proof-field">
        <label>👥 Number of Beneficiaries (optional)</label>
        <input type="number" name="beneficiary_count" min="1" max="9999"
               placeholder="e.g. 5 families received this donation">
      </div>

      <div class="proof-field">
        <label>📝 Delivery Note (optional)</label>
        <textarea name="delivery_note" rows="2"
                  placeholder="e.g. Delivered to community centre, received by Mr. Sharma…"></textarea>
      </div>

      <div id="proofTypeLabel" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:11px 14px;font-size:13px;font-weight:600;color:#065f46;margin-bottom:16px">
        📦 Donation type will appear here
      </div>

      <button type="submit" class="proof-submit">✅ Confirm Delivery &amp; Notify Donor</button>
      <button type="button" class="proof-cancel" onclick="closeProofModal()">Cancel</button>
    </form>
  </div>
</div>

<script src="../js/dashboard.js"></script>
<script src="../js/ai_chat.js"></script>
<script>
function openProofModal(id, table, type) {
  document.getElementById('proofDonId').value  = id;
  document.getElementById('proofTable').value  = table;
  document.getElementById('proofTypeLabel').textContent = '📦 ' + type + ' Donation — ID #' + id;
  // Reset state
  document.getElementById('proofFileInput').value = '';
  document.getElementById('proofImgPreview').style.display = 'none';
  document.getElementById('proofImgThumb').src = '';
  document.getElementById('proofModalOverlay').classList.add('open');
}
function closeProofModal() {
  document.getElementById('proofModalOverlay').classList.remove('open');
}
function previewProofImg(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('proofImgThumb').src = e.target.result;
      document.getElementById('proofImgPreview').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
  }
}
// Close on backdrop click
document.getElementById('proofModalOverlay').addEventListener('click', function(e){
  if (e.target === this) closeProofModal();
});
</script>
</body>
</html>
