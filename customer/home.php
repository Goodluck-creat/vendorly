<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
// No require_login() / require_role() here on purpose — anyone can browse the marketplace.

$search = trim($_GET['q'] ?? '');

if ($search !== '') {
    $stmt = db()->prepare(
        "SELECT b.id, b.slug, b.business_name, b.category, COUNT(p.id) AS product_count
         FROM businesses b
         JOIN products p ON p.business_id = b.id AND p.status = 'active'
         WHERE b.business_name LIKE :q OR b.category LIKE :q
         GROUP BY b.id
         ORDER BY b.business_name"
    );
    $stmt->execute(['q' => '%' . $search . '%']);
} else {
    $stmt = db()->query(
        "SELECT b.id, b.slug, b.business_name, b.category, COUNT(p.id) AS product_count
         FROM businesses b
         JOIN products p ON p.business_id = b.id AND p.status = 'active'
         GROUP BY b.id
         ORDER BY b.business_name"
    );
}
$businesses = $stmt->fetchAll();
foreach ($businesses as &$b) {
    if (empty($b['slug'])) {
        $full = ensure_business_has_slug($b);
        $b['slug'] = $full['slug'];
    }
}
unset($b);

$loggedIn = is_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Marketplace — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:#fff; }
  h1{ font-family:'Sora',sans-serif; }
  a{ text-decoration:none; color:inherit; }
  .icon{ width:20px; height:20px; vertical-align:middle; }
  :focus-visible{ outline:2.5px solid var(--accent); outline-offset:2px; }

  header.site{ border-bottom:1px solid var(--line); }
  .headerbar{ max-width:820px; margin:0 auto; display:flex; align-items:center; gap:18px; padding:16px 20px; }
  .wordmark{ display:flex; align-items:center; gap:9px; font-weight:800; font-size:17px; flex:none; }
  .wordmark .chip{ width:24px; height:24px; border-radius:7px; background:linear-gradient(135deg,var(--brand),var(--brand-deep)); }
  .headeractions{ display:flex; align-items:center; gap:16px; font-size:13.5px; font-weight:600; margin-left:auto; flex:none; }
  .headeractions .btn.primary{ background:var(--brand); color:#fff; padding:9px 16px; border-radius:8px; }

  .wrap{ max-width:820px; margin:0 auto; padding:0 20px 50px; }

  .searchbar{
    display:flex; align-items:center; gap:10px; background:var(--paper); border:1.5px solid var(--line);
    border-radius:12px; padding:13px 16px; margin:24px 0 22px; transition:border-color .18s ease;
  }
  .searchbar:focus-within{ border-color:var(--brand); }
  .searchbar .icon{ color:var(--ink-soft); flex:none; }
  .searchbar input{ border:none; background:none; outline:none; font-size:14.5px; width:100%; font-family:inherit; }

  h1.pagetitle{ font-size:22px; margin:0 0 4px; }
  .pagesub{ font-size:13.5px; color:var(--ink-soft); margin:0 0 18px; }

  .card{
    display:flex; align-items:center; gap:14px; background:#fff; border:1px solid var(--line); border-radius:14px;
    padding:16px; margin-bottom:12px; opacity:0; transform:translateY(14px); transition:opacity .45s ease, transform .45s ease, border-color .18s ease;
  }
  .card.in-view{ opacity:1; transform:translateY(0); }
  .card:hover{ border-color:var(--brand); }
  .avatar{
    width:52px; height:52px; border-radius:12px; background:linear-gradient(135deg,var(--brand-tint),#fff);
    display:flex; align-items:center; justify-content:center; color:var(--brand); flex:none;
  }
  .card-info{ flex:1; min-width:0; }
  .card strong{ display:block; font-size:15px; margin-bottom:3px; }
  .card-meta{ display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--ink-soft); flex-wrap:wrap; }
  .cat-pill{ background:var(--brand-tint); color:var(--brand); padding:2px 9px; border-radius:20px; font-weight:600; }
  .card-arrow{ color:var(--ink-soft); flex:none; transition:transform .18s ease; transform:scaleX(-1); }
  .card:hover .card-arrow{ transform:scaleX(-1) translateX(-3px); color:var(--brand); }

  .empty{ text-align:center; padding:70px 20px; color:var(--ink-soft); }
  .empty .icon{ width:36px; height:36px; margin-bottom:12px; opacity:.5; }

  @media (max-width:480px){
    .headerbar{ padding:14px 16px; }
    .wrap{ padding:0 16px 40px; }
    .card{ padding:13px; gap:11px; }
    .avatar{ width:46px; height:46px; }
  }
</style>
</head>
<body>
<header class="site">
  <div class="headerbar">
    <a href="/customer/home.php" class="wordmark"><div class="chip"></div>Vendorly</a>
    <div class="headeractions">
      <?php if ($loggedIn): ?>
        <a href="<?= htmlspecialchars(role_home_path(current_role())) ?>"><?= is_guest() ? 'Hi, ' . htmlspecialchars($_SESSION['name']) : 'My account' ?></a>
        <a href="/logout.php">Log out</a>
      <?php else: ?>
        <a href="/login.php">Log in</a>
        <a href="/register.php" class="btn primary">Sign up</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="wrap">
  <form class="searchbar" method="GET" action="/customer/home.php">
    <?= icon('search') ?>
    <input type="text" name="q" placeholder="Search shops or categories..." value="<?= htmlspecialchars($search) ?>">
  </form>

  <h1 class="pagetitle">Marketplace</h1>
  <p class="pagesub"><?= count($businesses) ?> shop<?= count($businesses) === 1 ? '' : 's' ?> ready to order from</p>

  <?php if (empty($businesses)): ?>
    <div class="empty">
      <?= icon('store') ?>
      <div><?= $search !== '' ? 'No shops match "' . htmlspecialchars($search) . '".' : 'No shops have listed items yet — check back soon.' ?></div>
    </div>
  <?php else: ?>
    <?php foreach ($businesses as $b): ?>
      <a class="card reveal" href="/<?= htmlspecialchars($b['slug']) ?>">
        <div class="avatar"><?= icon('store') ?></div>
        <div class="card-info">
          <strong><?= htmlspecialchars($b['business_name']) ?></strong>
          <div class="card-meta">
            <span class="cat-pill"><?= htmlspecialchars($b['category'] ?? 'Food') ?></span>
            <span><?= (int) $b['product_count'] ?> item<?= $b['product_count'] == 1 ? '' : 's' ?></span>
          </div>
        </div>
        <div class="card-arrow"><?= icon('arrow-left', 'icon') ?></div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.reveal').forEach(el=>{
  el.style.transitionDelay = (Math.random()*0.1)+'s';
});
const io = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{ if (e.isIntersecting){ e.target.classList.add('in-view'); io.unobserve(e.target); } });
}, { threshold: 0.08 });
document.querySelectorAll('.reveal').forEach(el => io.observe(el));
</script>
</body>
</html>
