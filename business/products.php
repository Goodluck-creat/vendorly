<?php
require_once __DIR__ . '/../includes/marketplace.php';
start_secure_session();
require_role('business');

$businessId = current_business_id();

$stmt = db()->prepare(
    'SELECT * FROM products WHERE business_id = :bid ORDER BY created_at DESC'
);
$stmt->execute(['bid' => $businessId]);
$products = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Products — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-tint:#E4EEED; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink); margin:0; }
  .wrap{ max-width:900px; margin:0 auto; padding:32px 20px; }
  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
  h1{ font-size:22px; margin:0; }
  .btn{ display:inline-flex; align-items:center; padding:10px 18px; border-radius:9px; font-weight:700; font-size:13.5px; text-decoration:none; border:1.5px solid transparent; }
  .btn.primary{ background:var(--brand); color:#fff; }
  .btn.outline{ background:transparent; color:var(--brand); border-color:var(--brand); }
  .btn.danger{ background:transparent; color:#B1471B; border-color:#B1471B; }
  .flash{ background:#DEF2E6; color:#1F7A4C; padding:10px 14px; border-radius:8px; font-size:13.5px; margin-bottom:18px; }
  table{ width:100%; border-collapse:collapse; background:#fff; border-radius:12px; overflow:hidden; border:1px solid var(--line); }
  th,td{ text-align:left; padding:12px 14px; font-size:13.5px; border-bottom:1px solid var(--line); }
  th{ font-size:11.5px; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-soft); background:var(--brand-tint); }
  tr:last-child td{ border-bottom:none; }
  .thumb{ width:44px; height:44px; border-radius:8px; background:var(--brand-tint); object-fit:cover; }
  .thumb.empty{ display:inline-block; }
  .status{ font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px; }
  .status.active{ background:#DEF2E6; color:#1F7A4C; }
  .status.out_of_stock{ background:#FCE7DA; color:#B1471B; }
  .status.archived{ background:#EDEDED; color:#777; }
  .actions{ display:flex; gap:8px; }
  .actions a, .actions button{ font-size:12.5px; }
  .empty-state{ text-align:center; padding:60px 20px; color:var(--ink-soft); }
  a.top-link{ color:var(--brand); font-size:13.5px; text-decoration:none; font-weight:600; }
  form.inline{ display:inline; }
  button.linklike{ background:none; border:none; padding:0; cursor:pointer; font-weight:700; color:#B1471B; text-decoration:underline; }
</style>
</head>
<body>
<div class="wrap">
  <a href="/business/dashboard.php" class="top-link">&larr; Dashboard</a>
  <div class="topbar" style="margin-top:14px;">
    <h1>Your products</h1>
    <a href="/business/product_form.php" class="btn primary">+ Add product</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      You haven't listed any products yet.<br>
      <a href="/business/product_form.php" class="btn primary" style="margin-top:14px;">Add your first product</a>
    </div>
  <?php else: ?>
    <table>
      <thead>
        <tr><th></th><th>Product</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
          <tr>
            <td>
              <?php if ($p['image_url']): ?>
                <img class="thumb" src="/<?= htmlspecialchars($p['image_url']) ?>" alt="">
              <?php else: ?>
                <div class="thumb empty"></div>
              <?php endif; ?>
            </td>
            <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><span style="color:var(--ink-soft);font-size:12px;"><?= htmlspecialchars($p['category'] ?? '') ?></span></td>
            <td>₦<?= number_format((float)$p['price'], 2) ?></td>
            <td><?= (int) $p['stock_quantity'] ?></td>
            <td><span class="status <?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars(str_replace('_',' ',$p['status'])) ?></span></td>
            <td class="actions">
              <a href="/business/product_form.php?id=<?= (int) $p['id'] ?>" class="btn outline">Edit</a>
              <?php if ($p['status'] !== 'archived'): ?>
                <form class="inline" method="POST" action="/business/product_delete.php" onsubmit="return confirm('Archive this product? It will no longer be visible to customers.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                  <button type="submit" class="btn danger" style="border:1.5px solid #B1471B;background:none;">Archive</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
</body>
</html>
