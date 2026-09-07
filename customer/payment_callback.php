<?php
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_login();

$reference = $_GET['reference'] ?? $_GET['trxref'] ?? '';

if (!$reference) {
    header('Location: /customer/home.php');
    exit;
}

$stmt = db()->prepare("SELECT * FROM orders WHERE payment_reference = :ref AND customer_id = :cid LIMIT 1");
$stmt->execute(['ref' => $reference, 'cid' => current_user_id()]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /customer/home.php');
    exit;
}

// Never trust the redirect alone — always re-verify server-side with Paystack directly.
$verified = paystack_verify($reference);

if ($verified && $order['status'] === 'draft') {
    checkout_cart((int) $order['id'], current_user_id()); // flips draft -> pending_acceptance, stamps agreed_price
    db()->prepare("UPDATE orders SET payment_status = 'paid', paid_at = NOW() WHERE id = :id")
        ->execute(['id' => $order['id']]);
    header('Location: /customer/receipt.php?order_code=' . urlencode($order['order_code']));
    exit;
}

if ($verified && $order['payment_status'] === 'paid') {
    // Already processed (e.g. page refreshed) — just show the receipt again, don't double-charge or double-submit.
    header('Location: /customer/receipt.php?order_code=' . urlencode($order['order_code']));
    exit;
}

// Payment failed or couldn't be verified.
db()->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = :id")->execute(['id' => $order['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment issue — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;} body{ margin:0; font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink); }
  h1{ font-family:'Sora',sans-serif; }
  .icon{ width:20px; height:20px; vertical-align:middle; }
  .wrap{ max-width:420px; margin:0 auto; padding:60px 20px; text-align:center; }
  .badge-icon{ width:48px; height:48px; border-radius:13px; background:#FCE7DA; color:#B1471B; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
  h1{ font-size:19px; margin:0 0 10px; }
  p{ font-size:14px; color:var(--ink-soft); line-height:1.6; margin:0 0 22px; }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:12px 22px; background:var(--brand); color:#fff; border-radius:10px; font-weight:700; text-decoration:none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="badge-icon"><?= icon('x') ?></div>
  <h1>Payment didn't go through</h1>
  <p>Nothing was charged. Your cart is still saved — you can try paying again.</p>
  <a href="/customer/cart.php?business_id=<?= (int) $order['business_id'] ?>" class="btn"><?= icon('arrow-left', 'icon') ?>Back to cart</a>
</div>
</body>
</html>
