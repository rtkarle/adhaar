<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/upload.php';
require_once __DIR__ . '/../api/ai_engine.php';
if (!isset($_SESSION['user_email'])) { header("Location: ../auth/login.php"); exit; }
$me   = $_SESSION['user_email'];
$role = $_SESSION['role'] ?? 'donor';
$ai   = adhaar_ai();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: shop.php"); exit; }

$pq = $conn->prepare("SELECT p.*, s.store_name, s.store_description, s.village, s.state, s.store_logo, s.seller_email as s_email FROM products p JOIN seller_stores s ON s.seller_email=p.seller_email WHERE p.id=? AND p.is_active=1");
$pq->bind_param("i",$id); $pq->execute();
$p = $pq->get_result()->fetch_assoc();
if (!$p) { header("Location: shop.php"); exit; }

// ── Log product view for AI recommendations ───────────────────
$ai->logView($me, $id);

$rq = $conn->prepare("SELECT r.*, u.name FROM product_reviews r JOIN register u ON u.email=r.reviewer_email WHERE r.product_id=? ORDER BY r.created_at DESC LIMIT 20");
$rq->bind_param("i",$id); $rq->execute();
$reviews = $rq->get_result()->fetch_all(MYSQLI_ASSOC);

// ── Check if user can review (has purchased + delivered) ──────
$can_review = false;
$review_order_id = null;
$already_reviewed = false;
$oq = $conn->prepare("SELECT o.id FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.buyer_email=? AND oi.product_id=? AND o.order_status='delivered' LIMIT 1");
$oq->bind_param("si",$me,$id); $oq->execute();
$ord_row = $oq->get_result()->fetch_assoc();
if ($ord_row) {
    $can_review = true;
    $review_order_id = (int)$ord_row['id'];
    // Check already reviewed
    $rchk = $conn->prepare("SELECT id FROM product_reviews WHERE product_id=? AND reviewer_email=?");
    $rchk->bind_param("is",$id,$me); $rchk->execute();
    $already_reviewed = $rchk->get_result()->num_rows > 0;
}

// ── AI recommendations based on this product ─────────────────
$ai_recs = $ai->getProductRecommendations($me, $id, 4);

$related    = $conn->query("SELECT p.*, s.store_name FROM products p JOIN seller_stores s ON s.seller_email=p.seller_email WHERE p.category='".mysqli_real_escape_string($conn,$p['category'])."' AND p.id!=$id AND p.is_active=1 LIMIT 4")->fetch_all(MYSQLI_ASSOC);
$cart_count = (int)$conn->query("SELECT COUNT(*) c FROM cart WHERE user_email='".mysqli_real_escape_string($conn,$me)."'")->fetch_assoc()['c'];
$discount   = ($p['mrp'] && $p['mrp']>$p['price']) ? round((($p['mrp']-$p['price'])/$p['mrp'])*100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?=htmlspecialchars($p['name'])?> | Adhaar Shop</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--accent:#006D77;--accent2:#2E8B57;--bg:#f5f8f4;--card:#fff;--text:#102A43;--muted:#5A7184;--shadow:0 8px 24px rgba(16,42,67,.08)}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif}
body{background:linear-gradient(180deg,#f5f8f4 0%,#edf4f1 42%,#f8f3ee 100%);color:var(--text)}
header{position:sticky;top:0;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);box-shadow:0 2px 16px rgba(16,42,67,.07);z-index:100}
.nav{max-width:1200px;margin:auto;padding:0 20px;height:64px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.logo{font-size:18px;font-weight:800;color:var(--accent);text-decoration:none}
.nav-links{display:flex;align-items:center;gap:12px}
.nav-links a{text-decoration:none;font-size:13px;font-weight:600;color:var(--muted);padding:8px 12px;border-radius:8px;transition:.2s}
.nav-links a:hover{color:var(--accent)}
.cart-btn{position:relative;background:var(--accent);color:#fff !important;border-radius:10px !important}
.cart-count{position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-size:10px;font-weight:800;width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.page{max-width:1100px;margin:0 auto;padding:28px 20px}
.breadcrumb{font-size:13px;color:var(--muted);margin-bottom:20px}
.breadcrumb a{color:var(--accent);text-decoration:none;font-weight:600}
.product-layout{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-bottom:40px}
/* Images */
.img-main{width:100%;border-radius:16px;overflow:hidden;background:#f0ede5}
.img-main img{width:100%;max-height:420px;object-fit:cover;display:block}
.img-ph{width:100%;height:360px;background:linear-gradient(135deg,#f0ede5,#e8e4d8);display:flex;align-items:center;justify-content:center;font-size:64px}
.img-thumbs{display:flex;gap:10px;margin-top:12px}
.img-thumb{width:72px;height:72px;border-radius:10px;overflow:hidden;border:2px solid transparent;cursor:pointer;object-fit:cover}
.img-thumb.active,.img-thumb:hover{border-color:var(--accent)}
/* Details */
.store-tag{font-size:13px;color:var(--muted);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.store-tag a{color:var(--accent);font-weight:700;text-decoration:none}
.prod-title{font-size:24px;font-weight:800;line-height:1.25;margin-bottom:12px}
.rating-row{display:flex;align-items:center;gap:8px;margin-bottom:14px}
.stars{color:#f59e0b;font-size:16px}
.rating-txt{font-size:13px;color:var(--muted)}
.price-row{display:flex;align-items:baseline;gap:12px;margin-bottom:8px}
.price{font-size:28px;font-weight:800;color:var(--accent)}
.mrp{font-size:16px;color:var(--muted);text-decoration:line-through}
.discount-tag{background:#d1fae5;color:#065f46;padding:4px 10px;border-radius:8px;font-size:12px;font-weight:700}
.location{font-size:13px;color:var(--muted);margin-bottom:16px}
.stock-badge{display:inline-block;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;margin-bottom:18px}
.in-stock{background:#d1fae5;color:#065f46}.out-stock{background:#fee2e2;color:#991b1b}
.qty-row{display:flex;align-items:center;gap:12px;margin-bottom:20px}
.qty-row label{font-size:13px;font-weight:700;color:var(--muted)}
.qty-ctrl{display:flex;align-items:center;gap:0}
.qty-ctrl button{width:36px;height:36px;border:1.5px solid #e0ddd5;background:#fff;font-size:16px;font-weight:700;cursor:pointer;transition:.2s}
.qty-ctrl button:first-child{border-radius:8px 0 0 8px}
.qty-ctrl button:last-child{border-radius:0 8px 8px 0}
.qty-ctrl button:hover{border-color:var(--accent);color:var(--accent)}
.qty-ctrl input{width:52px;height:36px;border:1.5px solid #e0ddd5;border-left:none;border-right:none;text-align:center;font-size:15px;font-weight:700;outline:none}
.action-btns{display:flex;gap:12px;margin-bottom:24px}
.btn-cart{flex:2;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:.3s}
.btn-cart:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,109,119,.18)}
.btn-cart:disabled{opacity:.5;cursor:not-allowed;transform:none}
.delivery-info{background:#f8f7f0;border-radius:12px;padding:16px;margin-bottom:16px}
.delivery-info h4{font-size:13px;font-weight:700;margin-bottom:10px;color:var(--text)}
.del-row{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--muted);margin-bottom:7px}
.del-row .icon{font-size:18px;width:24px}
.seller-card{background:var(--card);border-radius:14px;padding:18px;border:1px solid #ede9df;margin-bottom:16px}
.seller-card h4{font-size:13px;font-weight:700;margin-bottom:10px}
.sc-row{font-size:13px;color:var(--muted);margin-bottom:5px}
/* Description */
.desc-section{background:var(--card);border-radius:16px;padding:28px;box-shadow:var(--shadow);margin-bottom:28px}
.desc-section h3{font-size:17px;font-weight:800;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #ede9df}
.desc-section p{font-size:14px;color:var(--muted);line-height:1.8}
/* Reviews */
.reviews-section{background:var(--card);border-radius:16px;padding:28px;box-shadow:var(--shadow);margin-bottom:28px}
.reviews-section h3{font-size:17px;font-weight:800;margin-bottom:6px}
.rev-summary{display:flex;align-items:center;gap:16px;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid #ede9df}
.rev-score{font-size:40px;font-weight:800;color:var(--accent)}
.rev-stars{font-size:20px;color:#f59e0b}
.rev-count{font-size:13px;color:var(--muted)}
.review-card{border-bottom:1px solid #f0ede4;padding:16px 0}
.review-card:last-child{border-bottom:none}
.rev-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.rev-name{font-size:14px;font-weight:700}
.rev-date{font-size:12px;color:var(--muted)}
.rev-stars-sm{color:#f59e0b;font-size:14px;margin-bottom:6px}
.rev-text{font-size:13px;color:var(--muted);line-height:1.7}
.verified-badge{font-size:10px;background:#d1fae5;color:#065f46;padding:2px 7px;border-radius:6px;font-weight:700;margin-left:8px}
/* Related */
.related-section{margin-bottom:28px}
.related-section h3{font-size:17px;font-weight:800;margin-bottom:16px}
.related-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px}
.rel-card{background:var(--card);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);transition:.3s;cursor:pointer;border:1px solid #ede9df}
.rel-card:hover{transform:translateY(-4px)}
.rel-img{width:100%;height:140px;object-fit:cover;background:#f0ede5}
.rel-body{padding:12px}
.rel-name{font-size:13px;font-weight:700;margin-bottom:4px}
.rel-price{font-size:15px;font-weight:800;color:var(--accent)}
/* Review form */
.write-review-section{background:var(--card);border-radius:16px;padding:28px;box-shadow:var(--shadow);margin-bottom:28px}
.write-review-section h3{font-size:17px;font-weight:800;margin-bottom:6px;padding-bottom:10px;border-bottom:2px solid #ede9df}
.star-picker{display:flex;gap:6px;margin-bottom:14px;font-size:28px;cursor:pointer}
.star-picker span{transition:.15s;color:#d4d0c8;user-select:none}
.star-picker span.lit{color:#f59e0b}
.star-picker span:hover{transform:scale(1.15)}
.review-textarea{width:100%;padding:12px 14px;border:1.5px solid #e0ddd5;border-radius:10px;font-size:14px;font-family:inherit;resize:vertical;min-height:90px;outline:none;background:#fafaf6;transition:.2s}
.review-textarea:focus{border-color:var(--accent);background:#fff;box-shadow:0 0 0 3px rgba(122,125,63,.1)}
.review-submit-btn{padding:10px 24px;border:none;border-radius:10px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:14px;font-weight:700;cursor:pointer;transition:.25s;margin-top:10px}
.review-submit-btn:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(122,125,63,.35)}
/* AI recs */
.ai-recs-section{margin-bottom:28px}
.ai-recs-section h3{font-size:17px;font-weight:800;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.ai-recs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-top:12px}
.ai-rec-card{background:var(--card);border-radius:14px;overflow:hidden;box-shadow:var(--shadow);transition:.3s;cursor:pointer;border:1px solid #ede9df;position:relative}
.ai-rec-card:hover{transform:translateY(-4px);box-shadow:0 12px 30px rgba(122,125,63,.15)}
.ai-badge{position:absolute;top:8px;left:8px;background:rgba(122,125,63,.9);color:#fff;font-size:9px;font-weight:800;padding:2px 7px;border-radius:20px;letter-spacing:.5px}
</style>
</head>
<body>
<header>
  <div class="nav">
    <a href="../index.html" class="logo">🌿 Adhaar</a>
    <div class="nav-links">
      <a href="shop.php">← Shop</a>
      <a href="my_orders.php">📋 Orders</a>
      <a href="cart.php" class="cart-btn">🛒 Cart<?php if($cart_count>0): ?><span class="cart-count"><?=$cart_count?></span><?php endif; ?></a>
    </div>
  </div>
</header>
<div class="page">
  <div class="breadcrumb"><a href="shop.php">Shop</a> / <a href="shop.php?cat=<?=htmlspecialchars($p['category'])?>"><?=ucfirst(str_replace('_',' ',$p['category']))?></a> / <?=htmlspecialchars($p['name'])?></div>
  <div class="product-layout">
    <!-- Images -->
    <div>
      <div class="img-main">
        <?php if($p['image1']): ?>
        <img src="../<?=htmlspecialchars($p['image1'])?>" alt="" id="mainImg">
        <?php else: ?><div class="img-ph">🛍️</div><?php endif; ?>
      </div>
      <div class="img-thumbs">
        <?php foreach(['image1','image2','image3'] as $i=>$k): if(!empty($p[$k])): ?>
        <img src="../<?=htmlspecialchars($p[$k])?>" class="img-thumb <?=$i===0?'active':''?>" onclick="setImg(this,'<?=htmlspecialchars('../'.$p[$k])?>',<?=$i?>)" alt="">
        <?php endif; endforeach; ?>
      </div>
    </div>
    <!-- Details -->
    <div>
      <div class="store-tag">🏪 <a href="shop.php?q=<?=urlencode($p['store_name'])?>"><?=htmlspecialchars($p['store_name'])?></a></div>
      <h1 class="prod-title"><?=htmlspecialchars($p['name'])?></h1>
      <?php if($p['avg_rating']>0): ?>
      <div class="rating-row">
        <span class="stars"><?=str_repeat('★',round($p['avg_rating'])).str_repeat('☆',5-round($p['avg_rating']))?></span>
        <span class="rating-txt"><?=number_format($p['avg_rating'],1)?> (<?=count($reviews)?> reviews)</span>
      </div>
      <?php endif; ?>
      <div class="price-row">
        <span class="price">₹<?=number_format($p['price'],2)?></span>
        <?php if($p['mrp']>$p['price']): ?>
        <span class="mrp">₹<?=number_format($p['mrp'],2)?></span>
        <span class="discount-tag"><?=$discount?>% OFF</span>
        <?php endif; ?>
      </div>
      <?php if($p['village']||$p['state']): ?>
      <div class="location">📍 Made in <?=htmlspecialchars(trim(($p['village']?$p['village'].', ':'').$p['state']))?></div>
      <?php endif; ?>
      <span class="stock-badge <?=$p['stock']>0?'in-stock':'out-stock'?>"><?=$p['stock']>0?'✓ In Stock ('.$p['stock'].' left)':'Out of Stock'?></span>
      <div class="qty-row">
        <label>Qty:</label>
        <div class="qty-ctrl">
          <button type="button" onclick="changeQty(-1)">−</button>
          <input type="number" id="qty" value="1" min="1" max="<?=(int)$p['stock']?>">
          <button type="button" onclick="changeQty(1)">+</button>
        </div>
      </div>
      <div class="action-btns">
        <button class="btn-cart" id="addCartBtn" onclick="addToCart()" <?=$p['stock']<=0?'disabled':''?>>🛒 Add to Cart</button>
      </div>
      <div class="delivery-info">
        <h4>📦 Delivery & Support</h4>
        <div class="del-row"><span class="icon">🚚</span>Free delivery on orders above ₹499</div>
        <div class="del-row"><span class="icon">📅</span>Estimated delivery: 5–8 business days</div>
        <div class="del-row"><span class="icon">↩️</span>Easy 7-day returns</div>
        <div class="del-row"><span class="icon">💳</span>COD available</div>
      </div>
      <div class="seller-card">
        <h4>🏪 About the Seller</h4>
        <div class="sc-row"><strong><?=htmlspecialchars($p['store_name'])?></strong></div>
        <?php if($p['village']||$p['state']): ?>
        <div class="sc-row">📍 <?=htmlspecialchars(trim(($p['village']?$p['village'].', ':'').$p['state']))?></div>
        <?php endif; ?>
        <div class="sc-row" style="margin-top:6px;font-size:12px"><?=htmlspecialchars(mb_substr($p['store_description']??'',0,120))?>...</div>
      </div>
    </div>
  </div>

  <div class="desc-section">
    <h3>📋 Product Description</h3>
    <p><?=nl2br(htmlspecialchars($p['description']??''))?></p>
    <?php if($p['weight_grams']): ?>
    <p style="margin-top:12px;font-size:13px;color:var(--muted)"><strong>Weight:</strong> <?=number_format($p['weight_grams'])?> grams</p>
    <?php endif; ?>
  </div>

  <div class="reviews-section">
    <h3>⭐ Customer Reviews</h3>
    <?php if(!empty($reviews)): ?>
    <div class="rev-summary">
      <div class="rev-score"><?=number_format($p['avg_rating'],1)?></div>
      <div>
        <div class="rev-stars"><?=str_repeat('★',round($p['avg_rating'])).str_repeat('☆',5-round($p['avg_rating']))?></div>
        <div class="rev-count"><?=count($reviews)?> verified reviews</div>
      </div>
    </div>
    <?php foreach($reviews as $r): ?>
    <div class="review-card">
      <div class="rev-header">
        <div class="rev-name"><?=htmlspecialchars($r['name']??'Buyer')?><span class="verified-badge">✓ Verified</span></div>
        <div class="rev-date"><?=date('d M Y',strtotime($r['created_at']))?></div>
      </div>
      <div class="rev-stars-sm"><?=str_repeat('★',$r['rating']).str_repeat('☆',5-$r['rating'])?></div>
      <?php if($r['review_text']): ?><div class="rev-text"><?=htmlspecialchars($r['review_text'])?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p style="color:var(--muted);font-size:14px">No reviews yet. Be the first to review after purchase!</p>
    <?php endif; ?>
  </div>

  <?php if(!empty($related)): ?>
  <div class="related-section">
    <h3>🛍️ Related Products</h3>
    <div class="related-grid">
      <?php foreach($related as $r): $ri=!empty($r['image1'])?image_url($r['image1']):null; ?>
      <div class="rel-card" onclick="location.href='product.php?id=<?=(int)$r['id']?>'">
        <?php if($ri): ?><img src="<?=htmlspecialchars($ri)?>" class="rel-img" alt="">
        <?php else: ?><div style="height:140px;background:linear-gradient(135deg,#f0ede5,#e8e4d8);display:flex;align-items:center;justify-content:center;font-size:32px">🛍️</div><?php endif; ?>
        <div class="rel-body">
          <div class="rel-name"><?=htmlspecialchars($r['name'])?></div>
          <div class="rel-price">₹<?=number_format($r['price'],2)?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ══ WRITE A REVIEW (inline — no order needed to browse, but submits via my_orders logic) ══ -->
  <?php if($can_review && !$already_reviewed): ?>
  <div class="write-review-section" id="writeReview">
    <h3>✍️ Write a Review</h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:16px">You purchased this product. Share your experience!</p>
    <form id="reviewForm">
      <?=csrf_field()?>
      <input type="hidden" name="order_id" value="<?=(int)$review_order_id?>">
      <input type="hidden" name="product_id" value="<?=(int)$id?>">
      <input type="hidden" name="rating" id="ratingInput" value="5">
      <div>
        <label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:8px">Your Rating *</label>
        <div class="star-picker" id="starPicker">
          <span onclick="setRating(1)">★</span>
          <span onclick="setRating(2)">★</span>
          <span onclick="setRating(3)">★</span>
          <span onclick="setRating(4)">★</span>
          <span onclick="setRating(5)" class="lit">★</span>
        </div>
      </div>
      <div style="margin-bottom:12px">
        <label style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;display:block;margin-bottom:8px">Your Review (optional)</label>
        <textarea class="review-textarea" name="review_text" id="reviewText" placeholder="Share what you liked, how it looks, delivery experience..."></textarea>
      </div>
      <button type="button" onclick="submitReview()" class="review-submit-btn">Submit Review →</button>
      <div id="reviewMsg" style="margin-top:12px;font-size:13px;font-weight:600;display:none"></div>
    </form>
  </div>
  <?php elseif($already_reviewed): ?>
  <div style="background:#d1fae5;border-radius:12px;padding:14px 18px;margin-bottom:28px;font-size:13px;color:#065f46;font-weight:600;display:flex;align-items:center;gap:8px">
    ✅ You have already reviewed this product. Thank you!
  </div>
  <?php elseif($p['stock']>0 && !$can_review): ?>
  <div style="background:#f8f7f2;border-radius:12px;padding:14px 18px;margin-bottom:28px;font-size:13px;color:var(--muted);font-weight:600;display:flex;align-items:center;gap:8px">
    💡 Purchase and receive this product to leave a verified review.
  </div>
  <?php endif; ?>

  <!-- ══ AI RECOMMENDATIONS ══ -->
  <?php if(!empty($ai_recs)): ?>
  <div class="ai-recs-section">
    <h3>🤖 AI Recommended For You
      <span style="font-size:11px;background:#f0ede5;padding:4px 11px;border-radius:20px;font-weight:600;color:var(--muted)">Based on your browsing history</span>
    </h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:12px">Products matching your interests</p>
    <div class="ai-recs-grid">
      <?php foreach($ai_recs as $ar):
        $arimg = !empty($ar['image1']) ? image_url($ar['image1']) : null;
        $ar_disc = ($ar['mrp'] && $ar['mrp']>$ar['price']) ? round((($ar['mrp']-$ar['price'])/$ar['mrp'])*100) : 0;
      ?>
      <div class="ai-rec-card" onclick="location.href='product.php?id=<?=(int)$ar['id']?>'">
        <span class="ai-badge">🤖 AI PICK</span>
        <?php if($arimg): ?><img src="<?=htmlspecialchars($arimg)?>" class="rel-img" alt="">
        <?php else: ?><div style="height:140px;background:linear-gradient(135deg,#f0ede5,#e8e4d8);display:flex;align-items:center;justify-content:center;font-size:32px">🛍️</div><?php endif; ?>
        <div class="rel-body">
          <div style="font-size:10px;color:var(--muted);margin-bottom:2px">🏪 <?=htmlspecialchars($ar['store_name'])?></div>
          <div class="rel-name"><?=htmlspecialchars($ar['name'])?></div>
          <div style="display:flex;align-items:baseline;gap:6px;margin-top:4px">
            <span class="rel-price">₹<?=number_format($ar['price'],2)?></span>
            <?php if($ar_disc>0): ?><span style="font-size:10px;background:#d1fae5;color:#065f46;padding:1px 6px;border-radius:5px;font-weight:700"><?=$ar_disc?>% off</span><?php endif; ?>
          </div>
          <?php if($ar['avg_rating']>0): ?><div style="font-size:11px;color:var(--muted);margin-top:3px">⭐ <?=number_format($ar['avg_rating'],1)?> · <?=(int)$ar['total_sold']?> sold</div><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
<script>
function setImg(thumb, src, idx) {
  document.querySelectorAll('.img-thumb').forEach(t=>t.classList.remove('active'));
  thumb.classList.add('active');
  document.getElementById('mainImg').src = src;
}
function changeQty(d) {
  const inp = document.getElementById('qty');
  inp.value = Math.max(1, Math.min(<?=(int)$p['stock']?>, +inp.value + d));
}
function addToCart() {
  const qty = document.getElementById('qty').value;
  fetch('../api/cart_action.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'action=add&product_id=<?=(int)$p['id']?>&quantity='+qty+'&csrf_token=<?=csrf_token()?>'
  })
  .then(r=>r.json())
  .then(d=>{
    const btn = document.getElementById('addCartBtn');
    if(d.success){
      btn.textContent='✅ Added to Cart!';
      btn.style.background='linear-gradient(135deg,#059669,#10b981)';
      setTimeout(()=>{btn.textContent='🛒 Add to Cart';btn.style.background='';},2500);
    } else { alert(d.message||'Error adding to cart'); }
  });
}

/* ── Star rating picker ── */
let curRating = 5;
function setRating(r) {
  curRating = r;
  document.getElementById('ratingInput').value = r;
  document.querySelectorAll('#starPicker span').forEach((s,i)=>{
    s.classList.toggle('lit', i < r);
  });
}
// Init stars on load
document.addEventListener('DOMContentLoaded', ()=>setRating(5));

/* ── Submit inline review ── */
async function submitReview() {
  const form = document.getElementById('reviewForm');
  const msg  = document.getElementById('reviewMsg');
  const btn  = form.querySelector('.review-submit-btn');
  const text = document.getElementById('reviewText').value.trim();
  const rating = parseInt(document.getElementById('ratingInput').value);

  if (!rating || rating < 1 || rating > 5) {
    msg.textContent = '⚠ Please select a rating.';
    msg.style.color = '#991b1b';
    msg.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Submitting…';

  const fd = new FormData(form);
  fd.append('submit_review', '1');

  const r = await fetch('../shop/my_orders.php', { method:'POST', body: fd });
  const text2 = await r.text();

  // Check for redirect (success) vs staying on page (error)
  btn.disabled = false;
  btn.textContent = 'Submit Review →';

  if (r.redirected || r.url.includes('msg=review') || r.status < 400) {
    msg.textContent = '✅ Review submitted! Thank you for your feedback.';
    msg.style.color = '#065f46';
    msg.style.background = '#d1fae5';
    msg.style.padding = '10px 14px';
    msg.style.borderRadius = '8px';
    msg.style.display = 'block';
    form.querySelector('.star-picker').style.pointerEvents = 'none';
    form.querySelector('.review-textarea').disabled = true;
    btn.style.display = 'none';
    // Reload page after 2s to show new review
    setTimeout(()=>location.reload(), 2000);
  } else {
    msg.textContent = '⚠ Submission failed. Please try again.';
    msg.style.color = '#991b1b';
    msg.style.display = 'block';
  }
}
</script>
</body>
</html>
