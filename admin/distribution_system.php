<?php
require_once __DIR__ . '/../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['admin_id'])) { header("Location: admin_login.php"); exit; }

$csrf = csrf_field();
$priority   = trim($_GET['priority']   ?? '');
$cloth_type = trim($_GET['cloth_type'] ?? '');
$tab        = ($_GET['tab'] ?? 'food') === 'cloth' ? 'cloth' : 'food';

$vols = $conn->query("SELECT name,email FROM register WHERE role='volunteer' AND verified=1 ORDER BY name");
$volunteers = $vols->fetch_all(MYSQLI_ASSOC);

function statCount($conn,$table,$status){$r=$conn->query("SELECT COUNT(*) c FROM $table WHERE status='$status'");return(int)$r->fetch_assoc()['c'];}
function statCountToday($conn,$table,$status){$r=$conn->query("SELECT COUNT(*) c FROM $table WHERE status='$status' AND DATE(created_at)=CURDATE()");return(int)$r->fetch_assoc()['c'];}

$stats=['pending'=>statCount($conn,'food_donations','pending')+statCount($conn,'cloth_donations','pending'),'scheduled'=>statCountToday($conn,'food_donations','scheduled')+statCountToday($conn,'cloth_donations','scheduled'),'out_pickup'=>statCount($conn,'food_donations','out_for_pickup')+statCount($conn,'cloth_donations','out_for_pickup'),'delivered'=>statCountToday($conn,'food_donations','delivered')+statCountToday($conn,'cloth_donations','delivered')];

$stages=['pending','accepted','scheduled','out_for_pickup','picked_up'];

function fetchStage($conn,$table,$stage,$extra=''){$sql="SELECT * FROM $table WHERE status=? $extra ORDER BY created_at DESC";$s=$conn->prepare($sql);$s->bind_param("s",$stage);$s->execute();return $s->get_result()->fetch_all(MYSQLI_ASSOC);}

$fw=$priority   ? " AND priority='".mysqli_real_escape_string($conn,$priority)."'"   : '';
$cw=$cloth_type ? " AND cloth_type='".mysqli_real_escape_string($conn,$cloth_type)."'" : '';
$food_data=$cloth_data=[];
foreach($stages as $st){$food_data[$st]=fetchStage($conn,'food_donations',$st,$fw);$cloth_data[$st]=fetchStage($conn,'cloth_donations',$st,$cw);}

$ct_res=$conn->query("SELECT DISTINCT cloth_type FROM cloth_donations WHERE cloth_type IS NOT NULL AND cloth_type!='' ORDER BY cloth_type");
$cloth_types=$ct_res->fetch_all(MYSQLI_ASSOC);

if(isset($_GET['export'])){
  $exp=($_GET['export']==='cloth')?'cloth_donations':'food_donations';
  $rows=$conn->query("SELECT * FROM $exp ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="'.$exp.'_export.csv"');
  if(!empty($rows)){echo implode(',',array_keys($rows[0]))."\n";foreach($rows as $row){echo implode(',',array_map(fn($v)=>'"'.str_replace('"','""',$v).'"',$row))."\n";}}
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Distribution System | Adhaar Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css">
<style>
/* Distribution-specific extras */
.page-sub{color:var(--muted);font-size:14px;margin-top:4px;margin-bottom:24px}
.stat-card{background:var(--card);border-radius:16px;padding:20px 22px;box-shadow:var(--shadow);border-top:4px solid var(--accent2)}
.stat-card p{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:6px}
.stat-card h2{font-size:30px;font-weight:800}
.stat-card.hl{background:linear-gradient(135deg,var(--accent),var(--accent2));border-top:none}
.stat-card.hl p,.stat-card.hl h2{color:#fff}
.toolbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:var(--card);padding:16px 20px;border-radius:14px;box-shadow:var(--shadow);margin-bottom:24px}
.toolbar select{padding:8px 14px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;color:var(--text);background:#fff;outline:none}
.toolbar select:focus{border-color:var(--accent)}
.export-btn{margin-left:auto;padding:9px 18px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border-radius:10px;font-size:13px;font-weight:700;text-decoration:none;white-space:nowrap}
.main-tabs{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.main-tab{padding:11px 26px;border-radius:10px;border:1.5px solid #ddd;background:#fff;font-size:14px;font-weight:600;color:var(--muted);text-decoration:none;transition:.25s}
.main-tab.active{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(122,125,63,.3)}
.main-tab:hover:not(.active){border-color:var(--accent);color:var(--accent)}
/* Kanban */
.kanban{display:flex;gap:16px;overflow-x:auto;padding-bottom:16px;-webkit-overflow-scrolling:touch}
.kanban-col{min-width:260px;flex:1;background:var(--card);border-radius:16px;box-shadow:var(--shadow);overflow:hidden}
.kanban-col-head{padding:14px 18px;font-size:13px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.kanban-col-head .count{background:rgba(255,255,255,.35);padding:2px 9px;border-radius:20px;font-size:12px;font-weight:700}
.col-pending .kanban-col-head{background:#fef3c7;color:#92400e}
.col-accepted .kanban-col-head{background:#dbeafe;color:#1e40af}
.col-scheduled .kanban-col-head{background:#ede9fe;color:#5b21b6}
.col-out .kanban-col-head{background:#fce7f3;color:#9d174d}
.col-picked .kanban-col-head{background:#d1fae5;color:#065f46}
.kanban-cards{padding:12px;display:flex;flex-direction:column;gap:10px;min-height:80px}
.don-card{background:#fafaf6;border-radius:12px;overflow:hidden;border:1px solid #ede9df;transition:.25s}
.don-card:hover{box-shadow:0 6px 20px rgba(122,125,63,.12);transform:translateY(-2px)}
.don-card-thumb{width:100%;height:100px;object-fit:cover;display:block;background:#f0ede5}
.don-card-thumb-ph{width:100%;height:100px;background:linear-gradient(135deg,#f0ede5,#e8e4d8);display:flex;align-items:center;justify-content:center;font-size:28px}
.don-card-info{padding:12px 14px}
.don-card-info .label{font-size:11px;color:var(--muted);font-weight:600}
.don-card-info .val{font-size:13px;font-weight:600;color:var(--text);margin-bottom:5px}
.don-card-info .addr{font-size:12px;color:var(--muted);line-height:1.5}
.don-card-actions{padding:10px 14px;border-top:1px solid #ede9df;display:flex;flex-direction:column;gap:7px}
.don-card-actions form{display:flex;gap:6px;flex-wrap:wrap}
.act-btn{flex:1;padding:7px 10px;border-radius:8px;border:none;font-size:12px;font-weight:700;cursor:pointer;transition:.2s;white-space:nowrap}
.act-accept{background:#dbeafe;color:#1e40af}.act-accept:hover{background:#bfdbfe}
.act-reject{background:#fee2e2;color:#991b1b}.act-reject:hover{background:#fecaca}
.act-out{background:#fce7f3;color:#9d174d}.act-out:hover{background:#fbcfe8}
.act-pickup{background:#fef3c7;color:#92400e}.act-pickup:hover{background:#fde68a}
.vol-select{width:100%;padding:6px 10px;border:1.5px solid #ddd;border-radius:8px;font-size:12px;font-family:inherit;color:var(--text);background:#fff}
.assign-btn{width:100%;padding:7px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer}
.date-input{width:100%;padding:6px 10px;border:1.5px solid #ddd;border-radius:8px;font-size:12px;margin-bottom:6px}
.pri{display:inline-block;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;text-transform:uppercase}
.pri.high{background:#fee2e2;color:#991b1b}.pri.medium{background:#fef3c7;color:#92400e}.pri.low{background:#d1fae5;color:#065f46}
.empty-col{text-align:center;padding:20px;color:var(--muted);font-size:13px}
@media(max-width:900px){.kanban{flex-direction:column}.kanban-col{min-width:unset}}
</style>
</head>
<body>
<!-- Mobile topbar -->
<div class="mobile-topbar">
  <span class="m-logo">🚚 Distribution</span>
  <button class="hamburger" id="hamburger" aria-label="Open menu">
    <span></span><span></span><span></span>
  </button>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="logo">🌿 Adhaar<span class="logo-sub">Admin Panel</span></div>
    <a href="admin_dashboard.php"     class="nav-btn">🏠 Dashboard</a>
    <a href="distribution_system.php" class="nav-btn active">🚚 Distribution</a>
    <div class="sidebar-footer">
      <a href="../auth/logout.php" class="logout-link">⇦ Logout</a>
    </div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h2>Distribution Management</h2>
      <span class="admin-badge">Admin</span>
    </div>

    <div class="stat-row">
      <div class="stat-card hl"><p>Total Pending</p><h2><?=$stats['pending']?></h2></div>
      <div class="stat-card"><p>Scheduled Today</p><h2><?=$stats['scheduled']?></h2></div>
      <div class="stat-card"><p>Out for Pickup</p><h2><?=$stats['out_pickup']?></h2></div>
      <div class="stat-card"><p>Delivered Today</p><h2><?=$stats['delivered']?></h2></div>
    </div>

    <div class="main-tabs">
      <a href="?tab=food"  class="main-tab <?=$tab==='food' ?'active':''?>">🍱 Food Distribution</a>
      <a href="?tab=cloth" class="main-tab <?=$tab==='cloth'?'active':''?>">👕 Cloth Distribution</a>
    </div>

    <div class="toolbar">
      <?php if($tab==='food'): ?>
      <form method="GET" style="display:contents"><input type="hidden" name="tab" value="food">
        <select name="priority" onchange="this.form.submit()">
          <option value="">All Priorities</option>
          <option value="high"   <?=$priority==='high'  ?'selected':''?>>🔴 High</option>
          <option value="medium" <?=$priority==='medium'?'selected':''?>>🟡 Medium</option>
          <option value="low"    <?=$priority==='low'   ?'selected':''?>>🟢 Low</option>
        </select>
      </form>
      <?php else: ?>
      <form method="GET" style="display:contents"><input type="hidden" name="tab" value="cloth">
        <select name="cloth_type" onchange="this.form.submit()">
          <option value="">All Cloth Types</option>
          <?php foreach($cloth_types as $ct): ?>
          <option value="<?=htmlspecialchars($ct['cloth_type'])?>" <?=$cloth_type===$ct['cloth_type']?'selected':''?>><?=htmlspecialchars($ct['cloth_type'])?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <?php endif; ?>
      <a href="?tab=<?=$tab?>&export=<?=$tab?>" class="export-btn">⬇ Export CSV</a>
    </div>

    <?php
    $data      =($tab==='food')?$food_data:$cloth_data;
    $tbl_name  =($tab==='food')?'food_donations':'cloth_donations';
    $emoji     =($tab==='food')?'🍱':'👕';
    $col_meta  =['pending'=>['label'=>'Pending','class'=>'col-pending'],'accepted'=>['label'=>'Accepted','class'=>'col-accepted'],'scheduled'=>['label'=>'Scheduled','class'=>'col-scheduled'],'out_for_pickup'=>['label'=>'Out for Pickup','class'=>'col-out'],'picked_up'=>['label'=>'Picked Up','class'=>'col-picked']];
    ?>
    <div class="kanban">
    <?php foreach($col_meta as $stage=>$meta): ?>
      <div class="kanban-col <?=$meta['class']?>">
        <div class="kanban-col-head"><?=$meta['label']?><span class="count"><?=count($data[$stage])?></span></div>
        <div class="kanban-cards">
          <?php if(empty($data[$stage])): ?><div class="empty-col">No items</div><?php endif; ?>
          <?php foreach($data[$stage] as $d):$imgSrc=!empty($d['image'])?htmlspecialchars($d['image']):''; ?>
          <div class="don-card">
            <?php if($imgSrc): ?><img src="../<?=$imgSrc?>" class="don-card-thumb" alt="">
            <?php else: ?><div class="don-card-thumb-ph"><?=$emoji?></div><?php endif; ?>
            <div class="don-card-info">
              <div class="label">Donor</div><div class="val"><?=htmlspecialchars($d['donor_email'])?></div>
              <div class="label">Quantity</div><div class="val"><?=htmlspecialchars($d['quantity'])?></div>
              <?php if($tab==='food'&&!empty($d['priority'])): ?><span class="pri <?=$d['priority']?>"><?=$d['priority']?></span><?php endif; ?>
              <?php if($tab==='cloth'&&!empty($d['cloth_type'])): ?><div class="val" style="font-size:12px;color:var(--muted)"><?=htmlspecialchars($d['cloth_type'])?></div><?php endif; ?>
              <div class="addr"><?=htmlspecialchars($d['pickup_address']??'—')?></div>
              <div class="addr" style="margin-top:3px">📞 <?=htmlspecialchars($d['contact']??'—')?></div>
              <div class="addr" style="margin-top:3px;color:#9a8f5c"><?=date("d M Y",strtotime($d['created_at']))?></div>
            </div>
            <div class="don-card-actions">
              <?php if($stage==='pending'): ?>
              <form method="POST" action="../api/update_status.php"><?=$csrf?><input type="hidden" name="id" value="<?=$d['id']?>"><input type="hidden" name="table" value="<?=$tbl_name?>"><input type="hidden" name="status" value="accepted"><button class="act-btn act-accept">✓ Accept</button></form>
              <form method="POST" action="../api/update_status.php"><?=$csrf?><input type="hidden" name="id" value="<?=$d['id']?>"><input type="hidden" name="table" value="<?=$tbl_name?>"><input type="hidden" name="status" value="rejected"><button class="act-btn act-reject">✗ Reject</button></form>
              <?php elseif($stage==='accepted'): ?>
              <form method="POST" action="../api/update_status.php"><?=$csrf?><input type="hidden" name="id" value="<?=$d['id']?>"><input type="hidden" name="table" value="<?=$tbl_name?>"><input type="hidden" name="status" value="scheduled"><input type="date" name="pickup_date" required class="date-input"><input type="time" name="pickup_time" required class="date-input"><select name="volunteer_email" class="vol-select" style="margin-bottom:6px"><option value="">Assign Volunteer</option><?php foreach($volunteers as $v): ?><option value="<?=htmlspecialchars($v['email'])?>"><?=htmlspecialchars($v['name'])?></option><?php endforeach; ?></select><button class="assign-btn">📅 Schedule</button></form>
              <?php elseif($stage==='scheduled'): ?>
              <form method="POST" action="../api/update_status.php"><?=$csrf?><input type="hidden" name="id" value="<?=$d['id']?>"><input type="hidden" name="table" value="<?=$tbl_name?>"><input type="hidden" name="status" value="out_for_pickup"><button class="act-btn act-out">🚚 Out for Pickup</button></form>
              <?php elseif($stage==='out_for_pickup'): ?>
              <form method="POST" action="../api/update_status.php"><?=$csrf?><input type="hidden" name="id" value="<?=$d['id']?>"><input type="hidden" name="table" value="<?=$tbl_name?>"><input type="hidden" name="status" value="picked_up"><button class="act-btn act-pickup">📦 Mark Picked Up</button></form>
              <?php elseif($stage==='picked_up'): ?>
              <form method="POST" action="../api/update_status.php"><?=$csrf?><input type="hidden" name="id" value="<?=$d['id']?>"><input type="hidden" name="table" value="<?=$tbl_name?>"><input type="hidden" name="status" value="delivered"><button class="act-btn" style="background:#d1fae5;color:#065f46">✅ Delivered</button></form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </main>
</div>
<div id="dashToast"></div>
<script src="../js/dashboard.js"></script>
</body>
</html>
