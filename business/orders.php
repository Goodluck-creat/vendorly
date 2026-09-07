<?php
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/business_layout.php';
start_secure_session();
require_role('business');

$businessId = current_business_id();
$bizStmt = db()->prepare('SELECT * FROM businesses WHERE id = :id');
$bizStmt->execute(['id' => $businessId]);
$business = $bizStmt->fetch();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $check = db()->prepare("SELECT id FROM orders WHERE id = :id AND business_id = :bid AND status = 'pending_acceptance' LIMIT 1");
    $check->execute(['id' => $orderId, 'bid' => $businessId]);
    if ($check->fetch()) {
        $newStatus = $action === 'accept' ? 'accepted' : 'cancelled';
        db()->prepare('UPDATE orders SET status = :status WHERE id = :id')->execute(['status' => $newStatus, 'id' => $orderId]);
        $_SESSION['flash'] = $action === 'accept' ? 'Order accepted.' : 'Order declined.';
    }
    header('Location: /business/orders.php');
    exit;
}

function fetch_items_for_orders(array $orderIds): array {
    if (empty($orderIds)) return [];
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = db()->prepare("SELECT oi.order_id, oi.quantity, oi.unit_price_at_order, p.name
        FROM order_items oi JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id IN ($placeholders)");
    $stmt->execute($orderIds);
    $byOrder = [];
    foreach ($stmt->fetchAll() as $row) {
        $byOrder[$row['order_id']][] = $row;
    }
    return $byOrder;
}

$pendingStmt = db()->prepare(
    "SELECT o.*, u.name AS customer_name, u.phone AS customer_phone
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE o.business_id = :bid AND o.status = 'pending_acceptance'
     ORDER BY o.created_at DESC"
);
$pendingStmt->execute(['bid' => $businessId]);
$pending = $pendingStmt->fetchAll();
$pendingItems = fetch_items_for_orders(array_column($pending, 'id'));

$activeStmt = db()->prepare(
    "SELECT o.*, u.name AS customer_name, u.phone AS customer_phone
     FROM orders o JOIN users u ON u.id = o.customer_id
     WHERE o.business_id = :bid AND o.status IN ('accepted','awaiting_delivery','in_transit')
     ORDER BY o.created_at DESC"
);
$activeStmt->execute(['bid' => $businessId]);
$active = $activeStmt->fetchAll();
$activeItems = fetch_items_for_orders(array_column($active, 'id'));

$draftStmt = db()->prepare(
    "SELECT o.*, u.name AS customer_name, u.phone AS customer_phone, u.status AS customer_status,
     COUNT(oi.id) AS item_count, SUM(oi.quantity * oi.unit_price_at_order) AS total, MAX(o.updated_at) AS last_updated
     FROM orders o JOIN users u ON u.id = o.customer_id JOIN order_items oi ON oi.order_id = o.id
     WHERE o.business_id = :bid AND o.status = 'draft'
     GROUP BY o.id HAVING item_count > 0 ORDER BY last_updated DESC"
);
$draftStmt->execute(['bid' => $businessId]);
$drafts = $draftStmt->fetchAll();
$draftItems = fetch_items_for_orders(array_column($drafts, 'id'));

$statusLabels = ['accepted' => 'Accepted', 'awaiting_delivery' => 'Awaiting delivery', 'in_transit' => 'In transit'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Orders — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1,h2{ font-family:'Sora',sans-serif; }
  a{ text-decoration:none; }
  .icon{ width:20px; height:20px; vertical-align:middle; }
  <?= business_layout_styles() ?>

  .content-wrap{ max-width:920px; margin:0 auto; padding:32px 32px 60px; }
  h1{ font-size:25px; margin:0 0 20px; }
  h2{ font-size:14px; text-transform:uppercase; letter-spacing:.03em; color:var(--ink-soft); margin:28px 0 12px; }
  h2:first-of-type{ margin-top:0; }

  .flash{ display:flex; align-items:center; gap:8px; background:#DEF2E6; color:#1F7A4C; padding:11px 15px; border-radius:9px; font-size:13.5px; margin-bottom:16px; }
  .flash .icon{ width:16px; height:16px; }

  .ordergrid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr)); gap:14px; }
  .ocard{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:16px 18px; }
  .orow{ display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:10px; }
  .ocode{ font-family:'Sora',sans-serif; font-weight:700; font-size:14px; }
  .ocust{ font-size:12.5px; color:var(--ink-soft); margin-top:2px; }
  .oprice{ font-weight:800; font-size:15px; white-space:nowrap; }
  .status-pill{ font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:20px; display:inline-block; }
  .status-pill.accepted{ background:#DEF2E6; color:#1F7A4C; }
  .status-pill.awaiting_delivery,.status-pill.in_transit{ background:var(--brand-tint); color:var(--brand); }
  .status-pill.guest{ background:#FCE7DA; color:#B1471B; }
  .status-pill.paid{ background:#DEF2E6; color:#1F7A4C; }

  .itemlist{ border-top:1px dashed var(--line); padding-top:10px; margin-top:2px; }
  .itemrow{ display:flex; justify-content:space-between; font-size:12.5px; color:var(--ink-soft); margin-bottom:4px; }
  .itemrow strong{ color:var(--ink); font-weight:600; }

  .oactions{ display:flex; gap:8px; margin-top:12px; }
  .btn{ display:flex; align-items:center; justify-content:center; gap:6px; flex:1; padding:9px; border-radius:9px; font-weight:700; font-size:12.5px; cursor:pointer; border:none; transition:all .16s ease; }
  .btn.primary{ background:var(--brand); color:#fff; }
  .btn.primary:hover{ background:var(--brand-deep); }
  .btn.danger{ background:transparent; color:#B1471B; border:1.5px solid #B1471B; }
  .btn.danger:hover{ background:#FCE7DA; }
  .btn.call{ background:transparent; color:var(--brand); border:1.5px solid var(--brand); text-decoration:none; }
  .btn.call:hover{ background:var(--brand-tint); }

  .empty{ text-align:center; padding:30px 20px; color:var(--ink-soft); font-size:13px; background:#fff; border:1px dashed var(--line); border-radius:13px; }
  .draftnote{ font-size:11.5px; color:var(--ink-soft); margin-top:8px; }

  @media (max-width:700px){ .content-wrap{ padding:24px 18px 50px; } .ordergrid{ grid-template-columns:1fr; } }
</style>
</head>
<body>
<?= business_layout_head('orders', $business['business_name']) ?>
  <div class="content-wrap">
    <h1>Orders</h1>
    <?php if ($flash): ?><div class="flash"><?= icon('check-circle', 'icon') ?><?= htmlspecialchars($flash) ?></div><?php endif; ?>

    <h2>New orders (<?= count($pending) ?>)</h2>
    <?php if (empty($pending)): ?>
      <div class="empty">No new orders waiting right now.</div>
    <?php else: ?>
      <div class="ordergrid">
        <?php foreach ($pending as $o): ?>
          <div class="ocard">
            <div class="orow">
              <div><div class="ocode"><?= htmlspecialchars($o['order_code']) ?></div><div class="ocust"><?= htmlspecialchars($o['customer_name']) ?></div></div>
              <div><div class="oprice">₦<?= number_format((float) $o['agreed_price'], 2) ?></div><span class="status-pill paid" style="margin-top:4px;display:inline-block;"><?= $o['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?></span></div>
            </div>
            <div class="itemlist">
              <?php foreach ($pendingItems[$o['id']] ?? [] as $item): ?>
                <div class="itemrow"><span><strong><?= (int) $item['quantity'] ?>&times;</strong> <?= htmlspecialchars($item['name']) ?></span><span>₦<?= number_format($item['quantity'] * $item['unit_price_at_order'], 2) ?></span></div>
              <?php endforeach; ?>
            </div>
            <div class="oactions">
              <form method="POST" style="flex:1;"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="action" value="accept"><button type="submit" class="btn primary"><?= icon('check', 'icon') ?>Accept</button></form>
              <form method="POST" style="flex:1;"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="action" value="decline"><button type="submit" class="btn danger"><?= icon('x', 'icon') ?>Decline</button></form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2>Active orders (<?= count($active) ?>)</h2>
    <?php if (empty($active)): ?>
      <div class="empty">No active orders in progress.</div>
    <?php else: ?>
      <div class="ordergrid">
        <?php foreach ($active as $o): ?>
          <div class="ocard">
            <div class="orow">
              <div><div class="ocode"><?= htmlspecialchars($o['order_code']) ?></div><div class="ocust"><?= htmlspecialchars($o['customer_name']) ?></div></div>
              <div class="oprice">₦<?= number_format((float) $o['agreed_price'], 2) ?></div>
            </div>
            <div class="itemlist">
              <?php foreach ($activeItems[$o['id']] ?? [] as $item): ?>
                <div class="itemrow"><span><strong><?= (int) $item['quantity'] ?>&times;</strong> <?= htmlspecialchars($item['name']) ?></span><span>₦<?= number_format($item['quantity'] * $item['unit_price_at_order'], 2) ?></span></div>
              <?php endforeach; ?>
            </div>
            <span class="status-pill <?= htmlspecialchars($o['status']) ?>" style="margin-top:10px;display:inline-block;"><?= htmlspecialchars($statusLabels[$o['status']] ?? $o['status']) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2>Unfinished carts (<?= count($drafts) ?>)</h2>
    <?php if (empty($drafts)): ?>
      <div class="empty">No abandoned carts right now.</div>
    <?php else: ?>
      <div class="ordergrid">
        <?php foreach ($drafts as $d): ?>
          <div class="ocard">
            <div class="orow">
              <div>
                <div class="ocust" style="font-weight:700;color:var(--ink);"><?= htmlspecialchars($d['customer_name']) ?></div>
                <?php if ($d['customer_status'] === 'guest'): ?><span class="status-pill guest">Guest</span><?php endif; ?>
              </div>
              <a href="tel:<?= htmlspecialchars($d['customer_phone']) ?>" class="btn call" style="flex:none; padding:9px 14px;"><?= icon('phone', 'icon') ?><?= htmlspecialchars($d['customer_phone']) ?></a>
            </div>
            <div class="itemlist">
              <?php foreach ($draftItems[$d['id']] ?? [] as $item): ?>
                <div class="itemrow"><span><strong><?= (int) $item['quantity'] ?>&times;</strong> <?= htmlspecialchars($item['name']) ?></span><span>₦<?= number_format($item['quantity'] * $item['unit_price_at_order'], 2) ?></span></div>
              <?php endforeach; ?>
            </div>
            <div class="draftnote">Never completed checkout — call to follow up.</div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?= business_layout_foot() ?>
</body>
</html>
