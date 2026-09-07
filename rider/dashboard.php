<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_role('rider');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard — Vendorly</title>
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

  .wrap{ max-width:520px; margin:0 auto; padding:32px 20px 60px; }
  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; }
  .wordmark{ display:flex; align-items:center; gap:9px; font-weight:800; font-size:16px; color:var(--ink); }
  .wordmark .chip{ width:24px; height:24px; border-radius:7px; background:linear-gradient(135deg,var(--brand),var(--brand-deep)); }
  .icon-btn{ display:flex; align-items:center; gap:6px; color:var(--ink-soft); font-size:13px; font-weight:600; }
  .icon-btn .icon{ width:17px; height:17px; }

  .card{ background:#fff; border:1px solid var(--line); border-radius:16px; padding:28px; text-align:center; animation: rise .5s ease both; box-shadow:0 8px 26px rgba(15,34,34,.05); }
  @keyframes rise{ from{ opacity:0; transform:translateY(12px); } to{ opacity:1; transform:translateY(0); } }
  @media (prefers-reduced-motion: reduce){ .card{ animation:none; } }

  .badge-icon{ width:52px; height:52px; border-radius:14px; background:var(--brand-tint); color:var(--brand); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
  h1{ font-size:20px; margin:0 0 8px; }
  p{ font-size:14px; color:var(--ink-soft); line-height:1.6; margin:0; }
  p strong{ color:var(--ink); }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div class="wordmark"><div class="chip"></div>Vendorly</div>
    <a href="/logout.php" class="icon-btn"><?= icon('log-out', 'icon') ?>Log out</a>
  </div>

  <div class="card">
    <div class="badge-icon"><?= icon('bike') ?></div>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></h1>
    <p>Rider tools are coming in <strong>Stage 5</strong> — accepting deliveries, navigation, and earnings will all live here.</p>
  </div>
</div>
</body>
</html>
