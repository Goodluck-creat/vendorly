<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_login();

$stmt = db()->prepare(
    "SELECT o.*, b.business_name, b.slug,
     (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
     FROM orders o JOIN businesses b ON b.id = o.business_id
     WHERE o.customer_id = :cid AND o.status != 'draft'
     ORDER BY o.created_at DESC"
);
$stmt->execute(['cid' => current_user_id()]);
$orders = $stmt->fetchAll();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$statusMeta = [
    'pending_acceptance' => ['label' => 'Awaiting confirmation', 'class' => 'pending'],
    'accepted'           => ['label' => 'Accepted',             'class' => 'accepted'],
    'awaiting_delivery'  => ['label' => 'Awaiting delivery',    'class' => 'accepted'],
    'in_transit'         => ['label' => 'On the way',           'class' => 'accepted'],
    'delivered'          => ['label' => 'Delivered',            'class' => 'delivered'],
    'cancelled'          => ['label' => 'Cancelled',            'class' => 'cancelled'],
    'negotiating'        => ['label' => 'Negotiating',          'class' => 'pending'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My orders — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1{ font-family:'Sora',sans-serif; }
  a{ text-decoration:none; color:inherit; }
  .icon{ width:20px; height:20px; vertical-align:middle; }

  .wrap{ max-width:600px; margin:0 auto; padding:28px 20px 60px; }
  .top-link{ display:inline-flex; align-items:center; gap:6px; color:var(--brand); font-size:13.5px; font-weight:600; }
  .top-link .icon{ width:16px; height:16px; }
  h1{ font-size:22px; margin:16px 0 20px; }

  .flash{ display:flex; align-items:center; gap:8px; background:#DEF2E6; color:#1F7A4C; padding:11px 15px; border-radius:9px; font-size:13.5px; margin-bottom:16px; }
  .flash .icon{ width:16px; height:16px; }

  .ocard{ display:block; background:#fff; border:1px solid var(--line); border-radius:13px; padding:14px 16px; margin-bottom:10px; }
  .orow{ display:flex; justify-content:space-between; align-items:flex-start; gap:10px; }
  .ocode{ font-family:'Sora',sans-serif; font-weight:700; font-size:14px; }
  .obiz{ font-size:12.5px; color:var(--ink-soft); margin-top:2px; }
  .oprice{ font-weight:700; font-size:14.5px; white-space:nowrap; }
  .status-pill{ font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:20px; display:inline-block; margin-top:8px; }
  .status-pill.pending{ background:#FCE7DA; color:#B1471B; }
  .status-pill.accepted{ background:var(--brand-tint); color:var(--brand); }
  .status-pill.delivered{ background:#DEF2E6; color:#1F7A4C; }
  .status-pill.cancelled{ background:#EDEDED; color:#777; }

  .empty{ text-align:center; padding:60px 20px; color:var(--ink-soft); }
</style>
</head>
<body>
<div class="wrap">
  <a href="/customer/home.php" class="top-link"><?= icon('arrow-left', 'icon') ?>Marketplace</a>
  <h1>My orders</h1>

  <?php if ($flash): ?><div class="flash"><?= icon('check-circle', 'icon') ?><?= htmlspecialchars($flash) ?></div><?php endif; ?>

  <?php if (empty($orders)): ?>
    <div class="empty"><?= icon('package') ?><div>No orders yet.</div></div>
  <?php else: ?>
    <?php foreach ($orders as $o): $sm = $statusMeta[$o['status']] ?? ['label' => $o['status'], 'class' => 'pending']; ?>
      <div class="ocard">
        <div class="orow">
          <div>
            <div class="ocode"><?= htmlspecialchars($o['order_code']) ?></div>
            <div class="obiz"><?= htmlspecialchars($o['business_name']) ?> &middot; <?= (int) $o['item_count'] ?> item<?= $o['item_count'] == 1 ? '' : 's' ?></div>
          </div>
          <div class="oprice">₦<?= number_format((float) $o['agreed_price'], 2) ?></div>
        </div>
        <span class="status-pill <?= $sm['class'] ?>"><?= htmlspecialchars($sm['label']) ?></span>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
