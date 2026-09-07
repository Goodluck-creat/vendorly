<?php
require_once __DIR__ . '/includes/marketplace.php';
require_once __DIR__ . '/includes/icons.php';
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

// Group products by category for the menu sections
$byCategory = [];
foreach ($products as $p) {
    $cat = $p['category'] ?: 'More items';
    $byCategory[$cat][] = $p;
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
    --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5;
  }
  *{box-sizing:border-box;}
  html{ scroll-behavior:smooth; }
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:#fff; -webkit-font-smoothing:antialiased; }
  h1,h2,h3{ font-family:'Sora',sans-serif; margin:0; }
  img{ display:block; max-width:100%; }
  :focus-visible{ outline:2.5px solid var(--accent); outline-offset:2px; }
  .icon{ width:18px; height:18px; vertical-align:middle; }

  .wrap{ max-width:760px; margin:0 auto; padding:0 20px; }

  /* ---------------- HERO ---------------- */
  .hero{
    position:relative; height:min(60vh, 420px); min-height:300px; overflow:hidden;
    display:flex; align-items:flex-end;
  }
  .hero-backdrop{
    position:absolute; inset:-20px; background-size:cover; background-position:center;
    filter:blur(28px) brightness(0.55) saturate(1.2); transform:scale(1.1);
  }
  .hero-backdrop.no-image{ background:linear-gradient(135deg, var(--brand), var(--brand-deep)); }
  .hero-inner{ position:relative; z-index:1; width:100%; padding:0 20px 64px; }
  .hero-topbar{ position:absolute; top:0; left:0; right:0; z-index:2; padding:16px 20px; display:flex; justify-content:space-between; align-items:center; }
  .hero-topbar a{ color:#fff; text-decoration:none; font-size:13.5px; font-weight:600; display:flex; align-items:center; gap:6px; text-shadow:0 1px 4px rgba(0,0,0,.35); }
  .hero-topbar .navactions{ display:flex; gap:14px; align-items:center; }
  .hero-topbar .navactions a{ background:rgba(255,255,255,.16); backdrop-filter:blur(6px); padding:8px 14px; border-radius:20px; }

  /* Signature dish showcase, floated over the hero */
  .signature-stage{
    position:absolute; right:20px; top:50%; transform:translateY(-50%); z-index:1;
    width:150px; height:150px; display:flex; align-items:center; justify-content:center;
  }
  .signature-plate{
    width:132px; height:132px; border-radius:50%; overflow:hidden; border:4px solid rgba(255,255,255,.85);
    box-shadow:0 20px 45px rgba(0,0,0,.35);
    animation: spin 14s linear infinite;
  }
  .signature-plate img{ width:100%; height:100%; object-fit:cover; }
  .steam{ position:absolute; bottom:60%; left:50%; width:60px; height:80px; pointer-events:none; }
  .steam span{
    position:absolute; bottom:0; width:14px; height:14px; border-radius:50%;
    background:rgba(255,255,255,.55); filter:blur(6px);
    animation: rise 3.2s ease-in infinite;
  }
  .steam span:nth-child(1){ left:8px; animation-delay:0s; }
  .steam span:nth-child(2){ left:26px; animation-delay:1s; }
  .steam span:nth-child(3){ left:16px; animation-delay:2s; }

  @keyframes spin{ from{ transform:rotate(0deg); } to{ transform:rotate(360deg); } }
  @keyframes rise{
    0%{ transform:translateY(0) scale(0.6); opacity:0; }
    30%{ opacity:.7; }
    100%{ transform:translateY(-70px) scale(1.3); opacity:0; }
  }
  @media (prefers-reduced-motion: reduce){
    .signature-plate{ animation:none; }
    .steam span{ animation:none; opacity:0; }
  }

  /* ---------------- FLOATING VENDOR CARD ---------------- */
  .vendor-card{
    background:#fff; border-radius:18px; padding:20px; margin:-46px 20px 0;
    position:relative; z-index:2; box-shadow:0 12px 34px rgba(15,34,34,.12);
  }
  .vendor-card h1{ font-size:21px; margin-bottom:4px; }
  .vendor-meta{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; font-size:12.5px; color:var(--ink-soft); }
  .status-dot{ width:7px; height:7px; border-radius:50%; background:#2E9E6B; display:inline-block; margin-right:5px; }
  .cat-pill{ background:var(--brand-tint); color:var(--brand); padding:3px 10px; border-radius:20px; font-weight:600; }

  /* ---------------- STICKY CATEGORY NAV ---------------- */
  .catnav-sticky{
    position:sticky; top:0; z-index:10; background:#fff; border-bottom:1px solid var(--line);
    margin-top:20px;
  }
  .catnav-scroll{ display:flex; gap:8px; overflow-x:auto; padding:14px 20px; scrollbar-width:none; }
  .catnav-scroll::-webkit-scrollbar{ display:none; }
  .catnav-scroll a{
    flex:none; text-decoration:none; font-size:13px; font-weight:600; color:var(--ink-soft);
    padding:8px 16px; border-radius:20px; border:1.5px solid var(--line); white-space:nowrap;
    transition:all .2s ease;
  }
  .catnav-scroll a.active{ background:var(--brand); color:#fff; border-color:var(--brand); }

  /* ---------------- SCROLL-REVEAL SECTIONS ---------------- */
  .menu-section{ padding:30px 0 6px; opacity:0; transform:translateY(18px); transition:opacity .5s ease, transform .5s ease; }
  .menu-section.in-view{ opacity:1; transform:translateY(0); }
  .menu-section h2{ font-size:17px; margin-bottom:14px; padding:0 20px; }

  .item-row{
    display:flex; gap:14px; align-items:center; padding:12px 20px; text-decoration:none; color:inherit;
    border-bottom:1px solid var(--line);
  }
  .item-row:active{ background:var(--paper); }
  .item-thumb{ width:64px; height:64px; border-radius:12px; background:var(--brand-tint); object-fit:cover; flex:none; }
  .item-info{ flex:1; min-width:0; }
  .item-name{ font-size:14.5px; font-weight:700; margin-bottom:3px; }
  .item-desc{ font-size:12px; color:var(--ink-soft); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-bottom:4px; }
  .item-price{ font-size:13.5px; font-weight:700; }
  .item-price .tag{ font-weight:600; font-size:9.5px; color:var(--ink-soft); background:var(--brand-tint); padding:2px 7px; border-radius:20px; margin-left:6px; }

  .empty{ text-align:center; padding:60px 20px; color:var(--ink-soft); }
  footer.shopfoot{ text-align:center; padding:30px 20px 50px; font-size:12px; color:var(--ink-soft); }

  /* ---------------- RESPONSIVE ---------------- */
  @media (max-width:520px){
    .hero{ height:min(46vh, 300px); min-height:230px; }
    .signature-stage{ width:110px; height:110px; right:14px; }
    .signature-plate{ width:96px; height:96px; }
    .vendor-card{ margin:-38px 14px 0; padding:16px; }
    .vendor-card h1{ font-size:18px; }
    .wrap{ padding:0 14px; }
    .catnav-scroll{ padding:12px 14px; }
    .menu-section h2{ padding:0 14px; }
    .item-row{ padding:11px 14px; gap:11px; }
    .item-thumb{ width:56px; height:56px; }
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
        <?php if ($signature['image_url']): ?>
          <img src="/<?= htmlspecialchars($signature['image_url']) ?>" alt="<?= htmlspecialchars($signature['name']) ?>">
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="hero-inner"></div>
</div>

<div class="vendor-card">
  <h1><?= htmlspecialchars($business['business_name']) ?></h1>
  <div class="vendor-meta">
    <span><span class="status-dot"></span>Open now</span>
    <span class="cat-pill"><?= htmlspecialchars($business['category'] ?? 'Food') ?></span>
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
    <div class="empty">This shop hasn't listed any items yet.</div>
  <?php else: ?>
    <?php foreach ($byCategory as $cat => $items): $catId = 'cat-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($cat)); ?>
      <section class="menu-section" id="<?= $catId ?>">
        <h2><?= htmlspecialchars($cat) ?></h2>
        <?php foreach ($items as $p): ?>
          <a class="item-row" href="/customer/product.php?id=<?= (int) $p['id'] ?>">
            <?php if ($p['image_url']): ?>
              <img class="item-thumb" src="/<?= htmlspecialchars($p['image_url']) ?>" alt="">
            <?php else: ?>
              <div class="item-thumb"></div>
            <?php endif; ?>
            <div class="item-info">
              <div class="item-name"><?= htmlspecialchars($p['name']) ?></div>
              <?php if (!empty($p['description'])): ?>
                <div class="item-desc"><?= htmlspecialchars($p['description']) ?></div>
              <?php endif; ?>
              <div class="item-price">
                ₦<?= number_format((float) $p['price'], 2) ?>
                <?php if ($p['pricing_mode'] === 'negotiable'): ?><span class="tag">negotiable</span><?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  <?php endif; ?>

  <footer class="shopfoot">Powered by Vendorly</footer>
</div>

<script>
// Scroll-spy: highlight the active category tab as the user scrolls through sections,
// and smooth-scroll (accounting for the sticky nav height) when a tab is tapped.
(function(){
  const sections = document.querySelectorAll('.menu-section');
  const navLinks = document.querySelectorAll('#catnav a');
  const stickyNav = document.querySelector('.catnav-sticky');

  if (!sections.length) return;

  function setActive(id){
    navLinks.forEach(a => a.classList.toggle('active', a.dataset.target === id));
  }

  const spyObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if (entry.isIntersecting) setActive(entry.target.id);
    });
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

  // Scroll-reveal: fade/slide each section up as it enters the viewport.
  const revealObserver = new IntersectionObserver((entries)=>{
    entries.forEach(entry=>{
      if (entry.isIntersecting){
        entry.target.classList.add('in-view');
        revealObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });
  sections.forEach(s => revealObserver.observe(s));
})();
</script>
</body>
</html>
