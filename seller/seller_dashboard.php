<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
if (!isset($_SESSION['user_email']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../auth/login.php"); exit;
}
$email = $_SESSION['user_email'];
$uq = $conn->prepare("SELECT name FROM register WHERE email=? AND role='seller' AND verified=1");
$uq->bind_param("s",$email); $uq->execute();
$user = $uq->get_result()->fetch_assoc();
if (!$user) { header("Location: ../auth/login.php"); exit; }

$sq = $conn->prepare("SELECT * FROM seller_stores WHERE seller_email=?");
$sq->bind_param("s",$email); $sq->execute();
$store = $sq->get_result()->fetch_assoc();

$total_products = $total_orders = $pending_orders = 0;
$total_revenue  = 0.0;
if ($store) {
    $se = mysqli_real_escape_string($conn,$email);
    $total_products = (int)$conn->query("SELECT COUNT(*) c FROM products WHERE seller_email='$se' AND is_active=1")->fetch_assoc()['c'];
    $total_orders   = (int)$conn->query("SELECT COUNT(*) c FROM orders WHERE seller_email='$se'")->fetch_assoc()['c'];
    $total_revenue  = (float)$conn->query("SELECT COALESCE(SUM(total_amount),0) r FROM orders WHERE seller_email='$se' AND order_status NOT IN ('cancelled','returned')")->fetch_assoc()['r'];
    $pending_orders = (int)$conn->query("SELECT COUNT(*) c FROM orders WHERE seller_email='$se' AND order_status='placed'")->fetch_assoc()['c'];
}

$pq = $conn->prepare("SELECT p.*,(SELECT COUNT(*) FROM product_reviews WHERE product_id=p.id) rev_count FROM products p WHERE p.seller_email=? ORDER BY p.created_at DESC");
$pq->bind_param("s",$email); $pq->execute();
$products = $pq->get_result()->fetch_all(MYSQLI_ASSOC);

$oq = $conn->prepare("SELECT o.*, GROUP_CONCAT(oi.product_name SEPARATOR ', ') AS items FROM orders o LEFT JOIN order_items oi ON oi.order_id=o.id WHERE o.seller_email=? GROUP BY o.id ORDER BY o.created_at DESC LIMIT 50");
$oq->bind_param("s",$email); $oq->execute();
$orders = $oq->get_result()->fetch_all(MYSQLI_ASSOC);

$tab     = $_GET['tab']     ?? 'overview';
$success = $_GET['success'] ?? '';
$err     = $_GET['err']     ?? '';

require_once __DIR__ . '/../api/ai_engine.php';
$ai_recommendations = adhaar_ai()->getSellerRecommendations($email);
$ai_product_recs = adhaar_ai()->getProductRecommendations($email, 0, 4);

$cats = ['handicraft'=>'Handicraft','textile'=>'Textile','food_product'=>'Food Product',
         'jewelry'=>'Jewelry','art'=>'Art','pottery'=>'Pottery','organic'=>'Organic','other'=>'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Seller Dashboard | Adhaar</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../css/dashboard.css">
<style>
/* ── Seller-specific styles ── */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
.stat{background:var(--card);padding:22px;border-radius:var(--radius);box-shadow:var(--shadow);border-left:4px solid var(--accent);transition:.3s}
.stat:hover{transform:translateY(-3px);box-shadow:var(--shadow-lg)}
.stat .s-l{font-size:11px;color:var(--muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px}
.stat .s-v{font-size:28px;font-weight:800}
.stat.warn{border-color:#f59e0b}.stat.warn .s-v{color:#d97706}
.form-card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:32px}
.form-card h3{font-size:18px;font-weight:800;margin-bottom:6px}
.fc-sub{font-size:13px;color:var(--muted);margin-bottom:24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.form-grid.one{grid-template-columns:1fr}
.field{margin-bottom:0}
.field label{display:block;font-size:12px;font-weight:700;color:var(--muted);margin-bottom:6px;text-transform:uppercase;letter-spacing:.3px}
.field input,.field select,.field textarea{width:100%;padding:11px 14px;border:1.5px solid #e0ddd5;border-radius:10px;font-size:14px;font-family:inherit;color:var(--text);background:#fafaf6;transition:.2s}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent);outline:none;background:#fff;box-shadow:0 0 0 3px rgba(122,125,63,.1)}
.field textarea{resize:vertical;min-height:90px}
.field.full{grid-column:1/-1}
.form-btn{padding:11px 24px;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:.25s;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;box-shadow:0 4px 14px rgba(122,125,63,.3)}
.form-btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(122,125,63,.4)}
.btn-sm{padding:7px 14px;font-size:12px;border-radius:8px;border:none;cursor:pointer;font-weight:700;transition:.25s}
.btn-warning{background:#fef3c7;color:#92400e}.btn-warning:hover{background:#fde68a}
.btn-success{background:#d1fae5;color:#065f46}.btn-success:hover{background:#a7f3d0}
.btn-primary-sm{background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff}
/* Setup hero */
.setup-hero{background:linear-gradient(135deg,var(--accent),var(--accent2));border-radius:var(--radius);padding:24px 28px;color:#fff;margin-bottom:24px}
.setup-hero h3{font-size:19px;font-weight:800;margin-bottom:6px}
.setup-hero p{font-size:13px;opacity:.9;max-width:500px}
/* No-store banner */
.no-store-banner{background:#fef3c7;border:1.5px solid #fde68a;border-radius:14px;padding:18px 22px;margin-bottom:24px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.no-store-banner .icon{font-size:28px}
.no-store-banner h4{font-size:14px;font-weight:700;color:#92400e;margin-bottom:2px}
.no-store-banner p{font-size:13px;color:#78350f}
/* Product grid */
.product-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:16px;margin-top:20px}
.prod-card{background:var(--card);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);transition:.3s;border:1px solid #ede9df}
.prod-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg)}
.prod-img{width:100%;height:140px;object-fit:cover;background:#f0ede5}
.prod-img-ph{width:100%;height:140px;background:linear-gradient(135deg,#f0ede5,#e8e4d8);display:flex;align-items:center;justify-content:center;font-size:36px}
.prod-body{padding:14px}
.prod-name{font-size:14px;font-weight:700;margin-bottom:4px}
.prod-price{font-size:16px;font-weight:800;color:var(--accent);margin-bottom:6px}
.prod-mrp{font-size:12px;color:var(--muted);text-decoration:line-through;margin-left:6px;font-weight:400}
.prod-meta{font-size:12px;color:var(--muted);margin-bottom:10px}
.prod-actions{display:flex;gap:8px}
/* Overview grid */
.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:24px}
.info-tile{background:var(--card);border-radius:12px;padding:18px;box-shadow:var(--shadow);border-top:3px solid var(--accent)}
.info-tile .ti{font-size:24px;margin-bottom:8px}
.info-tile h4{font-size:14px;font-weight:700;margin-bottom:4px}
.info-tile p{font-size:12px;color:var(--muted)}
/* topbar in main */
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.top-bar h2{font-size:20px;font-weight:800}.top-bar h2 span{color:var(--accent)}
/* nav-section = nav-sec in seller */
.nav-section{font-size:9px;color:rgba(255,255,255,.32);font-weight:700;text-transform:uppercase;letter-spacing:.9px;padding:14px 14px 5px;margin-top:4px;display:block;pointer-events:none;user-select:none}
@media(max-width:1100px){.stats{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.stats{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<!-- Mobile topbar -->
<div class="mobile-topbar">
  <span class="m-logo">🏪 Seller Hub</span>
  <button class="hamburger" id="hamburger" aria-label="Open menu">
    <span></span><span></span><span></span>
  </button>
</div>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app">
  <!-- ══ SIDEBAR ══ -->
  <aside class="sidebar" id="sidebar">
    <div class="logo">🏪 Seller Hub<span class="logo-sub">Adhaar – The SoulServe</span></div>

    <button class="nav-btn <?=$tab==='overview'?'active':''?>"     onclick="goTab('overview')">📊 Overview</button>
    <button class="nav-btn <?=$tab==='store'?'active':''?>"        onclick="goTab('store')">🏬 My Store</button>

    <span class="nav-section">Products</span>
    <button class="nav-btn <?=$tab==='add_product'?'active':''?>"  onclick="goTab('add_product')">➕ Add Product</button>
    <button class="nav-btn <?=$tab==='products'?'active':''?>"     onclick="goTab('products')">📦 My Products<?php if(count($products)>0):?><span class="nav-badge green"><?=count($products)?></span><?php endif;?></button>

    <span class="nav-section">Business</span>
    <button class="nav-btn <?=$tab==='orders'?'active':''?>"       onclick="goTab('orders')">🛒 Orders<?php if($pending_orders>0):?><span class="nav-badge"><?=$pending_orders?></span><?php endif;?></button>
    <button class="nav-btn <?=$tab==='bank'?'active':''?>"         onclick="goTab('bank')">🏦 Bank Details</button>

    <span class="nav-section">Community</span>
    <a href="../shop/shop.php" class="nav-btn">🛍️ View Shop</a>

    <div class="sidebar-footer">
      <a href="../auth/logout.php" class="logout-link">⇦ Logout</a>
    </div>
  </aside>

  <!-- ══ MAIN ══ -->
  <main class="main">
    <div class="top-bar">
      <h2>Welcome, <span><?=htmlspecialchars($user['name'])?></span> 👋</h2>
      <span class="badge">🏪 Seller</span>
    </div>

    <?php if($success): ?><div class="alert alert-success">✅ <?=htmlspecialchars($success)?></div><?php endif; ?>
    <?php if($err):     ?><div class="alert alert-error">⚠ <?=htmlspecialchars($err)?></div><?php endif; ?>

    <?php if(!$store): ?>
    <div class="no-store-banner">
      <span class="icon">⚠️</span>
      <div>
        <h4>Set up your store first</h4>
        <p>Complete your store profile before adding products.</p>
      </div>
      <button class="btn-sm btn-primary-sm" style="margin-left:auto" onclick="goTab('store')">Set Up Store →</button>
    </div>
    <?php endif; ?>

    <!-- ══ OVERVIEW ══ -->
    <div id="tab-overview" class="tab-panel <?=$tab==='overview'?'active':''?>">
      <div class="stats">
        <div class="stat"><div class="s-l">Products</div><div class="s-v"><?=$total_products?></div></div>
        <div class="stat"><div class="s-l">Total Orders</div><div class="s-v"><?=$total_orders?></div></div>
        <div class="stat"><div class="s-l">Revenue</div><div class="s-v">₹<?=number_format($total_revenue,0)?></div></div>
        <div class="stat warn"><div class="s-l">Pending Orders</div><div class="s-v"><?=$pending_orders?></div></div>
      </div>

      <div class="setup-hero">
        <h3>🌿 Empowering You to Sell</h3>
        <p>Adhaar Shop connects your handmade, organic, and local products directly to buyers across India. No middlemen. Pure impact.</p>
      </div>

      <div class="card" style="margin-bottom:24px; background:linear-gradient(135deg,#0d2338,#006d77); color:#fff; border:none;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:12px;">
          <div>
            <div style="font-size:11px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; opacity:.8;">🤖 AI Seller Insights</div>
            <h3 style="font-size:18px; font-weight:800; margin-top:6px;">Smart growth recommendations</h3>
          </div>
          <span style="padding:6px 12px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); border-radius:999px; font-size:11px; font-weight:700;">Live</span>
        </div>
        <div style="display:grid; gap:10px;">
          <?php foreach($ai_recommendations as $rec): ?>
            <div style="display:flex; align-items:flex-start; gap:12px; padding:12px 14px; border-radius:12px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);">
              <span style="font-size:1.2rem; flex-shrink:0;"><?=$rec['icon']?></span>
              <span style="font-size:13px; line-height:1.6; color:rgba(255,255,255,.9);"><?=$rec['text']?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if(!empty($ai_product_recs)): ?>
      <div style="margin-bottom:22px;">
        <div class="section-title">✨ AI Recommended Products</div>
        <div class="product-grid">
          <?php foreach($ai_product_recs as $p): $img = !empty($p['image1']) ? image_url($p['image1']) : null; ?>
            <div class="prod-card" onclick="location.href='../shop/product.php?id=<?=(int)$p['id']?>'">
              <?php if($img): ?><img class="prod-img" src="<?=htmlspecialchars($img)?>" alt="<?=htmlspecialchars($p['name'])?>">
              <?php else: ?><div class="prod-img-ph">🛍️</div><?php endif; ?>
              <div class="prod-body">
                <div class="prod-name"><?=htmlspecialchars($p['name'])?></div>
                <div class="prod-price">₹<?=number_format((float)$p['price'],2)?> <span class="prod-mrp">₹<?=number_format((float)$p['mrp'],2)?></span></div>
                <div class="prod-meta">📍 <?=htmlspecialchars($p['store_name'])?> · ⭐ <?=number_format((float)($p['avg_rating'] ?? 0), 1)?> / 5</div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="section-title">📋 What You Can Do</div>
      <div class="info-grid">
        <?php foreach([
          ['🏬','Build Your Store','Create a branded storefront with logo and description'],
          ['📸','Add Products','Upload photos, set price, stock, and category'],
          ['🛒','Manage Orders','Confirm, ship, and track orders from buyers'],
          ['💰','Earn Revenue','Get paid directly via UPI or bank transfer'],
          ['⭐','Get Reviews','Build credibility through verified buyer reviews'],
          ['📦','Handle Returns','Manage return requests with a clear process'],
        ] as $pt): ?>
        <div class="info-tile"><div class="ti"><?=$pt[0]?></div><h4><?=$pt[1]?></h4><p><?=$pt[2]?></p></div>
        <?php endforeach; ?>
      </div>

      <?php if(!empty($orders)): ?>
      <div class="section-title" style="margin-top:8px">🕐 Recent Orders</div>
      <div class="table-wrap"><div style="overflow-x:auto"><table>
        <thead><tr><th>Order #</th><th>Buyer</th><th>Items</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach(array_slice($orders,0,5) as $o): ?>
        <tr>
          <td><strong><?=htmlspecialchars($o['order_number'])?></strong></td>
          <td style="font-size:12px"><?=htmlspecialchars($o['buyer_email'])?></td>
          <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($o['items']??'—')?></td>
          <td><strong>₹<?=number_format($o['total_amount'],2)?></strong></td>
          <td><span class="pill <?=htmlspecialchars($o['order_status'])?>"><?=ucfirst(str_replace('_',' ',$o['order_status']))?></span></td>
          <td style="white-space:nowrap"><?=date('d M Y',strtotime($o['created_at']))?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div></div>
      <?php endif; ?>
    </div>

    <!-- ══ STORE SETUP ══ -->
    <div id="tab-store" class="tab-panel <?=$tab==='store'?'active':''?>">
      <div class="section-title">🏬 My Store Profile</div>
      <div class="form-card">
        <h3><?=$store ? 'Update Store' : 'Set Up Your Store'?></h3>
        <p class="fc-sub">This is how customers see your brand on Adhaar Shop.</p>
        <form method="POST" action="../api/seller_setup.php" enctype="multipart/form-data">
          <?=csrf_field()?>
          <div class="form-grid">
            <div class="field"><label>Store Name *</label><input type="text" name="store_name" value="<?=htmlspecialchars($store['store_name']??'')?>" required placeholder="e.g. Priya's Handlooms"></div>
            <div class="field"><label>Category *</label>
              <select name="store_category" required>
                <?php foreach($cats as $v=>$l): ?><option value="<?=$v?>" <?=($store['store_category']??'')===$v?'selected':''?>><?=$l?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field full"><label>Tagline</label><input type="text" name="store_tagline" value="<?=htmlspecialchars($store['store_tagline']??'')?>" placeholder="A short catchy line about your store"></div>
            <div class="field full"><label>Store Description *</label><textarea name="store_description" required placeholder="Tell customers your story..."><?=htmlspecialchars($store['store_description']??'')?></textarea></div>
            <div class="field"><label>Store Logo</label><input type="file" name="store_logo" accept="image/*"><?php if(!empty($store['store_logo'])): ?><div style="font-size:11px;color:var(--muted);margin-top:5px">Current: <?=basename($store['store_logo'])?></div><?php endif; ?></div>
            <div class="field"><label>Store Banner</label><input type="file" name="store_banner" accept="image/*"></div>
            <div class="field"><label>WhatsApp</label><input type="tel" name="whatsapp" value="<?=htmlspecialchars($store['whatsapp']??'')?>" placeholder="For order inquiries"></div>
            <div class="field"><label>Village / Town</label><input type="text" name="village" value="<?=htmlspecialchars($store['village']??'')?>" placeholder="Village or town"></div>
            <div class="field"><label>District</label><input type="text" name="district" value="<?=htmlspecialchars($store['district']??'')?>" placeholder="District"></div>
            <div class="field"><label>State</label><input type="text" name="state" value="<?=htmlspecialchars($store['state']??'')?>" placeholder="State"></div>
          </div>
          <button type="submit" class="form-btn">💾 <?=$store?'Update Store':'Create Store'?> →</button>
        </form>
      </div>
    </div>

    <!-- ══ ADD PRODUCT ══ -->
    <div id="tab-add_product" class="tab-panel <?=$tab==='add_product'?'active':''?>">
      <div class="section-title">➕ Add New Product</div>
      <?php if(!$store): ?>
        <div class="alert alert-error">⚠ Please set up your store before adding products.</div>
      <?php else: ?>
      <div class="form-card">
        <h3>New Product Listing</h3>
        <p class="fc-sub">Clear photos and honest descriptions sell better.</p>
        <form method="POST" action="../api/add_product.php" enctype="multipart/form-data">
          <?=csrf_field()?>
          <div class="form-grid">
            <div class="field full"><label>Product Name *</label><input type="text" name="name" required placeholder="e.g. Hand-woven Cotton Saree"></div>
            <div class="field full"><label>Description *</label><textarea name="description" required placeholder="Describe the product: material, size, how it's made..."></textarea></div>
            <div class="field"><label>Category *</label>
              <select name="category" required><?php foreach($cats as $v=>$l): ?><option value="<?=$v?>"><?=$l?></option><?php endforeach; ?></select>
            </div>
            <div class="field"><label>Selling Price (₹) *</label><input type="number" name="price" min="1" step="0.01" required placeholder="e.g. 299"></div>
            <div class="field"><label>MRP / Original Price (₹)</label><input type="number" name="mrp" min="1" step="0.01" placeholder="e.g. 499 (optional)"></div>
            <div class="field"><label>Stock Quantity *</label><input type="number" name="stock" min="0" required placeholder="How many pieces?"></div>
            <div class="field"><label>Weight (grams)</label><input type="number" name="weight_grams" min="1" placeholder="e.g. 500"></div>
            <div class="field"><label>Main Photo *</label><input type="file" name="image1" accept="image/*" required></div>
            <div class="field"><label>Photo 2 (optional)</label><input type="file" name="image2" accept="image/*"></div>
            <div class="field"><label>Photo 3 (optional)</label><input type="file" name="image3" accept="image/*"></div>
          </div>
          <button type="submit" class="form-btn">🚀 List Product →</button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ MY PRODUCTS ══ -->
    <div id="tab-products" class="tab-panel <?=$tab==='products'?'active':''?>">
      <div class="section-title">
        📦 My Products (<?=count($products)?>)
        <button class="form-btn" style="padding:7px 16px;font-size:13px" onclick="goTab('add_product')">➕ Add Product</button>
      </div>
      <?php if(empty($products)): ?>
      <div class="empty-state">
        <span class="emoji">📦</span>
        <p>No products yet. <a href="#" onclick="goTab('add_product');return false;" style="color:var(--accent);font-weight:700">Add your first listing →</a></p>
      </div>
      <?php else: ?>
      <div class="product-grid">
        <?php foreach($products as $p):
          $img = !empty($p['image1']) ? image_url($p['image1']) : null;
        ?>
        <div class="prod-card">
          <?php if($img): ?>
            <img src="<?=htmlspecialchars($img)?>" class="prod-img" alt="<?=htmlspecialchars($p['name'])?>">
          <?php else: ?>
            <div class="prod-img-ph">🛍️</div>
          <?php endif; ?>
          <div class="prod-body">
            <div class="prod-name"><?=htmlspecialchars($p['name'])?></div>
            <div class="prod-price">
              ₹<?=number_format($p['price'],2)?>
              <?php if($p['mrp'] > $p['price']): ?>
                <span class="prod-mrp">₹<?=number_format($p['mrp'],2)?></span>
              <?php endif; ?>
            </div>
            <div class="prod-meta">
              Stock: <?=(int)$p['stock']?> &nbsp;|&nbsp;
              Sold: <?=(int)$p['total_sold']?> &nbsp;|&nbsp;
              ⭐ <?=number_format($p['avg_rating'],1)?> (<?=(int)$p['rev_count']?> reviews)
            </div>
            <div style="margin-bottom:10px">
              <span class="pill <?=$p['is_active']?'active':'inactive'?>"><?=$p['is_active']?'Active':'Inactive'?></span>
            </div>
            <div class="prod-actions">
              <form method="POST" action="../api/add_product.php" style="flex:1">
                <?=csrf_field()?>
                <input type="hidden" name="toggle_id" value="<?=(int)$p['id']?>">
                <input type="hidden" name="active"    value="<?=$p['is_active']?0:1?>">
                <button type="submit" class="btn-sm <?=$p['is_active']?'btn-warning':'btn-success'?>" style="width:100%">
                  <?=$p['is_active']?'Deactivate':'Activate'?>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ ORDERS ══ -->
    <div id="tab-orders" class="tab-panel <?=$tab==='orders'?'active':''?>">
      <div class="section-title">🛒 Manage Orders</div>
      <?php if(empty($orders)): ?>
      <div class="empty-state"><span class="emoji">🛒</span><p>No orders yet.</p></div>
      <?php else: ?>
      <div class="table-wrap" style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>Order #</th><th>Buyer</th><th>Items</th><th>Amount</th>
              <th>Ship To</th><th>Status</th><th>Date</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($orders as $o): ?>
          <tr>
            <td><strong><?=htmlspecialchars($o['order_number'])?></strong></td>
            <td style="font-size:11px"><?=htmlspecialchars($o['buyer_email'])?></td>
            <td style="max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:12px">
              <?=htmlspecialchars($o['items']??'—')?>
            </td>
            <td><strong>₹<?=number_format($o['total_amount'],2)?></strong></td>
            <td style="font-size:12px"><?=htmlspecialchars($o['shipping_city'])?>, <?=htmlspecialchars($o['shipping_state'])?></td>
            <td><span class="pill <?=htmlspecialchars($o['order_status'])?>"><?=ucfirst(str_replace('_',' ',$o['order_status']))?></span></td>
            <td style="white-space:nowrap;font-size:12px"><?=date('d M Y',strtotime($o['created_at']))?></td>
            <td>
              <?php if($o['order_status']==='placed'): ?>
                <form method="POST" action="../api/update_order_status.php" style="display:inline">
                  <?=csrf_field()?>
                  <input type="hidden" name="order_id" value="<?=(int)$o['id']?>">
                  <input type="hidden" name="status"   value="confirmed">
                  <button type="submit" class="btn-sm btn-success">✓ Confirm</button>
                </form>
              <?php elseif($o['order_status']==='confirmed'): ?>
                <form method="POST" action="../api/update_order_status.php" style="display:inline">
                  <?=csrf_field()?>
                  <input type="hidden" name="order_id" value="<?=(int)$o['id']?>">
                  <input type="hidden" name="status"   value="shipped">
                  <input type="text" name="tracking_id" placeholder="Tracking ID"
                    style="width:90px;padding:4px 8px;border-radius:6px;border:1px solid #ddd;font-size:11px;margin-right:4px">
                  <button type="submit" class="btn-sm btn-warning">🚚 Ship</button>
                </form>
              <?php elseif($o['order_status']==='shipped'): ?>
                <form method="POST" action="../api/update_order_status.php" style="display:inline">
                  <?=csrf_field()?>
                  <input type="hidden" name="order_id" value="<?=(int)$o['id']?>">
                  <input type="hidden" name="status"   value="out_for_delivery">
                  <button type="submit" class="btn-sm btn-warning">📦 Out for Delivery</button>
                </form>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ══ BANK DETAILS ══ -->
    <div id="tab-bank" class="tab-panel <?=$tab==='bank'?'active':''?>">
      <div class="section-title">🏦 Bank &amp; Payment Details</div>
      <div class="form-card">
        <h3>Payment Information</h3>
        <p class="fc-sub">Used for order settlements. Kept secure and never shared.</p>
        <form method="POST" action="../api/seller_setup.php" enctype="multipart/form-data">
          <?=csrf_field()?>
          <input type="hidden" name="bank_only" value="1">
          <div class="form-grid">
            <div class="field">
              <label>UPI ID</label>
              <input type="text" name="upi_id" value="<?=htmlspecialchars($store['upi_id']??'')?>" placeholder="yourname@upi">
            </div>
            <div class="field">
              <label>Bank Name</label>
              <input type="text" name="bank_name" value="<?=htmlspecialchars($store['bank_name']??'')?>" placeholder="e.g. State Bank of India">
            </div>
            <div class="field">
              <label>Account Holder Name</label>
              <input type="text" name="bank_holder_name" value="<?=htmlspecialchars($store['bank_holder_name']??'')?>" placeholder="Name as on passbook">
            </div>
            <div class="field">
              <label>Account Number</label>
              <input type="text" name="bank_account" value="<?=htmlspecialchars($store['bank_account']??'')?>" placeholder="Account number">
            </div>
            <div class="field">
              <label>IFSC Code</label>
              <input type="text" name="bank_ifsc" value="<?=htmlspecialchars($store['bank_ifsc']??'')?>" placeholder="e.g. SBIN0001234">
            </div>
          </div>
          <div style="background:#fef3c7;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#92400e">
            🔒 Your banking details are encrypted and used only for payment settlements. Never share your OTP or password.
          </div>
          <button type="submit" class="form-btn">💾 Save Payment Details →</button>
        </form>
      </div>
    </div>

  </main>
</div>

<div id="dashToast"></div>
<script src="../js/dashboard.js"></script>
<script src="../js/ai_chat.js"></script>
<script src="../js/script.js"></script>
</body>
</html>
