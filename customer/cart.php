<?php
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/paystack.php';
start_secure_session();
require_login();

$businessId = (int) ($_GET['business_id'] ?? get_active_cart_business_id() ?? 0);
if (!$businessId) {
    header('Location: /customer/home.php');
    exit;
}

$bizStmt = db()->prepare('SELECT * FROM businesses WHERE id = :id');
$bizStmt->execute(['id' => $businessId]);
$business = $bizStmt->fetch();
if (!$business) {
    header('Location: /customer/home.php');
    exit;
}
$business = ensure_business_has_slug($business);

$errors = [];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    $cart = get_cart(current_user_id(), $businessId);

    if ($action === 'update' && $cart) {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $qty = (int) ($_POST['quantity'] ?? 0);
        update_cart_item_quantity((int) $cart['id'], $itemId, $qty);
        header('Location: /customer/cart.php?business_id=' . $businessId);
        exit;
    }

    if ($action === 'checkout' && $cart) {
        try {
            if (empty($cart['items'])) {
                throw new Exception('Your cart is empty.');
            }
            // Get the customer's email for Paystack (guests get an auto-generated one, which is fine —
            // Paystack just needs a valid-format email to send the receipt to, if they have a real one).
            $custStmt = db()->prepare('SELECT email FROM users WHERE id = :id');
            $custStmt->execute(['id' => current_user_id()]);
            $customerEmail = $custStmt->fetchColumn();

            $reference = 'VDL-' . $cart['id'] . '-' . bin2hex(random_bytes(6));
            db()->prepare('UPDATE orders SET payment_reference = :ref WHERE id = :id')
                ->execute(['ref' => $reference, 'id' => $cart['id']]);

            $callbackUrl = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/customer/payment_callback.php';
            $payUrl = paystack_initialize($customerEmail, $cart['total'], $reference, $callbackUrl);

            header('Location: ' . $payUrl);
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$cart = get_cart(current_user_id(), $businessId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your cart — <?= htmlspecialchars($business['business_name']) ?></title>
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

  .wrap{ max-width:560px; margin:0 auto; padding:26px 20px 90px; }
  .top-link{ display:inline-flex; align-items:center; gap:6px; color:var(--brand); font-size:13.5px; font-weight:600; }
  .top-link .icon{ width:16px; height:16px; }
  h1{ font-size:21px; margin:14px 0 4px; }
  .subtitle{ font-size:13px; color:var(--ink-soft); margin:0 0 20px; }

  .flash{ display:flex; align-items:center; gap:8px; background:#DEF2E6; color:#1F7A4C; padding:11px 15px; border-radius:9px; font-size:13.5px; margin-bottom:16px; }
  .error{ display:flex; align-items:center; gap:8px; background:#FCE7DA; color:#B1471B; padding:11px 15px; border-radius:9px; font-size:13.5px; margin-bottom:16px; }
  .flash .icon, .error .icon{ width:16px; height:16px; }

  .item{ display:flex; gap:12px; align-items:center; background:#fff; border:1px solid var(--line); border-radius:13px; padding:13px; margin-bottom:10px; }
  .thumb{ width:52px; height:52px; border-radius:10px; background:var(--brand-tint); object-fit:cover; flex:none; }
  .iteminfo{ flex:1; min-width:0; }
  .itemname{ font-size:14px; font-weight:700; margin-bottom:2px; }
  .itemprice{ font-size:12.5px; color:var(--ink-soft); }
  .qtybox{ display:flex; align-items:center; gap:8px; flex:none; }
  .qtybox button{ width:28px; height:28px; border-radius:8px; border:1.5px solid var(--line); background:#fff; font-size:15px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
  .qtybox button:hover{ border-color:var(--brand); color:var(--brand); }
  .qtybox span{ font-size:13.5px; font-weight:700; min-width:16px; text-align:center; }

  .empty{ text-align:center; padding:60px 20px; color:var(--ink-soft); }
  .empty .icon{ width:36px; height:36px; margin-bottom:12px; opacity:.5; }

  .summary{ background:#fff; border:1px solid var(--line); border-radius:13px; padding:16px; margin-top:16px; }
  .sumrow{ display:flex; justify-content:space-between; font-size:14px; margin-bottom:6px; }
  .sumrow.total{ font-weight:800; font-size:16.5px; border-top:1px dashed var(--line); padding-top:10px; margin-top:8px; }

  .checkout-bar{ position:fixed; bottom:0; left:0; right:0; background:#fff; border-top:1px solid var(--line); padding:14px 20px; }
  .checkout-inner{ max-width:560px; margin:0 auto; }
  button.checkout-btn{
    display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:14px;
    background:var(--brand); color:#fff; border:none; border-radius:11px; font-weight:700; font-size:15px; cursor:pointer;
    transition:background .18s ease, transform .12s ease;
  }
  button.checkout-btn:hover{ background:var(--brand-deep); }
  button.checkout-btn:active{ transform:scale(0.98); }
</style>
</head>
<body>
<div class="wrap">
  <a href="/<?= htmlspecialchars($business['slug']) ?>" class="top-link"><?= icon('arrow-left', 'icon') ?> <?= htmlspecialchars($business['business_name']) ?></a>
  <h1>Your cart</h1>
  <p class="subtitle">Ordering from <?= htmlspecialchars($business['business_name']) ?></p>

  <?php if ($flash): ?><div class="flash"><?= icon('check-circle', 'icon') ?><?= htmlspecialchars($flash) ?></div><?php endif; ?>
  <?php foreach ($errors as $error): ?><div class="error"><?= icon('x', 'icon') ?><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

  <?php if (!$cart || empty($cart['items'])): ?>
    <div class="empty">
      <?= icon('package') ?>
      <div>Your cart is empty.</div>
    </div>
  <?php else: ?>
    <?php foreach ($cart['items'] as $item): ?>
      <div class="item">
        <?php if ($item['image_url']): ?>
          <img class="thumb" src="/<?= htmlspecialchars($item['image_url']) ?>" alt="">
        <?php else: ?>
          <div class="thumb"></div>
        <?php endif; ?>
        <div class="iteminfo">
          <div class="itemname"><?= htmlspecialchars($item['name']) ?></div>
          <div class="itemprice">₦<?= number_format((float) $item['unit_price_at_order'], 2) ?> each</div>
        </div>
        <form method="POST" class="qtybox">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
          <button type="submit" name="quantity" value="<?= (int) $item['quantity'] - 1 ?>"><?= $item['quantity'] == 1 ? icon('trash','icon') : '&minus;' ?></button>
          <span><?= (int) $item['quantity'] ?></span>
          <button type="submit" name="quantity" value="<?= (int) $item['quantity'] + 1 ?>">+</button>
        </form>
      </div>
    <?php endforeach; ?>

    <div class="summary">
      <div class="sumrow total"><span>Total</span><span>₦<?= number_format($cart['total'], 2) ?></span></div>
    </div>
  <?php endif; ?>
</div>

<?php if ($cart && !empty($cart['items'])): ?>
<div class="checkout-bar">
  <div class="checkout-inner">
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="checkout">
      <button type="submit" class="checkout-btn"><?= icon('lock', 'icon') ?>Pay ₦<?= number_format($cart['total'], 2) ?></button>
    </form>
  </div>
</div>
<?php endif; ?>
</body>
</html>
