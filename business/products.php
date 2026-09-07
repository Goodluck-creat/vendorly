<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_role('business');

$businessId = current_business_id();

$stmt = db()->prepare('SELECT * FROM products WHERE business_id = :bid ORDER BY created_at DESC');
$stmt->execute(['bid' => $businessId]);
$products = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusMeta = [
    'active'       => ['label' => 'Active',       'class' => 'active'],
    'out_of_stock' => ['label' => 'Out of stock',  'class' => 'out'],
    'archived'     => ['label' => 'Archived',      'class' => 'archived'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Products — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1{ font-family:'Sora',sans-serif; }
  a{ text-decoration:none; }
  .icon{ width:20px; height:20px; vertical-align:middle; }
  :focus-visible{ outline:2.5px solid var(--accent); outline-offset:2px; }

  .wrap{ max-width:720px; margin:0 auto; padding:28px 20px 60px; }
  .top-link{ display:inline-flex; align-items:center; gap:6px; color:var(--brand); font-size:13.5px; font-weight:600; }
  .top-link .icon{ width:16px; height:16px; }

  .topbar{ display:flex; align-items:center; justify-content:space-between; margin:16px 0 22px; flex-wrap:wrap; gap:12px; }
  h1{ font-size:22px; margin:0; }
  .btn{ display:flex; align-items:center; gap:7px; padding:10px 18px; border-radius:9px; font-weight:700; font-size:13.5px; transition:all .16s ease; }
  .btn .icon{ width:16px; height:16px; }
  .btn.primary{ background:var(--brand); color:#fff; }
  .btn.primary:hover{ background:var(--brand-deep); }
  .btn.outline{ background:transparent; color:var(--brand); border:1.5px solid var(--brand); }
  .btn.outline:hover{ background:var(--brand-tint); }
  .btn.danger{ background:transparent; color:#B1471B; border:1.5px solid #B1471B; }
  .btn.danger:hover{ background:#FCE7DA; }
  .btn.small{ padding:8px 14px; font-size:12.5px; }

  .flash{ display:flex; align-items:center; gap:8px; background:#DEF2E6; color:#1F7A4C; padding:11px 15px; border-radius:9px; font-size:13.5px; margin-bottom:18px; }
  .flash .icon{ width:16px; height:16px; }

  .plist{ display:flex; flex-direction:column; gap:12px; }
  .pcard{
    display:flex; gap:14px; align-items:center; background:#fff; border:1px solid var(--line); border-radius:14px;
    padding:14px; opacity:0; transform:translateY(12px); transition:opacity .4s ease, transform .4s ease;
  }
  .pcard.in-view{ opacity:1; transform:translateY(0); }
  .thumb{ width:56px; height:56px; border-radius:11px; background:var(--brand-tint); object-fit:cover; flex:none; display:flex; align-items:center; justify-content:center; color:var(--brand); }
  .pinfo{ flex:1; min-width:0; }
  .pname{ font-size:14.5px; font-weight:700; margin-bottom:2px; }
  .pcat{ font-size:12px; color:var(--ink-soft); margin-bottom:6px; }
  .pmeta{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .price{ font-size:13.5px; font-weight:700; }
  .stock{ font-size:12px; color:var(--ink-soft); }
  .status-pill{ font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:20px; }
  .status-pill.active{ background:#DEF2E6; color:#1F7A4C; }
  .status-pill.out{ background:#FCE7DA; color:#B1471B; }
  .status-pill.archived{ background:#EDEDED; color:#777; }
  .sig-pill{ font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:20px; background:var(--brand-tint); color:var(--brand); display:inline-flex; align-items:center; gap:4px; }
  .sig-pill .icon{ width:11px; height:11px; }

  .pactions{ display:flex; flex-direction:column; gap:6px; flex:none; }

  .empty-state{ text-align:center; padding:60px 20px; color:var(--ink-soft); }
  .empty-state .icon{ width:36px; height:36px; margin-bottom:12px; opacity:.5; }

  @media (max-width:520px){
    .wrap{ padding:22px 16px 50px; }
    .pcard{ flex-wrap:wrap; padding:12px; }
    .pactions{ flex-direction:row; width:100%; margin-top:8px; }
    .pactions .btn{ flex:1; justify-content:center; }
  }
</style>
</head>
<body>
<div class="wrap">
  <a href="/business/dashboard.php" class="top-link"><?= icon('arrow-left', 'icon') ?>Dashboard</a>
  <div class="topbar">
    <h1>Your products</h1>
    <a href="/business/product_form.php" class="btn primary"><?= icon('plus', 'icon') ?>Add product</a>
  </div>

  <?php if ($flash): ?>
    <div class="flash"><?= icon('check-circle', 'icon') ?><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <?php if (empty($products)): ?>
    <div class="empty-state">
      <?= icon('package') ?>
      <div>You haven't listed any products yet.</div>
      <a href="/business/product_form.php" class="btn primary" style="margin-top:16px; display:inline-flex; width:auto;"><?= icon('plus', 'icon') ?>Add your first product</a>
    </div>
  <?php else: ?>
    <div class="plist">
      <?php foreach ($products as $p): $sm = $statusMeta[$p['status']] ?? $statusMeta['active']; ?>
        <div class="pcard reveal">
          <?php if ($p['image_url']): ?>
            <img class="thumb" src="/<?= htmlspecialchars($p['image_url']) ?>" alt="">
          <?php else: ?>
            <div class="thumb"><?= icon('image') ?></div>
          <?php endif; ?>
          <div class="pinfo">
            <div class="pname"><?= htmlspecialchars($p['name']) ?></div>
            <div class="pcat"><?= htmlspecialchars($p['category'] ?? 'Uncategorized') ?></div>
            <div class="pmeta">
              <span class="price">₦<?= number_format((float) $p['price'], 2) ?></span>
              <span class="stock"><?= (int) $p['stock_quantity'] ?> in stock</span>
              <span class="status-pill <?= $sm['class'] ?>"><?= $sm['label'] ?></span>
              <?php if (!empty($p['is_signature'])): ?>
                <span class="sig-pill"><?= icon('star', 'icon') ?>Signature</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="pactions">
            <a href="/business/product_form.php?id=<?= (int) $p['id'] ?>" class="btn outline small"><?= icon('edit', 'icon') ?>Edit</a>
            <?php if ($p['status'] !== 'archived'): ?>
              <form method="POST" action="/business/product_delete.php" onsubmit="return confirm('Archive this product? It will no longer be visible to customers.');">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
                <button type="submit" class="btn danger small"><?= icon('trash', 'icon') ?>Archive</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<script>
const io = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{ if (e.isIntersecting){ e.target.classList.add('in-view'); io.unobserve(e.target); } });
}, { threshold: 0.06 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));
</script>
</body>
</html>
