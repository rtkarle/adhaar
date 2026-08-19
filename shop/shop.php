<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
require_once __DIR__ . '/../api/ai_engine.php';
if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php?redirect=shop"); exit; }
$me    = $_SESSION['user_email'];
$role  = $_SESSION['role'] ?? 'donor';
$ai    = adhaar_ai();

$cat    = $_GET['cat']    ?? 'all';
$search = trim($_GET['q'] ?? '');
$sort   = $_GET['sort']   ?? 'newest';

// ── Log search query for AI recommendation engine ────────────
if ($search) {
    $ai->logSearch($me, $search, $cat !== 'all' ? $cat : null, 0);
}

$where = "p.is_active=1 AND s.is_active=1";
if ($cat !== 'all') $where .= " AND p.category='".mysqli_real_escape_string($conn,$cat)."'";
if ($search) $where .= " AND (p.name LIKE '%".mysqli_real_escape_string($conn,$search)."%' OR p.description LIKE '%".mysqli_real_escape_string($conn,$search)."%')";

$order_map = ['newest'=>'p.created_at DESC','price_low'=>'p.price ASC','price_high'=>'p.price DESC','popular'=>'p.total_sold DESC','rating'=>'p.avg_rating DESC'];
$order_sql = $order_map[$sort] ?? 'p.created_at DESC';

$pq = $conn->query("SELECT p.*, s.store_name, s.village, s.state FROM products p JOIN seller_stores s ON s.seller_email=p.seller_email WHERE $where ORDER BY $order_sql");
$products = $pq->fetch_all(MYSQLI_ASSOC);

// Update result count in search log
if ($search) {
    $conn->query("UPDATE product_search_history SET result_count=".count($products)." WHERE user_email='".mysqli_real_escape_string($conn,$me)."' AND query='".mysqli_real_escape_string($conn,$search)."' ORDER BY searched_at DESC LIMIT 1");
}

$cart_count = (int)$conn->query("SELECT COUNT(*) c FROM cart WHERE user_email='".mysqli_real_escape_string($conn,$me)."'")->fetch_assoc()['c'];

// ── AI Product Recommendations ────────────────────────────────
$ai_recs = $ai->getProductRecommendations($me, 0, 6);
$show_ai_recs = !empty($ai_recs) && !$search; // show on main page, hide when searching

$cats = ['handicraft'=>'🎨 Handicraft','textile'=>'🧵 Textile','food_product'=>'🍯 Food','jewelry'=>'💍 Jewelry','art'=>'🖼️ Art','pottery'=>'🏺 Pottery','organic'=>'🌿 Organic','other'=>'📦 Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Adhaar Shop – Empowering Rural Entrepreneurs</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --navy:#102A43; --teal:#006D77; --teal-dark:#004F55; --teal-light:#83C5BE;
  --green:#2E8B57; --orange:#FF8A00; --pink:#F72585; --purple:#7B2CBF;
  --blue:#2563EB; --cyan:#06B6D4; --white:#FFFFFF; --bg:#F7FAF9; --card:#FFFFFF;
  --border:#E2EBE9; --text:#102A43; --muted:#5A7184; --text-light:#94A3B8;
  --shadow:0 18px 48px rgba(16,42,67,.12); --radius:18px; --radius-sm:10px;
  --accent:var(--teal); --accent2:var(--green);
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
html{scroll-behavior:smooth}
body{background:linear-gradient(180deg,#f5f8f4 0%,#edf4f1 42%,#f8f3ee 100%);color:var(--text);line-height:1.6;position:relative}
body::before,
body::after{content:'';position:fixed;pointer-events:none;z-index:0;border-radius:50%;filter:blur(12px)}
body::before{width:440px;height:440px;left:-140px;top:-120px;background:radial-gradient(circle, rgba(0,109,119,.14), rgba(0,109,119,0));}
body::after{width:360px;height:360px;right:-120px;bottom:-120px;background:radial-gradient(circle, rgba(247,37,133,.10), rgba(247,37,133,0));}
header{position:sticky;top:0;background:rgba(255,255,255,.9);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);box-shadow:0 2px 18px rgba(16,42,67,.07);z-index:100;border-bottom:1px solid rgba(226,235,233,.8)}
.nav{max-width:1200px;margin:auto;padding:0 20px;height:68px;display:flex;align-items:center;justify-content:space-between;gap:16px;position:relative;z-index:1}
.logo{font-size:18px;font-weight:900;color:var(--teal);text-decoration:none;white-space:nowrap;letter-spacing:-.3px}
.search-bar{flex:1;max-width:420px;display:flex;gap:0}
.search-bar input{flex:1;padding:12px 16px;border:1.5px solid var(--border);border-right:none;border-radius:10px 0 0 10px;font-size:14px;outline:none;background:#fafaf6;color:var(--text)}
.search-bar input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,109,119,.08)}
.search-bar button{padding:0 16px;background:linear-gradient(135deg,var(--teal),var(--green));color:#fff;border:none;border-radius:0 10px 10px 0;cursor:pointer;font-weight:700;box-shadow:0 8px 20px rgba(0,109,119,.18)}
.nav-links{display:flex;align-items:center;gap:12px}
.nav-links a{text-decoration:none;font-size:13px;font-weight:700;color:var(--muted);padding:8px 12px;border-radius:10px;transition:.2s}
.nav-links a:hover{color:var(--teal);background:rgba(0,109,119,.06)}
.cart-btn{position:relative;background:linear-gradient(135deg,var(--teal),var(--green));color:#fff !important;padding:8px 16px !important;border-radius:10px !important;box-shadow:0 8px 24px rgba(0,109,119,.2)}
.cart-count{position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:10px;font-weight:800;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.page{max-width:1200px;margin:0 auto;padding:24px 20px;position:relative;z-index:1}
.shop-hero{background:linear-gradient(135deg,#102A43 0%,#006D77 60%,#2E8B57 100%);border-radius:22px;padding:32px 36px;color:#fff;margin-bottom:28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;box-shadow:0 20px 56px rgba(16,42,67,.18)}
.shop-hero h1{font-size:26px;font-weight:900;margin-bottom:6px;letter-spacing:-.4px}
.shop-hero p{font-size:14px;opacity:.92;max-width:500px}
.hero-stats{display:flex;gap:20px;flex-wrap:wrap}
.hero-stat{background:rgba(255,255,255,.12);padding:10px 18px;border-radius:12px;text-align:center;border:1px solid rgba(255,255,255,.12)}
.hero-stat .hs-v{font-size:20px;font-weight:800}
.hero-stat .hs-l{font-size:11px;opacity:.9;text-transform:uppercase;letter-spacing:.5px}
.filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:24px}
.filter-cats{display:flex;gap:8px;flex-wrap:wrap;flex:1}
.cat-btn{padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);background:#fff;font-size:13px;font-weight:700;cursor:pointer;transition:.2s;text-decoration:none;color:var(--muted)}
.cat-btn:hover,.cat-btn.active{background:linear-gradient(135deg,var(--teal),var(--green));color:#fff;border-color:transparent;box-shadow:0 8px 24px rgba(0,109,119,.18)}
.sort-select{padding:9px 14px;border:1.5px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);background:#fff;outline:none;cursor:pointer}
.sort-select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(0,109,119,.08)}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px}
.prod-card{background:var(--card);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);transition:.3s;cursor:pointer;border:1px solid rgba(16,42,67,.05);position:relative}
.prod-card:hover{transform:translateY(-6px);box-shadow:0 24px 58px rgba(16,42,67,.13)}
.prod-img{width:100%;height:180px;object-fit:cover;background:#f0ede5;display:block}
.prod-img-ph{width:100%;height:180px;background:linear-gradient(135deg,#edf3f0,#e5e4dd);display:flex;align-items:center;justify-content:center;font-size:42px}
.prod-body{padding:14px}
.prod-store{font-size:11px;color:var(--muted);margin-bottom:4px;display:flex;align-items:center;gap:4px}
.prod-name{font-size:15px;font-weight:800;margin-bottom:6px;line-height:1.35;color:var(--text)}
.prod-loc{font-size:11px;color:var(--muted);margin-bottom:8px}
.prod-price-row{display:flex;align-items:baseline;gap:8px;margin-bottom:10px;flex-wrap:wrap}
.prod-price{font-size:18px;font-weight:900;color:var(--teal)}
.prod-mrp{font-size:12px;color:var(--muted);text-decoration:line-through}
.prod-discount{font-size:11px;background:#d1fae5;color:#065f46;padding:2px 7px;border-radius:6px;font-weight:800}
.prod-rating{font-size:12px;color:var(--muted);margin-bottom:10px}
.add-cart-btn{width:100%;padding:10px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--teal),var(--green));color:#fff;font-size:13px;font-weight:800;cursor:pointer;transition:.25s;box-shadow:0 8px 20px rgba(0,109,119,.16)}
.add-cart-btn:hover{transform:translateY(-1px);box-shadow:0 10px 30px rgba(46,139,87,.28)}
.add-cart-btn.added{background:linear-gradient(135deg,#059669,#10b981)}
.empty{text-align:center;padding:64px 24px;background:rgba(255,255,255,.7);border-radius:20px;box-shadow:var(--shadow);border:1px solid rgba(16,42,67,.05)}
.empty .emoji{font-size:52px;margin-bottom:14px}
.empty p{color:var(--muted);font-size:14px}
.toast{position:fixed;bottom:24px;right:24px;background:#102A43;color:#fff;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:600;z-index:9999;transform:translateY(80px);opacity:0;transition:.35s;box-shadow:0 18px 42px rgba(16,42,67,.22)}
.toast.show{transform:translateY(0);opacity:1}
@media(max-width:700px){.search-bar{max-width:none;order:3;width:100%}.nav{flex-wrap:wrap;height:auto;padding:12px 16px;gap:8px}.shop-hero{padding:20px}.prod-img,.prod-img-ph{height:150px}}
</style>
</head>
<body>
<header>
  <div class="nav">
    <a href="../index.html" class="logo">🌿 Adhaar</a>
    <form class="search-bar" method="GET">
      <input type="text" name="q" value="<?=htmlspecialchars($search)?>" placeholder="Search handmade products...">
      <button type="submit">🔍</button>
    </form>
    <div class="nav-links">
      <a href="../<?=$role?>/<?=$role?>_dashboard.php">Dashboard</a>
      <a href="my_orders.php">📋 Orders</a>
      <a href="cart.php" class="cart-btn">
        🛒 Cart
        <?php if($cart_count>0): ?><span class="cart-count"><?=$cart_count?></span><?php endif; ?>
      </a>
    </div>
  </div>
</header>

<div class="page">
  <div class="shop-hero">
    <div>
      <h1>🛍️ Adhaar Shop</h1>
      <p>Every purchase empowers a rural artisan, woman entrepreneur, or local craftsperson. Buy with purpose.</p>
    </div>
    <div class="hero-stats">
      <div class="hero-stat">
        <div class="hs-v"><?=count($products)?></div>
        <div class="hs-l">Products</div>
      </div>
      <div class="hero-stat">
        <div class="hs-v"><?=(int)$conn->query("SELECT COUNT(*) c FROM seller_stores WHERE is_active=1")->fetch_assoc()['c']?></div>
        <div class="hs-l">Sellers</div>
      </div>
    </div>
  </div>

  <div class="filter-bar">
    <div class="filter-cats">
      <a href="?cat=all&sort=<?=$sort?>" class="cat-btn <?=$cat==='all'?'active':''?>">All</a>
      <?php foreach($cats as $v=>$l): ?>
      <a href="?cat=<?=$v?>&sort=<?=$sort?>" class="cat-btn <?=$cat===$v?'active':''?>"><?=$l?></a>
      <?php endforeach; ?>
    </div>
    <form method="GET" style="display:flex;gap:8px;align-items:center">
      <input type="hidden" name="cat" value="<?=htmlspecialchars($cat)?>">
      <input type="hidden" name="q" value="<?=htmlspecialchars($search)?>">
      <select name="sort" class="sort-select" onchange="this.form.submit()">
        <option value="newest" <?=$sort==='newest'?'selected':''?>>Newest</option>
        <option value="price_low" <?=$sort==='price_low'?'selected':''?>>Price: Low → High</option>
        <option value="price_high" <?=$sort==='price_high'?'selected':''?>>Price: High → Low</option>
        <option value="popular" <?=$sort==='popular'?'selected':''?>>Most Popular</option>
        <option value="rating" <?=$sort==='rating'?'selected':''?>>Top Rated</option>
      </select>
    </form>
  </div>

  <?php if(empty($products)): ?>
  <div class="empty">
    <div class="emoji">🛍️</div>
    <p>No products found. <?=$search?'Try a different search.':'Check back soon!'?></p>
  </div>
  <?php else: ?>
  <div class="products-grid">
    <?php foreach($products as $p):
      $img = !empty($p['image1']) ? image_url($p['image1']) : null;
      $discount = ($p['mrp'] && $p['mrp']>$p['price']) ? round((($p['mrp']-$p['price'])/$p['mrp'])*100) : 0;
    ?>
    <div class="prod-card" onclick="window.location='product.php?id=<?=(int)$p['id']?>'">
      <?php if($img): ?><img src="<?=htmlspecialchars($img)?>" class="prod-img" alt="<?=htmlspecialchars($p['name'])?>">
      <?php else: ?><div class="prod-img-ph">🛍️</div><?php endif; ?>
      <div class="prod-body">
        <div class="prod-store">🏪 <?=htmlspecialchars($p['store_name'])?></div>
        <div class="prod-name"><?=htmlspecialchars($p['name'])?></div>
        <?php if($p['village']||$p['state']): ?>
        <div class="prod-loc">📍 <?=htmlspecialchars(trim(($p['village']?$p['village'].', ':'').$p['state']))?></div>
        <?php endif; ?>
        <div class="prod-price-row">
          <span class="prod-price">₹<?=number_format($p['price'],2)?></span>
          <?php if($p['mrp']>$p['price']): ?>
          <span class="prod-mrp">₹<?=number_format($p['mrp'],2)?></span>
          <span class="prod-discount"><?=$discount?>% off</span>
          <?php endif; ?>
        </div>
        <?php if($p['avg_rating']>0): ?>
        <div class="prod-rating">⭐ <?=number_format($p['avg_rating'],1)?> · <?=(int)$p['total_sold']?> sold</div>
        <?php endif; ?>
        <button class="add-cart-btn" id="btn-<?=(int)$p['id']?>" onclick="addToCart(event,<?=(int)$p['id']?>)" <?=$p['stock']<=0?'disabled style="opacity:.5;cursor:not-allowed"':''?>>
          <?=$p['stock']<=0?'Out of Stock':'🛒 Add to Cart'?>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if($show_ai_recs): ?>
<!-- ══ AI RECOMMENDED FOR YOU ══ -->
<div style="margin-top:40px;margin-bottom:12px">
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:18px">
    <div>
      <h2 style="font-size:18px;font-weight:800;color:var(--text);margin-bottom:4px">🤖 Recommended For You</h2>
      <p style="font-size:13px;color:var(--muted)">AI-personalised picks based on your browsing &amp; purchase history</p>
    </div>
    <span style="font-size:11px;background:#f0ede5;padding:5px 12px;border-radius:20px;font-weight:700;color:var(--muted);display:flex;align-items:center;gap:5px">
      🤖 AI Powered &nbsp; · &nbsp; <?=count($ai_recs)?> picks
    </span>
  </div>
  <div class="products-grid">
    <?php foreach($ai_recs as $p):
      $img = !empty($p['image1']) ? image_url($p['image1']) : null;
      $discount = ($p['mrp'] && $p['mrp']>$p['price']) ? round((($p['mrp']-$p['price'])/$p['mrp'])*100) : 0;
      $score = $p['_rec_score'] ?? 0;
    ?>
    <div class="prod-card" onclick="window.location='product.php?id=<?=(int)$p['id']?>'">
      <?php if($img): ?><img src="<?=htmlspecialchars($img)?>" class="prod-img" alt="<?=htmlspecialchars($p['name'])?>">
      <?php else: ?><div class="prod-img-ph">🛍️</div><?php endif; ?>
      <!-- AI relevance badge -->
      <div style="position:absolute;top:10px;left:10px;background:rgba(122,125,63,.92);color:#fff;font-size:9px;font-weight:800;padding:3px 8px;border-radius:20px;letter-spacing:.5px">🤖 AI PICK</div>
      <div class="prod-body">
        <div class="prod-store">🏪 <?=htmlspecialchars($p['store_name'])?></div>
        <div class="prod-name"><?=htmlspecialchars($p['name'])?></div>
        <?php if($p['village']||$p['state']): ?>
        <div class="prod-loc">📍 <?=htmlspecialchars(trim(($p['village']?$p['village'].', ':'').$p['state']))?></div>
        <?php endif; ?>
        <div class="prod-price-row">
          <span class="prod-price">₹<?=number_format($p['price'],2)?></span>
          <?php if($p['mrp']>$p['price']): ?>
          <span class="prod-mrp">₹<?=number_format($p['mrp'],2)?></span>
          <span class="prod-discount"><?=$discount?>% off</span>
          <?php endif; ?>
        </div>
        <?php if($p['avg_rating']>0): ?>
        <div class="prod-rating">⭐ <?=number_format($p['avg_rating'],1)?> · <?=(int)$p['total_sold']?> sold</div>
        <?php endif; ?>
        <button class="add-cart-btn" id="btn-rec-<?=(int)$p['id']?>" onclick="addToCart(event,<?=(int)$p['id']?>)" <?=$p['stock']<=0?'disabled style="opacity:.5;cursor:not-allowed"':''?>>
          <?=$p['stock']<=0?'Out of Stock':'🛒 Add to Cart'?>
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div id="toast" class="toast"></div>

<script>
function addToCart(e, pid) {
  e.stopPropagation();
  const btn = document.getElementById('btn-'+pid);
  fetch('../api/cart_action.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=add&product_id='+pid+'&csrf_token='+encodeURIComponent('<?=csrf_token()?>')
  })
  .then(r=>r.json())
  .then(d=>{
    if(d.success){
      btn.textContent='✅ Added!';
      btn.classList.add('added');
      setTimeout(()=>{btn.textContent='🛒 Add to Cart';btn.classList.remove('added');},2000);
      showToast('Added to cart! 🛒');
      // Update cart count in header
      document.querySelectorAll('.cart-count').forEach(el=>el.textContent=d.cart_count);
      if(d.cart_count>0 && !document.querySelector('.cart-count')){
        const cb=document.querySelector('.cart-btn');
        const span=document.createElement('span');
        span.className='cart-count';span.textContent=d.cart_count;
        cb.appendChild(span);
      }
    } else {
      showToast(d.message || 'Could not add to cart');
    }
  });
}
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'), 3000);
}
</script>
</body>
</html>
