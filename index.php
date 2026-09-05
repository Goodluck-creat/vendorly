<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

// If already logged in, skip the landing page entirely and go straight to their dashboard
if (is_logged_in()) {
    header('Location: ' . role_home_path(current_role()));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Vendorly by GLOVATECH</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root{
    --brand:#0F3D3E;
    --brand-dark:#082627;
    --tint:#E4EEED;
    --accent:#FF7A45;
    --ink:#132323;
    --ink-soft:#5C6E6C;
    --paper:#F5F7F5;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; flex-direction:column;
    font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper);
  }
  .topbar{
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 28px;
  }
  .brand{ display:flex; align-items:center; gap:10px; }
  .brand .mark{ width:32px; height:32px; border-radius:9px; background:linear-gradient(135deg, var(--brand), var(--brand-dark)); }
  .brand span{ font-weight:800; font-size:16px; letter-spacing:-0.01em; }
  .navlinks a{
    text-decoration:none; font-size:13.5px; font-weight:600; margin-left:18px;
    color:var(--ink); padding:9px 16px; border-radius:8px;
  }
  .navlinks a.primary{ background:var(--brand); color:#fff; }

  .hero{
    flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
    text-align:center; padding:40px 20px 60px;
  }
  .eyebrow{
    font-family:'IBM Plex Mono',monospace; font-size:11.5px; letter-spacing:.12em; text-transform:uppercase;
    color:var(--ink-soft); background:var(--tint); padding:6px 14px; border-radius:20px; margin-bottom:20px;
  }
  h1{ font-size:clamp(28px,5vw,42px); margin:0 0 14px; max-width:640px; letter-spacing:-0.02em; line-height:1.15; }
  h1 span{ color:var(--brand); }
  .sub{ color:var(--ink-soft); font-size:16px; max-width:480px; margin:0 0 32px; line-height:1.6; }
  .ctas{ display:flex; gap:12px; flex-wrap:wrap; justify-content:center; }
  .btn{ text-decoration:none; padding:14px 26px; border-radius:10px; font-weight:700; font-size:14.5px; }
  .btn.primary{ background:var(--brand); color:#fff; }
  .btn.secondary{ background:#fff; color:var(--brand); border:1.5px solid var(--brand); }

  .pillars{
    display:flex; gap:20px; flex-wrap:wrap; justify-content:center; margin-top:56px; max-width:900px;
  }
  .pillar{
    background:#fff; border-radius:14px; padding:20px; width:220px; text-align:left;
    box-shadow:0 6px 20px rgba(15,61,62,0.06);
  }
  .pillar .dot{ width:10px; height:10px; border-radius:50%; background:var(--accent); margin-bottom:10px; }
  .pillar strong{ display:block; font-size:14px; margin-bottom:5px; }
  .pillar p{ font-size:12.5px; color:var(--ink-soft); margin:0; line-height:1.5; }

  footer{ text-align:center; padding:20px; font-size:12px; color:var(--ink-soft); }
</style>
</head>
<body>
  <div class="topbar">
    <div class="brand"><div class="mark"></div><span>Vendorly</span></div>
    <div class="navlinks">
      <a href="/login.php">Log in</a>
      <a href="/register.php" class="primary">Sign up</a>
    </div>
  </div>

  <div class="hero">
    <div class="eyebrow">by GLOVATECH</div>
    <h1>Buy, sell, and deliver — <span>all in one place.</span></h1>
    <p class="sub">Discover local businesses, negotiate a price directly, and get it delivered using PinPoint — a location every rider can actually find.</p>
    <div class="ctas">
      <a href="/register.php" class="btn primary">Create your account</a>
      <a href="/login.php" class="btn secondary">Log in</a>
    </div>

    <div class="pillars">
      <div class="pillar"><div class="dot"></div><strong>Marketplace</strong><p>Local businesses list products and chat directly with customers.</p></div>
      <div class="pillar"><div class="dot"></div><strong>PinPoint</strong><p>Every delivery uses a real, shareable location — not a vague description.</p></div>
      <div class="pillar"><div class="dot"></div><strong>Riders</strong><p>Independent riders pick up jobs, deliver, and get paid.</p></div>
    </div>
  </div>

  <footer>GLOVATECH &middot; Vendorly staging environment</footer>
</body>
</html>