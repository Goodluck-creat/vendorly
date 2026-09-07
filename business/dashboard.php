<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_role('business');

$businessId = current_business_id();
$stmt = db()->prepare('SELECT status FROM users WHERE id = :id');
$stmt->execute(['id' => current_user_id()]);
$accountStatus = $stmt->fetchColumn();

$bizStmt = db()->prepare('SELECT * FROM businesses WHERE id = :id');
$bizStmt->execute(['id' => $businessId]);
$business = ensure_business_has_slug($bizStmt->fetch());
$shopfrontUrl = business_shopfront_url($business);

$countStmt = db()->prepare("SELECT
    SUM(status = 'active') AS active_count,
    COUNT(*) AS total_count
    FROM products WHERE business_id = :bid");
$countStmt->execute(['bid' => $businessId]);
$counts = $countStmt->fetch();
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

  .wrap{ max-width:640px; margin:0 auto; padding:32px 20px 60px; }
  .topbar{ display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; }
  .wordmark{ display:flex; align-items:center; gap:9px; font-weight:800; font-size:16px; color:var(--ink); }
  .wordmark .chip{ width:24px; height:24px; border-radius:7px; background:linear-gradient(135deg,var(--brand),var(--brand-deep)); }
  .icon-btn{ display:flex; align-items:center; gap:6px; color:var(--ink-soft); font-size:13px; font-weight:600; }
  .icon-btn .icon{ width:17px; height:17px; }

  h1{ font-size:23px; margin:0 0 6px; animation: rise .5s ease both; }
  .badge{ display:inline-flex; align-items:center; gap:6px; background:#FCE7DA; color:#B1471B; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:20px; }
  .badge .icon{ width:14px; height:14px; }

  @keyframes rise{ from{ opacity:0; transform:translateY(10px); } to{ opacity:1; transform:translateY(0); } }
  .stagger{ animation: rise .5s ease both; }
  @media (prefers-reduced-motion: reduce){ h1,.stagger{ animation:none; } }

  .linkcard{ background:#fff; border:1.5px solid var(--brand); border-radius:14px; padding:18px; margin-bottom:22px; animation-delay:.05s; }
  .linklabel{ display:flex; align-items:center; gap:7px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-soft); margin-bottom:10px; }
  .linklabel .icon{ width:15px; height:15px; }
  .linkrow{ display:flex; gap:8px; }
  .linkrow input{ flex:1; padding:10px 13px; border:1.5px solid var(--line); border-radius:9px; font-size:13px; color:var(--brand); font-weight:600; background:var(--brand-tint); font-family:inherit; }
  .linkrow button{ display:flex; align-items:center; gap:6px; padding:10px 16px; background:var(--brand); color:#fff; border:none; border-radius:9px; font-weight:700; font-size:13px; cursor:pointer; transition:background .18s ease; }
  .linkrow button:hover{ background:var(--brand-deep); }
  .linkrow button .icon{ width:15px; height:15px; }
  .linknote{ font-size:11.5px; color:var(--ink-soft); margin-top:9px; }

  .statgrid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:22px; animation-delay:.1s; }
  .statcard{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:18px; display:flex; align-items:center; gap:12px; }
  .statcard .icwrap{ width:38px; height:38px; border-radius:10px; background:var(--brand-tint); color:var(--brand); display:flex; align-items:center; justify-content:center; flex:none; }
  .statcard .val{ font-size:22px; font-weight:800; color:var(--ink); line-height:1.1; }
  .statcard .lbl{ font-size:12px; color:var(--ink-soft); }

  .btn{ display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:14px; background:var(--brand); color:#fff; border-radius:11px; font-weight:700; font-size:14.5px; transition:background .18s ease, transform .12s ease; animation-delay:.15s; }
  .btn:hover{ background:var(--brand-deep); }
  .btn:active{ transform:scale(0.98); }
  .btn .icon{ width:18px; height:18px; }

  a.logout{ display:flex; align-items:center; gap:6px; justify-content:center; margin-top:20px; font-size:13px; color:var(--ink-soft); }
  a.logout .icon{ width:15px; height:15px; }

  @media (max-width:420px){ .wrap{ padding:24px 16px 50px; } .statgrid{ gap:10px; } .statcard{ padding:14px; } }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div class="wordmark"><div class="chip"></div>Vendorly</div>
    <a href="/logout.php" class="icon-btn"><?= icon('log-out', 'icon') ?>Log out</a>
  </div>

  <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></h1>
  <?php if ($accountStatus === 'pending'): ?>
    <div class="badge"><?= icon('clock', 'icon') ?>Pending verification</div>
  <?php endif; ?>

  <div class="linkcard stagger">
    <div class="linklabel"><?= icon('link', 'icon') ?>Your shopfront link</div>
    <div class="linkrow">
      <input type="text" readonly value="<?= htmlspecialchars($shopfrontUrl) ?>" id="shopUrl" onclick="this.select()">
      <button type="button" onclick="copyShopLink()"><?= icon('copy', 'icon') ?>Copy</button>
    </div>
    <div class="linknote">Share this link anywhere — anyone who opens it lands straight on your shop.</div>
  </div>

  <div class="statgrid stagger">
    <div class="statcard">
      <div class="icwrap"><?= icon('check-circle') ?></div>
      <div><div class="val"><?= (int) ($counts['active_count'] ?? 0) ?></div><div class="lbl">Active items</div></div>
    </div>
    <div class="statcard">
      <div class="icwrap"><?= icon('package') ?></div>
      <div><div class="val"><?= (int) ($counts['total_count'] ?? 0) ?></div><div class="lbl">Total items</div></div>
    </div>
  </div>

  <a href="/business/products.php" class="btn stagger"><?= icon('store', 'icon') ?>Manage products</a>
</div>
<script>
function copyShopLink(){
  const input = document.getElementById('shopUrl');
  input.select();
  navigator.clipboard.writeText(input.value).then(()=>{
    const btn = event.currentTarget;
    const original = btn.innerHTML;
    btn.textContent = 'Copied!';
    setTimeout(()=>{ btn.innerHTML = original; }, 1500);
  });
}
</script>
</body>
</html>
