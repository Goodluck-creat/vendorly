<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();

$productId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT p.*, b.business_name, b.id AS business_id, b.slug AS business_slug
     FROM products p
     JOIN businesses b ON b.id = p.business_id
     WHERE p.id = :id AND p.status = 'active'
     LIMIT 1"
);
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: /customer/home.php');
    exit;
}
if (empty($product['business_slug'])) {
    $business = ensure_business_has_slug(['id' => $product['business_id'], 'business_name' => $product['business_name']]);
    $product['business_slug'] = $business['slug'];
}

// Build the full gallery: primary photo first, then any extra gallery photos.
$gallery = [];
if ($product['image_url']) $gallery[] = $product['image_url'];
foreach (get_product_media($productId) as $m) {
    $gallery[] = $m['image_url'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($product['name']) ?> — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-tint:#E4EEED; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --accent:#FF7A45; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:#fff; color:var(--ink); margin:0; }
  h1{ font-family:'Sora',sans-serif; }
  .icon{ width:16px; height:16px; vertical-align:middle; }
  .topbar{ display:flex; align-items:center; padding:16px 20px; position:sticky; top:0; background:#fff; z-index:5; border-bottom:1px solid var(--line); }
  .topbar a{ color:var(--brand); text-decoration:none; font-size:13.5px; font-weight:700; display:flex; align-items:center; gap:6px; }

  .gallery-main{ width:100%; aspect-ratio:1/0.85; background:var(--brand-tint); overflow:hidden; position:relative; }
  .gallery-main img{ width:100%; height:100%; object-fit:cover; display:block; }
  .gallery-thumbs{ display:flex; gap:8px; padding:12px 20px; overflow-x:auto; }
  .gallery-thumbs img{ width:56px; height:56px; border-radius:9px; object-fit:cover; cursor:pointer; border:2px solid transparent; opacity:.65; flex:none; }
  .gallery-thumbs img.active{ border-color:var(--brand); opacity:1; }

  .wrap{ max-width:560px; margin:0 auto; padding:8px 20px 40px; }
  h1{ font-size:21px; margin:8px 0 4px; }
  .seller{ font-size:13px; color:var(--ink-soft); margin-bottom:14px; }
  .seller a{ color:var(--brand); text-decoration:none; font-weight:600; }
  .price{ font-size:23px; font-weight:800; margin-bottom:16px; }
  .price .tag{ font-size:11px; font-weight:600; color:var(--ink-soft); background:var(--brand-tint); padding:3px 9px; border-radius:20px; margin-left:8px; vertical-align:middle; }
  .desc{ font-size:14px; color:var(--ink-soft); line-height:1.65; margin-bottom:20px; }
  .meta{ font-size:12.5px; color:var(--ink-soft); margin-bottom:22px; }

  .cta-bar{ position:sticky; bottom:0; background:#fff; border-top:1px solid var(--line); padding:14px 20px 18px; margin:24px -20px 0; }
  .btn{ display:block; width:100%; text-align:center; padding:14px; background:var(--brand); color:#fff; border-radius:11px; font-weight:700; font-size:15px; text-decoration:none; }
  .btn.pending{ opacity:0.5; cursor:not-allowed; }
  .note{ font-size:12px; color:var(--ink-soft); text-align:center; margin-top:9px; }

  @media (max-width:480px){
    .wrap{ padding:8px 16px 24px; }
    .cta-bar{ margin:20px -16px 0; padding:12px 16px 16px; }
  }
</style>
</head>
<body>

<div class="topbar">
  <a href="/<?= htmlspecialchars($product['business_slug']) ?>"><?= icon('arrow-left', 'icon') ?> <?= htmlspecialchars($product['business_name']) ?></a>
</div>

<div class="gallery-main">
  <?php if (!empty($gallery)): ?>
    <img id="mainImage" src="/<?= htmlspecialchars($gallery[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
  <?php endif; ?>
</div>
<?php if (count($gallery) > 1): ?>
  <div class="gallery-thumbs">
    <?php foreach ($gallery as $i => $img): ?>
      <img src="/<?= htmlspecialchars($img) ?>" class="<?= $i === 0 ? 'active' : '' ?>" onclick="swapImage(this, '/<?= htmlspecialchars($img) ?>')" alt="">
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="wrap">
  <h1><?= htmlspecialchars($product['name']) ?></h1>
  <div class="seller">Sold by <a href="/<?= htmlspecialchars($product['business_slug']) ?>"><?= htmlspecialchars($product['business_name']) ?></a></div>
  <div class="price">
    ₦<?= number_format((float) $product['price'], 2) ?>
    <?php if ($product['pricing_mode'] === 'negotiable'): ?><span class="tag">negotiable</span><?php endif; ?>
  </div>

  <?php if (!empty($product['description'])): ?>
    <div class="desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
  <?php endif; ?>

  <div class="meta"><?= (int) $product['stock_quantity'] ?> in stock</div>

  <div class="cta-bar">
    <?php
      $isFixed = ($product['pricing_mode'] ?? 'fixed') === 'fixed';
      $ctaLabel = $isFixed ? 'Order now' : 'Chat to negotiate';
      $guestRedirect = '/customer/guest_start.php?redirect=' . urlencode('/customer/product.php?id=' . $product['id']);
    ?>
    <a href="<?= is_logged_in() ? '#' : $guestRedirect ?>" class="btn <?= is_logged_in() ? 'pending' : '' ?>">
      <?= htmlspecialchars($ctaLabel) ?>
    </a>
    <?php if (is_guest()): ?>
      <div class="note">Continuing as <?= htmlspecialchars($_SESSION['name']) ?>. <?= $isFixed ? 'Cart & checkout' : 'Full chat' ?> ships in Stage 3.</div>
    <?php elseif (is_logged_in()): ?>
      <div class="note"><?= $isFixed ? 'Cart & checkout' : 'Full chat' ?> ships in Stage 3.</div>
    <?php else: ?>
      <div class="note">We'll just ask your name and phone — no password needed to <?= $isFixed ? 'order' : 'chat' ?>.</div>
    <?php endif; ?>
  </div>
</div>

<script>
function swapImage(thumb, src){
  document.getElementById('mainImage').src = src;
  document.querySelectorAll('.gallery-thumbs img').forEach(t => t.classList.remove('active'));
  thumb.classList.add('active');
}
</script>
</body>
</html>
