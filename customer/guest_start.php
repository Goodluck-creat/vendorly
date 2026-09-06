<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/public_nav.php';
start_secure_session();

// Where to send them back to once they've identified themselves (e.g. the product they were viewing).
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/customer/home.php';
// Only ever redirect to a path on our own site — never trust this value as an open redirect target.
if (!str_starts_with($redirect, '/')) {
    $redirect = '/customer/home.php';
}

// Already identified (guest or registered)? No need to ask again — just continue.
if (is_logged_in()) {
    header('Location: ' . $redirect);
    exit;
}

$errors = [];
$old = ['name' => '', 'phone' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name']  = trim($_POST['name'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');

    try {
        start_guest_session($old['name'], $old['phone'], $old['email'] ?: null);
        header('Location: ' . $redirect);
        exit;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Continue — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:#F5F7F5; color:var(--ink); margin:0; }
  .wrap{ max-width:420px; margin:0 auto; padding:40px 20px; }
  .card{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:26px; }
  h1{ font-size:19px; margin:0 0 6px; }
  p.sub{ font-size:13.5px; color:var(--ink-soft); margin:0 0 20px; }
  label{ display:block; font-size:12.5px; font-weight:600; margin:14px 0 5px; }
  input{ width:100%; padding:10px 12px; border:1.5px solid var(--line); border-radius:9px; font-size:14px; box-sizing:border-box; }
  button{ width:100%; margin-top:20px; padding:12px; background:var(--brand); color:#fff; border:none; border-radius:9px; font-weight:700; font-size:14px; cursor:pointer; }
  .error{ background:#FCE7DA; color:#B1471B; padding:10px 12px; border-radius:8px; font-size:13px; margin-bottom:10px; }
  .altlink{ text-align:center; margin-top:16px; font-size:13px; }
  .altlink a{ color:var(--brand); text-decoration:none; font-weight:600; }
  .note{ font-size:12px; color:var(--ink-soft); margin-top:14px; text-align:center; }
</style>
</head>
<body>
<?= render_public_nav() ?>
<div class="wrap">
  <div class="card">
    <h1>Just need a little info</h1>
    <p class="sub">No password needed — this lets the seller reach you and lets you track your order.</p>
    <?php foreach ($errors as $error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
      <label>Your name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required>
      <label>Phone number</label>
      <input type="tel" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required>
      <label>Email (optional)</label>
      <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>">
      <button type="submit">Continue</button>
    </form>
    <div class="altlink">Already have an account? <a href="/login.php?redirect=<?= urlencode($redirect) ?>">Log in</a> instead</div>
    <div class="note">You can create a full account with a password anytime later — nothing here is lost.</div>
  </div>
</div>
</body>
</html>
