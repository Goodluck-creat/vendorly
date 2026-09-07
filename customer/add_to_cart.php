<?php
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();

$productId = (int) ($_REQUEST['product_id'] ?? 0);
$confirmed = isset($_REQUEST['confirm_switch']);

$stmt = db()->prepare("SELECT p.*, b.business_name, b.slug FROM products p JOIN businesses b ON b.id = p.business_id WHERE p.id = :id AND p.status = 'active' LIMIT 1");
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();
if (!$product) {
    header('Location: /customer/home.php');
    exit;
}

// Not identified yet? Get their name/phone first, then come straight back here.
if (!is_logged_in()) {
    $redirect = '/customer/add_to_cart.php?product_id=' . $productId . ($confirmed ? '&confirm_switch=1' : '');
    header('Location: /customer/guest_start.php?redirect=' . urlencode($redirect));
    exit;
}

$conflict = $confirmed ? null : get_conflicting_cart(current_user_id(), (int) $product['business_id']);

if ($conflict) {
    // Show the interstitial instead of adding anything yet.
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Switch shops? — Vendorly</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
      :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
      *{box-sizing:border-box;} body{ margin:0; font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink); }
      h1{ font-family:'Sora',sans-serif; }
      .icon{ width:20px; height:20px; vertical-align:middle; }
      .wrap{ max-width:420px; margin:0 auto; padding:60px 20px; }
      .card{ background:#fff; border:1px solid var(--line); border-radius:16px; padding:26px; text-align:center; box-shadow:0 10px 32px rgba(15,34,34,.06); }
      .badge-icon{ width:48px; height:48px; border-radius:13px; background:#FCE7DA; color:#B1471B; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
      h1{ font-size:19px; margin:0 0 10px; }
      p{ font-size:14px; color:var(--ink-soft); line-height:1.6; margin:0 0 22px; }
      p strong{ color:var(--ink); }
      .btn{ display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:13px; border-radius:10px; font-weight:700; font-size:14px; text-decoration:none; margin-bottom:10px; transition:all .16s ease; }
      .btn.primary{ background:var(--brand); color:#fff; }
      .btn.primary:hover{ background:var(--brand-deep); }
      .btn.outline{ background:transparent; color:var(--brand); border:1.5px solid var(--brand); }
      .btn.outline:hover{ background:var(--brand-tint); }
    </style>
    </head>
    <body>
    <div class="wrap">
      <div class="card">
        <div class="badge-icon"><?= icon('store') ?></div>
        <h1>You're already shopping at <?= htmlspecialchars($conflict['business_name']) ?></h1>
        <p>Starting an order with <strong><?= htmlspecialchars($product['business_name']) ?></strong> will save your <?= (int) $conflict['item_count'] ?>-item cart at <?= htmlspecialchars($conflict['business_name']) ?> as a draft — nothing is lost, you can come back to it anytime.</p>
        <a href="/customer/add_to_cart.php?product_id=<?= $productId ?>&confirm_switch=1" class="btn primary"><?= icon('check', 'icon') ?>Continue with <?= htmlspecialchars($product['business_name']) ?></a>
        <a href="/customer/cart.php?business_id=<?= (int) $conflict['business_id'] ?>" class="btn outline">Go back to my <?= htmlspecialchars($conflict['business_name']) ?> cart</a>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// No conflict, or switch was confirmed — add it.
try {
    add_to_cart(current_user_id(), $productId, 1);
    $_SESSION['flash'] = '"' . $product['name'] . '" added to your cart.';
} catch (Exception $e) {
    $_SESSION['flash'] = $e->getMessage();
}
header('Location: /customer/cart.php?business_id=' . (int) $product['business_id']);
exit;
