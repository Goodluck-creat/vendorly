<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_login();

$orderCode = $_GET['order_code'] ?? '';

$stmt = db()->prepare(
    "SELECT o.*, b.business_name, b.slug FROM orders o JOIN businesses b ON b.id = o.business_id
     WHERE o.order_code = :code AND o.customer_id = :cid LIMIT 1"
);
$stmt->execute(['code' => $orderCode, 'cid' => current_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /customer/orders.php');
    exit;
}

$itemsStmt = db()->prepare(
    "SELECT oi.*, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :oid"
);
$itemsStmt->execute(['oid' => $order['id']]);
$items = $itemsStmt->fetchAll();

// Tracking timeline steps, in order — mark each as reached based on current status.
$allStages = ['pending_acceptance' => 'Order placed', 'accepted' => 'Confirmed by shop', 'awaiting_delivery' => 'Preparing', 'in_transit' => 'On the way', 'delivered' => 'Delivered'];
$stageKeys = array_keys($allStages);
$currentIndex = array_search($order['status'], $stageKeys);
if ($currentIndex === false) $currentIndex = ($order['status'] === 'cancelled') ? -1 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?= htmlspecialchars($order['order_code']) ?> — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@600&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1{ font-family:'Sora',sans-serif; }
  a{ text-decoration:none; }
  .icon{ width:20px; height:20px; vertical-align:middle; }

  .wrap{ max-width:480px; margin:0 auto; padding:32px 20px 60px; }
  .successhead{ text-align:center; margin-bottom:24px; animation: rise .5s ease both; }
  @keyframes rise{ from{ opacity:0; transform:translateY(12px); } to{ opacity:1; transform:translateY(0); } }
  .check-circle{ width:56px; height:56px; border-radius:50%; background:#DEF2E6; color:#1F7A4C; display:flex; align-items:center; justify-content:center; margin:0 auto 14px; }
  .successhead h1{ font-size:20px; margin:0 0 4px; }
  .successhead p{ font-size:13px; color:var(--ink-soft); margin:0; }
  .ocode{ font-family:'IBM Plex Mono',monospace; font-weight:600; background:var(--brand-tint); color:var(--brand); padding:6px 14px; border-radius:8px; display:inline-block; margin-top:10px; letter-spacing:.03em; }

  .card{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:20px; margin-bottom:16px; }
  .card h2{ font-size:13px; text-transform:uppercase; letter-spacing:.03em; color:var(--ink-soft); margin:0 0 14px; }

  .track{ display:flex; justify-content:space-between; position:relative; margin-bottom:6px; }
  .track::before{ content:''; position:absolute; top:11px; left:5%; right:5%; height:2px; background:var(--line); z-index:0; }
  .track-step{ display:flex; flex-direction:column; align-items:center; gap:6px; flex:1; position:relative; z-index:1; }
  .track-dot{ width:22px; height:22px; border-radius:50%; background:#fff; border:2px solid var(--line); display:flex; align-items:center; justify-content:center; }
  .track-step.done .track-dot{ background:var(--brand); border-color:var(--brand); color:#fff; }
  .track-step.current .track-dot{ border-color:var(--accent); background:var(--accent); color:#fff; }
  .track-label{ font-size:9.5px; text-align:center; color:var(--ink-soft); max-width:60px; }
  .track-step.done .track-label, .track-step.current .track-label{ color:var(--ink); font-weight:600; }

  .item-row{ display:flex; justify-content:space-between; font-size:13.5px; padding:8px 0; border-bottom:1px solid var(--line); }
  .item-row:last-of-type{ border-bottom:none; }
  .sumrow{ display:flex; justify-content:space-between; font-size:16px; font-weight:800; padding-top:12px; margin-top:6px; border-top:1.5px dashed var(--line); }

  .meta-row{ display:flex; justify-content:space-between; font-size:13px; padding:6px 0; color:var(--ink-soft); }
  .meta-row strong{ color:var(--ink); }

  .btn{ display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:13px; background:var(--brand); color:#fff; border-radius:11px; font-weight:700; font-size:14.5px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="successhead">
    <div class="check-circle"><?= icon('check-circle') ?></div>
    <h1>Payment successful</h1>
    <p>Your order at <?= htmlspecialchars($order['business_name']) ?> is confirmed.</p>
    <div class="ocode"><?= htmlspecialchars($order['order_code']) ?></div>
  </div>

  <?php if ($currentIndex >= 0): ?>
  <div class="card">
    <h2>Tracking</h2>
    <div class="track">
      <?php foreach ($allStages as $key => $label): $i = array_search($key, $stageKeys); ?>
        <div class="track-step <?= $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : '') ?>">
          <div class="track-dot"><?= $i <= $currentIndex ? icon('check','icon') : '' ?></div>
          <div class="track-label"><?= htmlspecialchars($label) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Order details</h2>
    <?php foreach ($items as $item): ?>
      <div class="item-row"><span><?= (int) $item['quantity'] ?>&times; <?= htmlspecialchars($item['name']) ?></span><span>₦<?= number_format($item['quantity'] * $item['unit_price_at_order'], 2) ?></span></div>
    <?php endforeach; ?>
    <div class="sumrow"><span>Total paid</span><span>₦<?= number_format((float) $order['agreed_price'], 2) ?></span></div>
  </div>

  <div class="card">
    <h2>Payment</h2>
    <div class="meta-row"><span>Reference</span><strong><?= htmlspecialchars($order['payment_reference']) ?></strong></div>
    <div class="meta-row"><span>Paid on</span><strong><?= date('M j, Y &middot; g:i A', strtotime($order['paid_at'])) ?></strong></div>
  </div>

  <a href="/customer/orders.php" class="btn"><?= icon('package', 'icon') ?>View all my orders</a>
</div>
</body>
</html>
