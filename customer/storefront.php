<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/public_nav.php';
start_secure_session();
// Public page — no login required to view a storefront.

$businessId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM businesses WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $businessId]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: /customer/home.php');
    exit;
}

$productsStmt = db()->prepare(
    "SELECT * FROM products WHERE business_id = :bid AND status = 'active' ORDER BY created_at DESC"
);
$productsStmt->execute(['bid' => $businessId]);
$products = $productsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($business['business_name']) ?> — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-tint:#E4EEED; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink); margin:0; }
  .wrap{ max-width:820px; margin:0 auto; padding:32px 20px; }
  a.top-link{ color:var(--brand); font-size:13.5px; text-decoration:none; font-weight:600; }
  .cover{ height:110px; border-radius:14px; background:linear-gradient(135deg,var(--brand),#082627); margin:16px 0; }
  .bizhead{ display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:24px; flex-wrap:wrap; gap:10px; }
  .bizhead h1{ font-size:20px; margin:0 0 4px; }
  .bizhead span{ font-size:13px; color:var(--ink-soft); }
  .grid{ display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
  .product-card{ background:#fff; border:1px solid var(--line); border-radius:12px; padding:14px; text-decoration:none; color:inherit; display:block; }
  .product-card:hover{ border-color:var(--brand); }
  .thumb{ width:100%; aspect-ratio:1/0.85; border-radius:9px; background:var(--brand-tint); object-fit:cover; margin-bottom:10px; }
  .pname{ font-size:13.5px; font-weight:600; margin-bottom:4px; }
  .price{ font-weight:700; font-size:14px; }
  .empty{ text-align:center; padding:50px 20px; color:var(--ink-soft); }
  @media (max-width:520px){ .grid{ grid-template-columns:1fr 1fr; gap:10px; } }
</style>
</head>
<body>
<?= render_public_nav() ?>
<div class="wrap">
  <a href="/customer/home.php" class="top-link">&larr; Marketplace</a>
  <div class="cover"></div>
  <div class="bizhead">
    <div>
      <h1><?= htmlspecialchars($business['business_name']) ?></h1>
      <span><?= htmlspecialchars($business['category'] ?? 'General') ?></span>
    </div>
  </div>

  <?php if (empty($products)): ?>
    <div class="empty">This business hasn't listed any products yet.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($products as $p): ?>
        <a class="product-card" href="/customer/product.php?id=<?= (int) $p['id'] ?>">
          <?php if ($p['image_url']): ?>
            <img class="thumb" src="/<?= htmlspecialchars($p['image_url']) ?>" alt="">
          <?php else: ?>
            <div class="thumb"></div>
          <?php endif; ?>
          <div class="pname"><?= htmlspecialchars($p['name']) ?></div>
          <div class="price">₦<?= number_format((float) $p['price'], 2) ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
