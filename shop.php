<?php
require_once __DIR__ . '/includes/marketplace.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/cart.php';
start_secure_session();
// This shopfront intentionally does NOT use the shared marketplace nav — the whole point
// is that it feels like the vendor's own independent site, not a page inside Vendorly's chrome.

$slug = $_GET['slug'] ?? '';
$business = $slug !== '' ? get_business_by_slug($slug) : null;

if (!$business) {
    header('Location: /customer/home.php');
    exit;
}

$productsStmt = db()->prepare(
    "SELECT * FROM products WHERE business_id = :bid AND status = 'active' ORDER BY category ASC, created_at DESC"
);
$productsStmt->execute(['bid' => $business['id']]);
$products = $productsStmt->fetchAll();

$signature = get_signature_product($business['id']);

$byCategory = [];
foreach ($products as $p) {
    $cat = $p['category'] ?: 'More items';
    $byCategory[$cat][] = $p;
}

// If this customer already has items in a cart for THIS business, show the persistent cart bar.
$activeCart = null;
if (is_logged_in()) {
    $activeCart = get_cart(current_user_id(), (int) $business['id']);
    if ($activeCart && empty($activeCart['items'])) $activeCart = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($business['business_name']) ?> — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED;
    --accent:#FF7A45; --accent-deep:#E8622E; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5;
  }
  *{box-sizing:border-box;}
  html{ scroll-behavior:smooth; }
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); -webkit-font-smoothing:antialiased; }
  h1,h2,h3{ font-family:'Sora',sans-serif; margin:0; }
  img{ display:block; max-width:100%; }
  :focus-visible{ outline:2.5px solid var(--accent); outline-offset:2px; }
  .icon{ width:18px; height:18px; vertical-align:middle; }

  .wrap{ max-width:1080px; margin:0 auto; padding:0 24px; }

  /* ---------------- HERO ---------------- */
  .hero{ position:relative; height:min(52vh, 380px); min-height:280px; overflow:hidden; display:flex; align-items:flex-end; }
  .hero-backdrop{ position:absolute; inset:-20px; background-size:cover; background-position:center; filter:blur(30px) brightness(0.5) saturate(1.3); transform:scale(1.1); }
  .hero-backdrop.no-image{ background:linear-gradient(135deg, var(--brand), var(--brand-deep)); }
  .hero-topbar{ position:absolute; top:0; left:0; right:0; z-index:2; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; }
  .hero-topbar a{ color:#fff; text-decoration:none; font-size:13.5px; font-weight:600; display:flex; align-items:center; gap:6px; text-shadow:0 1px 4px rgba(0,0,0,.35); }
  .hero-topbar .navactions{ display:flex; gap:12px; align-items:center; }
  .hero-topbar .navactions a{ background:rgba(255,255,255,.18); backdrop-filter:blur(6px); padding:9px 16px; border-radius:22px; transition:background .18s ease; }
  .hero-topbar .navactions a:hover{ background:rgba(255,255,255,.3); }

  .signature-stage{ position:absolute; right:6%; top:52%; transform:translateY(-50%); z-index:1; width:180px; height:180px; display:flex; align-items:center; justify-content:center; }
  .signature-plate{ width:158px; height:158px; border-radius:50%; overflow:hidden; border:5px solid rgba(255,255,255,.9); box-shadow:0 24px 50px rgba(0,0,0,.4); animation: spin 16s linear infinite; }
  .signature-plate img{ width:100%; height:100%; object-fit:cover; }
  .steam{ position:absolute; bottom:62%; left:50%; width:70px; height:90px; pointer-events:none; }
  .steam span{ position:absolute; bottom:0; width:16px; height:16px; border-radius:50%; background:rgba(255,255,255,.6); filter:blur(7px); animation: rise 3.2s ease-in infinite; }
  .steam span:nth-child(1){ left:10px; animation-delay:0s; }
  .steam span:nth-child(2){ left:30px; animation-delay:1s; }
  .steam span:nth-child(3){ left:18px; animation-delay:2s; }
  @keyframes spin{ from{ transform:rotate(0deg); } to{ transform:rotate(360deg); } }
  @keyframes rise{ 0%{ transform:translateY(0) scale(0.6); opacity:0; } 30%{ opacity:.7; } 100%{ transform:translateY(-75px) scale(1.35); opacity:0; } }
  @media (prefers-reduced-motion: reduce){ .signature-plate{ animation:none; } .steam span{ animation:none; opacity:0; } }

  /* ---------------- VENDOR INFO CARD ---------------- */
  .vendor-card{
    background:#fff; border-radius:20px; padding:24px 28px; margin:-50px 24px 0;
    position:relative; z-index:2; box-shadow:0 14px 38px rgba(15,34,34,.14);
    display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;
  }
  .vendor-card h1{ font-size:24px; margin-bottom:6px; }
  .vendor-meta{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; font-size:13px; color:var(--ink-soft); }
  .status-dot{ width:7px; height:7px; border-radius:50%; background:#2E9E6B; display:inline-block; margin-right:5px; }
  .cat-pill{ background:var(--brand-tint); color:var(--brand); padding:4px 12px; border-radius:20px; font-weight:600; }
  .vendor-stats{ display:flex; gap:22px; }
  .vendor-stats .stat{ text-align:center; }
  .vendor-stats .stat b{ display:block; font-size:17px; }
  .vendor-stats .stat span{ font-size:11px; color:var(--ink-soft); }

  /* ---------------- STICKY CATEGORY NAV ---------------- */
  .catnav-sticky{ position:sticky; top:0; z-index:10; background:#fff; border-bottom:1px solid var(--line); margin-top:24px; }
  .catnav-scroll{ display:flex; gap:10px; overflow-x:auto; padding:16px 24px; scrollbar-width:none; max-width:1080px; margin:0 auto; }
  .catnav-scroll::-webkit-scrollbar{ display:none; }
  .catnav-scroll a{
    flex:none; text-decoration:none; font-size:13.5px; font-weight:700; color:var(--ink-soft);
    padding:9px 18px; border-radius:22px; border:1.5px solid var(--line); white-space:nowrap; transition:all .2s ease;
  }
  .catnav-scroll a.active{ background:var(--accent); color:#fff; border-color:var(--accent); }
  .catnav-scroll a:hover:not(.active){ border-color:var(--brand); color:var(--brand); }

  /* ---------------- SCROLL-REVEAL MENU SECTIONS ---------------- */
  .menu-section{ padding:34px 0 6px; opacity:0; transform:translateY(20px); transition:opacity .5s ease, transform .5s ease; }
  .menu-section.in-view{ opacity:1; transform:translateY(0); }
  .menu-section h2{ font-size:19px; margin-bottom:16px; }

  .item-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:16px; }
  .item-card{
    background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; text-decoration:none; color:inherit;
    display:flex; flex-direction:column; transition:transform .18s ease, box-shadow .18s ease;
    position:relative;
  }
  .item-card:hover{ transform:translateY(-4px); box-shadow:0 14px 32px rgba(15,34,34,.12); }
  .item-photo{ width:100%; aspect-ratio:4/3; background:linear-gradient(135deg,var(--brand-tint),#fff); object-fit:cover; }
  .item-body{ padding:14px 16px 16px; flex:1; display:flex; flex-direction:column; }
  .item-name{ font-size:14.5px; font-weight:700; margin-bottom:4px; }
  .item-desc{ font-size:12px; color:var(--ink-soft); line-height:1.4; margin-bottom:10px; flex:1; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .item-footer{ display:flex; align-items:center; justify-content:space-between; }
  .item-price{ font-size:15px; font-weight:800; }
  .item-price .tag{ display:block; font-weight:600; font-size:9px; color:var(--ink-soft); background:var(--brand-tint); padding:2px 7px; border-radius:20px; margin-top:3px; }
  .quick-add{
    width:36px; height:36px; border-radius:50%; background:var(--accent); color:#fff; border:none;
    display:flex; align-items:center; justify-content:center; flex:none; transition:background .16s ease, transform .12s ease;
  }
  .quick-add:hover{ background:var(--accent-deep); transform:scale(1.08); }
  .quick-add .icon{ width:18px; height:18px; }

  .empty{ text-align:center; padding:70px 20px; color:var(--ink-soft); }
  footer.shopfoot{ text-align:center; padding:34px 20px 90px; font-size:12px; color:var(--ink-soft); }

  /* ---------------- STICKY CART BAR ---------------- */
  .cart-bar{
    position:fixed; bottom:16px; left:16px; right:16px; z-index:20; max-width:600px; margin:0 auto;
    background:var(--brand); color:#fff; border-radius:16px; padding:14px 20px;
    display:flex; align-items:center; justify-content:space-between; text-decoration:none;
    box-shadow:0 12px 34px rgba(15,61,62,.35); animation: slideup .35s ease both;
  }
  .cart-bar:hover{ background:var(--brand-deep); }
  @keyframes slideup{ from{ opacity:0; transform:translateY(20px); } to{ opacity:1; transform:translateY(0); } }
  .cart-bar-left{ display:flex; align-items:center; gap:10px; font-size:13.5px; font-weight:700; }
  .cart-bar-count{ background:var(--accent); width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; }
  .cart-bar-right{ display:flex; align-items:center; gap:8px; font-size:14.5px; font-weight:800; }

  /* ---------------- RESPONSIVE ---------------- */
  @media (min-width:1081px){
    .wrap{ padding:0 32px; }
    .catnav-scroll{ padding:18px 32px; }
  }
  @media (max-width:700px){
    .hero{ height:min(42vh, 280px); min-height:220px; }
    .signature-stage{ width:120px; height:120px; right:16px; }
    .signature-plate{ width:104px; height:104px; }
    .vendor-card{ margin:-40px 16px 0; padding:18px 20px; }
    .vendor-card h1{ font-size:19px; }
    .vendor-stats{ display:none; }
    .wrap{ padding:0 16px; }
    .catnav-scroll{ padding:14px 16px; }
    .item-grid{ grid-template-columns:1fr 1fr; gap:12px; }
    .item-body{ padding:11px 12px 13px; }
    .item-name{ font-size:13px; }
  }
  @media (max-width:420px){
    .item-grid{ grid-template-columns:1fr; }
  }
</style>
</head>
<body>

<div class="hero">
  <?php if ($signature && $signature['image_url']): ?>
    <div class="hero-backdrop" style="background-image:url('/<?= htmlspecialchars($signature['image_url']) ?>');"></div>
  <?php else: ?>
    <div class="hero-backdrop no-image"></div>
  <?php endif; ?>

  <div class="hero-topbar">
    <a href="/customer/home.php"><?= icon('arrow-left', 'icon') ?> Marketplace</a>
    <div class="navactions">
      <?php if (is_logged_in()): ?>
        <a href="/customer/orders.php">My orders</a>
        <a href="<?= htmlspecialchars(role_home_path(current_role())) ?>"><?= is_guest() ? 'Hi, ' . htmlspecialchars($_SESSION['name']) : 'My account' ?></a>
      <?php else: ?>
        <a href="/login.php">Log in</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($signature): ?>
    <div class="signature-stage">
      <div class="steam"><span></span><span></span><span></span></div>
      <div class="signature-plate">
        <?php if ($signature['image_url']): ?><img src="/<?= htmlspecialchars($signature['image_url']) ?>" alt="<?= htmlspecialchars($signature['name']) ?>"><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="vendor-card">
  <div>
    <h1><?= htmlspecialchars($business['business_name']) ?></h1>
    <div class="vendor-meta">
      <span><span class="status-dot"></span>Open now</span>
      <span class="cat-pill"><?= htmlspecialchars($business['category'] ?? 'Food') ?></span>
    </div>
  </div>
  <div class="vendor-stats">
    <div class="stat"><b><?= count($products) ?></b><span>Items</span></div>
    <div class="stat"><b><?= count($byCategory) ?></b><span>Categories</span></div>
  </div>
</div>

<?php if (!empty($products)): ?>
<div class="catnav-sticky">
  <div class="catnav-scroll" id="catnav">
    <?php $first = true; foreach (array_keys($byCategory) as $cat): $catId = 'cat-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($cat)); ?>
      <a href="#<?= $catId ?>" data-target="<?= $catId ?>" class="<?= $first ? 'active' : '' ?>"><?= htmlspecialchars($cat) ?></a>
    <?php $first = false; endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="wrap">
  <?php if (empty($products)): ?>
    <div class="empty"><?= icon('package') ?><div>This shop hasn't listed any items yet.</div></div>
  <?php else: ?>
    <?php foreach ($byCategory as $cat => $items): $catId = 'cat-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($cat)); ?>
      <section class="menu-section" id="<?= $catId ?>">
        <h2><?= htmlspecialchars($cat) ?></h2>
        <div class="item-grid">
          <?php foreach ($items as $p): ?>
            <a class="item-card" href="/customer/product.php?id=<?= (int) $p['id'] ?>">
              <?php if ($p['image_url']): ?>
                <img class="item-photo" src="/<?= htmlspecialchars($p['image_url']) ?>" alt="">
              <?php else: ?>
                <div class="item-photo"></div>
              <?php endif; ?>
              <div class="item-body">
                <div class="item-name"><?= htmlspecialchars($p['name']) ?></div>
                <?php if (!empty($p['description'])): ?><div class="item-desc"><?= htmlspecialchars($p['description']) ?></div><?php endif; ?>
                <div class="item-footer">
                  <div class="item-price">
                    ₦<?= number_format((float) $p['price'], 2) ?>
                    <?php if ($p['pricing_mode'] === 'negotiable'): ?><span class="tag">negotiable</span><?php endif; ?>
                  </div>
                  <?php if ($p['pricing_mode'] === 'fixed'): ?>
                    <span class="quick-add" onclick="event.preventDefault(); event.stopPropagation(); window.location.href='/customer/add_to_cart.php?product_id=<?= (int) $p['id'] ?>';"><?= icon('plus', 'icon') ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <footer class="shopfoot">Powered by Vendorly</footer>
</div>

<?php if ($activeCart): ?>
  <a class="cart-bar" href="/customer/cart.php?business_id=<?= (int) $business['id'] ?>">
    <div class="cart-bar-left">
      <span class="cart-bar-count"><?= array_sum(array_column($activeCart['items'], 'quantity')) ?></span>
      View cart
    </div>
    <div class="cart-bar-right">₦<?= number_format($activeCart['total'], 2) ?> <?= icon('arrow-left','icon') ?></div>
  </a>
<?php endif; ?>

<script>
(function(){
  const sections = document.querySelectorAll('.menu-section');
  const navLinks = document.querySelectorAll('#catnav a');
  const stickyNav = document.querySelector('.catnav-sticky');
  if (!sections.length) return;

  function setActive(id){ navLinks.forEach(a => a.classList.toggle('active', a.dataset.target === id)); }

  const spyObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{ if (entry.isIntersecting) setActive(entry.target.id); });
  }, { rootMargin: '-45% 0px -45% 0px', threshold: 0 });
  sections.forEach(s => spyObserver.observe(s));

  navLinks.forEach(link=>{
    link.addEventListener('click', function(e){
      e.preventDefault();
      const target = document.getElementById(this.dataset.target);
      if (!target) return;
      const offset = stickyNav.offsetHeight + 8;
      const top = target.getBoundingClientRect().top + window.scrollY - offset;
      window.scrollTo({ top, behavior:'smooth' });
    });
  });

  const revealObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{ if (entry.isIntersecting){ entry.target.classList.add('in-view'); revealObserver.unobserve(entry.target); } });
  }, { threshold: 0.08 });
  sections.forEach(s => revealObserver.observe(s));
})();
</script>
</body>
</html>
