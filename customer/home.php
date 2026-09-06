<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/public_nav.php';
start_secure_session();
// No require_login() / require_role() here on purpose — anyone can browse the marketplace.
// An account is only needed once someone wants to chat, order, or save something.

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = db()->prepare(
        "SELECT b.id, b.business_name, b.category, COUNT(p.id) AS product_count
         FROM businesses b
         JOIN products p ON p.business_id = b.id AND p.status = 'active'
         WHERE b.business_name LIKE :q OR b.category LIKE :q
         GROUP BY b.id
         ORDER BY b.business_name"
    );
    $stmt->execute(['q' => '%' . $search . '%']);
} else {
    $stmt = db()->query(
        "SELECT b.id, b.business_name, b.category, COUNT(p.id) AS product_count
         FROM businesses b
         JOIN products p ON p.business_id = b.id AND p.status = 'active'
         GROUP BY b.id
         ORDER BY b.business_name"
    );
}
$businesses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marketplace — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-tint:#E4EEED; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:#fff; color:var(--ink); margin:0; }
  .wrap{ max-width:820px; margin:0 auto; padding:0 20px; }
  h1{ font-size:20px; margin:18px 0 16px; }
  .card{ display:flex; align-items:center; gap:14px; background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px; margin-bottom:12px; text-decoration:none; color:inherit; }
  .card:hover{ border-color:var(--brand); }
  .avatar{ width:48px; height:48px; border-radius:10px; background:var(--brand-tint); flex:none; }
  .card strong{ display:block; font-size:15px; }
  .card span{ font-size:12.5px; color:var(--ink-soft); }
  .empty{ text-align:center; padding:60px 20px; color:var(--ink-soft); }
  .bottom-pad{ padding-bottom:40px; }
</style>
</head>
<body>
<?= render_public_nav($search) ?>
<div class="wrap bottom-pad">
  <h1>Marketplace</h1>

  <?php if (empty($businesses)): ?>
    <div class="empty">
      <?= $search !== '' ? 'No businesses match "' . htmlspecialchars($search) . '".' : 'No businesses have listed products yet — check back soon.' ?>
    </div>
  <?php else: ?>
    <?php foreach ($businesses as $b): ?>
      <a class="card" href="/customer/storefront.php?id=<?= (int) $b['id'] ?>">
        <div class="avatar"></div>
        <div>
          <strong><?= htmlspecialchars($b['business_name']) ?></strong>
          <span><?= htmlspecialchars($b['category'] ?? 'General') ?> &middot; <?= (int) $b['product_count'] ?> product<?= $b['product_count'] == 1 ? '' : 's' ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
