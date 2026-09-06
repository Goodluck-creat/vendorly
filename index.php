<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

// If already logged in, skip the landing page entirely and go straight to their dashboard
if (is_logged_in()) {
    header('Location: ' . role_home_path(current_role()));
    exit;
}

// Sample marketplace content — this is a pre-launch preview, not live data yet.
// Once Stage 2 ships, this section pulls from the real `products` table.
$featured = [
    ['name' => 'iPhone 14 Pro (256GB)', 'seller' => 'Tech World', 'price' => '₦1,250,000'],
    ['name' => 'AirPods Pro',            'seller' => 'Tech World', 'price' => '₦180,000'],
    ['name' => 'Ankara Set',             'seller' => 'Style Haven', 'price' => '₦45,000'],
    ['name' => 'Non-stick Cookware Set', 'seller' => 'Home Essentials', 'price' => '₦65,000'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendorly — Local shops, real delivery</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@600&display=swap" rel="stylesheet">
<style>
  :root{
    --brand:#0F3D3E;
    --brand-deep:#082627;
    --brand-tint:#E4EEED;
    --coral:#FF7A45;
    --ink:#122222;
    --ink-soft:#5C6E6C;
    --paper:#F3F6F4;
    --line:#DCE5E2;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; color:var(--ink); background:#fff;
    font-family:'Inter',sans-serif; font-size:16px; line-height:1.55;
    -webkit-font-smoothing:antialiased;
  }
  h1,h2,h3{ font-family:'Sora',sans-serif; margin:0; letter-spacing:-0.01em; }
  a{ color:inherit; text-decoration:none; }
  :focus-visible{ outline:2.5px solid var(--coral); outline-offset:3px; }
  img,svg{ display:block; max-width:100%; }

  .wrap{ max-width:1200px; margin:0 auto; padding:0 28px; }

  /* ---------------- HEADER ---------------- */
  header.site{ border-bottom:1px solid var(--line); }
  .headerbar{ display:flex; align-items:center; gap:24px; padding:16px 0; }
  .wordmark{ display:flex; align-items:center; gap:9px; font-family:'Sora',sans-serif; font-weight:700; font-size:18px; flex:none; }
  .wordmark .chip{ width:26px; height:26px; border-radius:8px; background:linear-gradient(135deg,var(--brand),var(--brand-deep)); }
  nav.mainnav{ display:flex; gap:22px; font-size:14px; font-weight:600; color:var(--ink-soft); flex:none; }
  nav.mainnav a:hover{ color:var(--ink); }
  .searchbar{
    flex:1; display:flex; align-items:center; gap:8px; background:var(--paper);
    border:1px solid var(--line); border-radius:10px; padding:10px 14px; color:var(--ink-soft); font-size:14px; max-width:420px;
  }
  .searchbar svg{ flex:none; opacity:.6; }
  .headeractions{ display:flex; align-items:center; gap:14px; font-size:14px; font-weight:600; flex:none; margin-left:auto; }
  .btn{
    display:inline-flex; align-items:center; justify-content:center;
    font-weight:700; font-size:14.5px; padding:11px 20px; border-radius:9px; border:1.5px solid transparent; white-space:nowrap;
  }
  .btn.primary{ background:var(--brand); color:#fff; }
  .btn.ghost{ background:transparent; color:var(--brand); border-color:var(--brand); }
  .btn.small{ padding:8px 16px; font-size:13.5px; }

  /* ---------------- HERO BANNER ---------------- */
  .hero{
    background:linear-gradient(120deg, var(--brand) 0%, var(--brand-deep) 100%);
    color:#fff; border-radius:18px; margin:26px 0; padding:52px 40px;
    display:flex; align-items:center; justify-content:space-between; gap:30px; overflow:hidden; position:relative;
  }
  .hero-copy{ max-width:520px; position:relative; z-index:1; }
  .hero h1{ font-size:clamp(28px,4vw,40px); font-weight:800; line-height:1.15; margin-bottom:14px; }
  .hero p{ color:#CFE3E0; font-size:15.5px; max-width:44ch; margin:0 0 24px; }
  .hero-ctas{ display:flex; gap:12px; flex-wrap:wrap; }
  .btn.on-dark{ background:#fff; color:var(--brand); }
  .btn.on-dark.outline{ background:transparent; color:#fff; border-color:rgba(255,255,255,.5); }

  .hero-art{ flex:none; width:230px; position:relative; z-index:1; }
  .hero-art svg{ width:100%; height:auto; }

  /* ---------------- TRUST STRIP ---------------- */
  .trust-strip{ display:grid; grid-template-columns:repeat(3,1fr); gap:20px; padding:8px 0 40px; }
  .trust-item{ display:flex; align-items:center; gap:12px; }
  .trust-item .ic{ width:38px; height:38px; border-radius:10px; background:var(--brand-tint); color:var(--brand); display:flex; align-items:center; justify-content:center; flex:none; }
  .trust-item strong{ display:block; font-size:14px; }
  .trust-item span{ font-size:12.5px; color:var(--ink-soft); }

  /* ---------------- SECTION HEADINGS ---------------- */
  .section-head{ display:flex; align-items:baseline; justify-content:space-between; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
  .section-head h2{ font-size:22px; font-weight:700; }
  .section-head a{ font-size:13.5px; font-weight:600; color:var(--brand); }

  /* ---------------- FEATURED PRODUCTS ---------------- */
  .featured{ padding:16px 0 50px; }
  .product-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:18px; }
  .product-card{ border:1px solid var(--line); border-radius:14px; padding:14px; }
  .product-thumb{ aspect-ratio:1/0.85; border-radius:10px; background:linear-gradient(135deg,var(--brand-tint),#fff); margin-bottom:12px; }
  .product-card .seller{ font-size:11.5px; color:var(--ink-soft); margin-bottom:3px; }
  .product-card .pname{ font-size:14px; font-weight:600; margin-bottom:6px; line-height:1.35; }
  .product-card .prow{ display:flex; align-items:center; justify-content:space-between; margin-top:8px; }
  .product-card .price{ font-weight:700; font-size:14.5px; }
  .product-card .btn{ padding:7px 12px; font-size:12.5px; }

  /* ---------------- MID PROMO BANNER ---------------- */
  .promo{
    background:var(--paper); border-radius:18px; padding:40px; margin-bottom:50px;
    display:flex; align-items:center; justify-content:space-between; gap:30px; flex-wrap:wrap;
  }
  .promo-copy{ max-width:420px; }
  .promo h3{ font-size:22px; margin-bottom:10px; }
  .promo p{ color:var(--ink-soft); font-size:14.5px; margin:0 0 18px; }
  .promo-art{ flex:none; width:180px; }

  /* ---------------- DUAL BANNERS ---------------- */
  .dual{ display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:56px; }
  .dual-card{ border-radius:16px; padding:30px; color:#fff; min-height:170px; display:flex; flex-direction:column; justify-content:flex-end; }
  .dual-card.a{ background:linear-gradient(135deg,#16504F,#0F3D3E); }
  .dual-card.b{ background:linear-gradient(135deg,#FF7A45,#E8622E); }
  .dual-card h3{ font-size:19px; margin-bottom:8px; }
  .dual-card p{ font-size:13.5px; opacity:.9; margin:0 0 14px; max-width:34ch; }

  /* ---------------- FOOTER ---------------- */
  footer.site{ background:var(--brand-deep); color:#CFE3E0; padding:48px 0 24px; }
  .footer-grid{ display:grid; grid-template-columns:1.4fr 1fr 1fr 1.3fr; gap:30px; padding-bottom:34px; }
  .footer-brand .wordmark{ color:#fff; }
  .footer-brand p{ font-size:13.5px; color:#9FC0BC; margin:14px 0 0; max-width:32ch; }
  .footer-col h4{ font-size:13px; text-transform:none; color:#fff; margin-bottom:14px; font-weight:700; }
  .footer-col a{ display:block; font-size:13.5px; color:#B7D2CE; margin-bottom:10px; }
  .footer-col a:hover{ color:#fff; }
  .newsletter{ display:flex; gap:8px; margin-top:14px; }
  .newsletter input{
    flex:1; padding:10px 12px; border-radius:8px; border:1px solid rgba(255,255,255,.2);
    background:rgba(255,255,255,.06); color:#fff; font-size:13.5px;
  }
  .newsletter input::placeholder{ color:#9FC0BC; }
  .footer-bottom{ border-top:1px solid rgba(255,255,255,.12); padding-top:20px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:10px; font-size:12.5px; color:#8FB3AF; }

  /* ---------------- RESPONSIVE ---------------- */
  @media (max-width: 980px){
    .product-grid{ grid-template-columns:repeat(2,1fr); }
    .footer-grid{ grid-template-columns:1fr 1fr; }
    .hero-art{ display:none; }
  }
  @media (max-width: 860px){
    nav.mainnav{ display:none; }
    .searchbar{ max-width:none; }
    .dual{ grid-template-columns:1fr; }
  }
  @media (max-width: 640px){
    .wrap{ padding:0 18px; }
    .headerbar{ flex-wrap:wrap; }
    .searchbar{ order:3; width:100%; max-width:none; }
    .hero{ padding:34px 24px; border-radius:14px; }
    .trust-strip{ grid-template-columns:1fr; gap:14px; }
    .product-grid{ grid-template-columns:repeat(2,1fr); gap:12px; }
    .promo{ flex-direction:column; text-align:center; padding:30px 22px; }
    .promo-copy{ max-width:none; }
    .promo-art{ order:-1; }
    .footer-grid{ grid-template-columns:1fr; gap:26px; }
    .footer-bottom{ flex-direction:column; align-items:flex-start; }
  }
</style>
</head>
<body>

<header class="site">
  <div class="wrap headerbar">
    <div class="wordmark"><div class="chip"></div>Vendorly</div>
    <nav class="mainnav">
      <a href="/register.php">Marketplace</a>
      <a href="/register.php">PinPoint</a>
      <a href="/register.php">Riders</a>
      <a href="/register.php">For businesses</a>
    </nav>
    <div class="searchbar">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
      Search products or businesses...
    </div>
    <div class="headeractions">
      <a href="/login.php">Log in</a>
      <a href="/register.php" class="btn primary small">Sign up</a>
    </div>
  </div>
</header>

<div class="wrap">
  <section class="hero">
    <div class="hero-copy">
      <h1>Everything local, delivered right to your door.</h1>
      <p>Browse real shops nearby, negotiate a price directly with the seller, and track delivery to a PinPoint location a rider can actually find.</p>
      <div class="hero-ctas">
        <a href="/register.php" class="btn on-dark">Start browsing</a>
        <a href="/register.php" class="btn on-dark outline">List your business</a>
      </div>
    </div>
    <div class="hero-art">
      <svg viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="100" cy="100" r="92" stroke="rgba(255,255,255,.18)" stroke-width="2"/>
        <path d="M40 140 C 70 60, 120 160, 160 60" stroke="#FF7A45" stroke-width="3" stroke-dasharray="1 9" stroke-linecap="round"/>
        <circle cx="40" cy="140" r="9" fill="#fff"/>
        <path d="M151 45 C 140 45 132 54 132 65 C132 79 151 96 151 96 C151 96 170 79 170 65 C170 54 162 45 151 45 Z" fill="#FF7A45"/>
      </svg>
    </div>
  </section>

  <div class="trust-strip">
    <div class="trust-item">
      <div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
      <div><strong>Chat & negotiate</strong><span>Talk to the seller directly, no middleman</span></div>
    </div>
    <div class="trust-item">
      <div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6-7-11a7 7 0 0114 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></div>
      <div><strong>PinPoint delivery</strong><span>A real location, not a rough description</span></div>
    </div>
    <div class="trust-item">
      <div class="ic"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="6" cy="18" r="2.5"/><circle cx="17" cy="18" r="2.5"/><path d="M6 18V9h7l3 4h3v5"/></svg></div>
      <div><strong>Independent riders</strong><span>Local riders who pick up and deliver</span></div>
    </div>
  </div>

  <section class="featured">
    <div class="section-head">
      <h2>Featured on Vendorly</h2>
      <a href="/register.php">See more</a>
    </div>
    <div class="product-grid">
      <?php foreach ($featured as $item): ?>
        <div class="product-card">
          <div class="product-thumb"></div>
          <div class="seller"><?= htmlspecialchars($item['seller']) ?></div>
          <div class="pname"><?= htmlspecialchars($item['name']) ?></div>
          <div class="prow">
            <span class="price"><?= htmlspecialchars($item['price']) ?></span>
            <a href="/register.php" class="btn ghost">View</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="promo">
    <div class="promo-copy">
      <h3>Have something to sell?</h3>
      <p>Open a storefront on Vendorly, list your products, and start chatting with customers nearby — free to set up.</p>
      <a href="/register.php" class="btn primary">Register your business</a>
    </div>
    <div class="promo-art">
      <svg viewBox="0 0 160 140" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="20" y="60" width="120" height="60" rx="10" fill="#E4EEED"/>
        <path d="M20 60 L60 25 L100 60" fill="none" stroke="#0F3D3E" stroke-width="4" stroke-linejoin="round"/>
        <rect x="70" y="80" width="30" height="40" rx="3" fill="#0F3D3E"/>
        <circle cx="120" cy="40" r="14" fill="#FF7A45"/>
      </svg>
    </div>
  </section>

  <div class="dual">
    <div class="dual-card a">
      <h3>New on Vendorly</h3>
      <p>Businesses that just opened their storefront this week.</p>
      <a href="/register.php" class="btn on-dark small" style="width:fit-content;">Browse new shops</a>
    </div>
    <div class="dual-card b">
      <h3>Trending nearby</h3>
      <p>The products getting the most orders in your area right now.</p>
      <a href="/register.php" class="btn on-dark small" style="width:fit-content;">See what's trending</a>
    </div>
  </div>
</div>

<footer class="site">
  <div class="wrap">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="wordmark"><div class="chip"></div>Vendorly</div>
        <p>A GLOVATECH product connecting local businesses, customers, and independent delivery riders — currently in staging.</p>
      </div>
      <div class="footer-col">
        <h4>Marketplace</h4>
        <a href="/register.php">Browse shops</a>
        <a href="/register.php">Categories</a>
        <a href="/register.php">How negotiation works</a>
      </div>
      <div class="footer-col">
        <h4>Company</h4>
        <a href="/register.php">For businesses</a>
        <a href="/register.php">For riders</a>
        <a href="/register.php">About PinPoint</a>
      </div>
      <div class="footer-col">
        <h4>Stay in the loop</h4>
        <div class="newsletter">
          <input type="email" placeholder="Your email">
          <a href="#" class="btn on-dark small">Join</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> GLOVATECH. All rights reserved.</span>
      <span>Vendorly staging environment</span>
    </div>
  </div>
</footer>

</body>
</html>