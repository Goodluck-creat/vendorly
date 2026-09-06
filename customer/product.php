<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/public_nav.php';
start_secure_session();
// Public page — anyone can view a product. Chat/order (Stage 3) is where login actually gets required.

$productId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    "SELECT p.*, b.business_name, b.id AS business_id
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($product['name']) ?> — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-tint:#E4EEED; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink); margin:0; }
  .wrap{ max-width:520px; margin:0 auto; padding:32px 20px; }
  a.top-link{ color:var(--brand); font-size:13.5px; text-decoration:none; font-weight:600; }
  .photo{ width:100%; aspect-ratio:1/0.85; border-radius:14px; background:var(--brand-tint); object-fit:cover; margin:16px 0; }
  h1{ font-size:20px; margin:0 0 4px; }
  .seller{ font-size:13px; color:var(--ink-soft); margin-bottom:14px; }
  .seller a{ color:var(--brand); text-decoration:none; font-weight:600; }
  .price{ font-size:22px; font-weight:800; margin-bottom:16px; }
  .desc{ font-size:14px; color:var(--ink-soft); line-height:1.6; margin-bottom:20px; }
  .meta{ font-size:12.5px; color:var(--ink-soft); margin-bottom:20px; }
  .btn{ display:block; width:100%; text-align:center; padding:13px; background:var(--brand); color:#fff; border-radius:10px; font-weight:700; font-size:14.5px; text-decoration:none; }
  .btn.pending{ opacity:0.5; cursor:not-allowed; }
  .note{ font-size:12px; color:var(--ink-soft); text-align:center; margin-top:8px; }
</style>
</head>
<body>
<?= render_public_nav() ?>
<div class="wrap">
  <a href="/customer/storefront.php?id=<?= (int) $product['business_id'] ?>" class="top-link">&larr; <?= htmlspecialchars($product['business_name']) ?></a>

  <?php if ($product['image_url']): ?>
    <img class="photo" src="/<?= htmlspecialchars($product['image_url']) ?>" alt="">
  <?php else: ?>
    <div class="photo"></div>
  <?php endif; ?>

  <h1><?= htmlspecialchars($product['name']) ?></h1>
  <div class="seller">Sold by <a href="/customer/storefront.php?id=<?= (int) $product['business_id'] ?>"><?= htmlspecialchars($product['business_name']) ?></a></div>
  <div class="price">₦<?= number_format((float) $product['price'], 2) ?></div>

  <?php if (!empty($product['description'])): ?>
    <div class="desc"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
  <?php endif; ?>

  <div class="meta"><?= (int) $product['stock_quantity'] ?> in stock</div>

  <a href="<?= is_logged_in() ? '#' : '/customer/guest_start.php?redirect=' . urlencode('/customer/product.php?id=' . $product['id']) ?>"
     class="btn <?= is_logged_in() ? 'pending' : '' ?>">
    Chat to negotiate
  </a>
  <?php if (is_guest()): ?>
    <div class="note">Continuing as <?= htmlspecialchars($_SESSION['name']) ?>. Full chat ships in Stage 3 — you're already identified, no extra step needed then.</div>
  <?php elseif (is_logged_in()): ?>
    <div class="note">Full chat ships in Stage 3.</div>
  <?php else: ?>
    <div class="note">We'll just ask your name and phone — no password needed to chat.</div>
  <?php endif; ?>
</div>
</body>
</html>
