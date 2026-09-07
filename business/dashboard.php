<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/business_layout.php';
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
    SUM(status = 'active') AS active_count, COUNT(*) AS total_count
    FROM products WHERE business_id = :bid");
$countStmt->execute(['bid' => $businessId]);
$counts = $countStmt->fetch();

$revStmt = db()->prepare("SELECT COUNT(*) AS order_count, COALESCE(SUM(agreed_price),0) AS revenue
    FROM orders WHERE business_id = :bid AND payment_status = 'paid'");
$revStmt->execute(['bid' => $businessId]);
$rev = $revStmt->fetch();
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
  <?= business_layout_styles() ?>

  .content-wrap{ max-width:920px; margin:0 auto; padding:32px 32px 60px; }
  h1{ font-size:25px; margin:0 0 6px; }
  .badge{ display:inline-flex; align-items:center; gap:6px; background:#FCE7DA; color:#B1471B; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:22px; }
  .badge .icon{ width:14px; height:14px; }

  .linkcard{ background:#fff; border:1.5px solid var(--brand); border-radius:14px; padding:18px 22px; margin-bottom:24px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
  .linklabel{ display:flex; align-items:center; gap:7px; font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-soft); margin-bottom:8px; }
  .linklabel .icon{ width:15px; height:15px; }
  .linkrow{ display:flex; gap:8px; flex:1; min-width:260px; }
  .linkrow input{ flex:1; padding:10px 13px; border:1.5px solid var(--line); border-radius:9px; font-size:13px; color:var(--brand); font-weight:600; background:var(--brand-tint); font-family:inherit; }
  .linkrow button{ display:flex; align-items:center; gap:6px; padding:10px 16px; background:var(--brand); color:#fff; border:none; border-radius:9px; font-weight:700; font-size:13px; cursor:pointer; transition:background .18s ease; }
  .linkrow button:hover{ background:var(--brand-deep); }
  .linkrow button .icon{ width:15px; height:15px; }
  .linknote{ font-size:11.5px; color:var(--ink-soft); flex-basis:100%; }

  .statgrid{ display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:8px; }
  .statcard{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px; display:flex; align-items:center; gap:14px; transition:transform .16s ease, box-shadow .16s ease; }
  .statcard:hover{ transform:translateY(-2px); box-shadow:0 10px 26px rgba(15,34,34,.08); }
  .statcard .icwrap{ width:44px; height:44px; border-radius:12px; background:var(--brand-tint); color:var(--brand); display:flex; align-items:center; justify-content:center; flex:none; }
  .statcard .val{ font-size:24px; font-weight:800; color:var(--ink); line-height:1.1; }
  .statcard .lbl{ font-size:12.5px; color:var(--ink-soft); }

  @media (max-width:700px){
    .content-wrap{ padding:24px 18px 50px; }
    .statgrid{ grid-template-columns:1fr; gap:12px; }
  }
</style>
</head>
<body>
<?= business_layout_head('dashboard', $business['business_name']) ?>
  <div class="content-wrap">
    <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></h1>
    <?php if ($accountStatus === 'pending'): ?>
      <div class="badge"><?= icon('clock', 'icon') ?>Pending verification</div>
    <?php endif; ?>

    <div class="linkcard">
      <div style="flex-basis:100%;">
        <div class="linklabel"><?= icon('link', 'icon') ?>Your shopfront link</div>
        <div class="linkrow">
          <input type="text" readonly value="<?= htmlspecialchars($shopfrontUrl) ?>" id="shopUrl" onclick="this.select()">
          <button type="button" onclick="copyShopLink()"><?= icon('copy', 'icon') ?>Copy</button>
        </div>
      </div>
      <div class="linknote">Share this link anywhere — anyone who opens it lands straight on your shop.</div>
    </div>

    <div class="statgrid">
      <div class="statcard">
        <div class="icwrap"><?= icon('check-circle') ?></div>
        <div><div class="val"><?= (int) ($counts['active_count'] ?? 0) ?></div><div class="lbl">Active items</div></div>
      </div>
      <div class="statcard">
        <div class="icwrap"><?= icon('package') ?></div>
        <div><div class="val"><?= (int) $rev['order_count'] ?></div><div class="lbl">Paid orders</div></div>
      </div>
      <div class="statcard">
        <div class="icwrap"><?= icon('star') ?></div>
        <div><div class="val">₦<?= number_format((float) $rev['revenue'], 0) ?></div><div class="lbl">Total revenue</div></div>
      </div>
    </div>
  </div>
<?= business_layout_foot() ?>
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
