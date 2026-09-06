<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/public_nav.php';
start_secure_session();

if (!is_guest()) {
    header('Location: /customer/home.php');
    exit;
}

$errors = [];

$stmt = db()->prepare('SELECT email FROM users WHERE id = :id');
$stmt->execute(['id' => current_user_id()]);
$currentEmail = $stmt->fetchColumn();
$needsRealEmail = str_ends_with($currentEmail, '@no-email.vendorly.local');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $email = $needsRealEmail ? trim($_POST['email'] ?? '') : null;

    try {
        if ($needsRealEmail && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        claim_guest_account($password, $email);
        $_SESSION['flash'] = 'Your account is all set — everything from before is still here.';
        header('Location: /customer/home.php');
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
<title>Save your account — Vendorly</title>
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
</style>
</head>
<body>
<?= render_public_nav() ?>
<div class="wrap">
  <div class="card">
    <h1>Save your account</h1>
    <p class="sub">Set a password to keep your order and chat history and make it faster next time — nothing changes about what you already have.</p>
    <?php foreach ($errors as $error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <form method="POST">
      <?= csrf_field() ?>
      <?php if ($needsRealEmail): ?>
        <label>Email</label>
        <input type="email" name="email" required>
      <?php endif; ?>
      <label>Create a password</label>
      <input type="password" name="password" minlength="8" required>
      <button type="submit">Save my account</button>
    </form>
  </div>
</div>
</body>
</html>
