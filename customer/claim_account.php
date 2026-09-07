<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1{ font-family:'Sora',sans-serif; }
  .icon{ width:20px; height:20px; vertical-align:middle; }
  :focus-visible{ outline:2.5px solid var(--accent); outline-offset:2px; }

  .wrap{ max-width:420px; margin:0 auto; padding:44px 20px; }
  .wordmark{ display:flex; align-items:center; gap:9px; font-weight:800; font-size:16px; text-decoration:none; color:var(--ink); margin-bottom:24px; justify-content:center; }
  .wordmark .chip{ width:24px; height:24px; border-radius:7px; background:linear-gradient(135deg,var(--brand),var(--brand-deep)); }

  .card{ background:#fff; border:1px solid var(--line); border-radius:16px; padding:28px; animation: rise .5s ease both; box-shadow:0 10px 32px rgba(15,34,34,.06); }
  @keyframes rise{ from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }
  @media (prefers-reduced-motion: reduce){ .card{ animation:none; } }

  .badge-icon{ width:44px; height:44px; border-radius:12px; background:var(--brand-tint); color:var(--brand); display:flex; align-items:center; justify-content:center; margin-bottom:16px; }
  h1{ font-size:19px; margin:0 0 6px; }
  p.sub{ font-size:13.5px; color:var(--ink-soft); margin:0 0 22px; line-height:1.5; }

  label{ display:block; font-size:12.5px; font-weight:600; margin:14px 0 6px; }
  .inputwrap{ position:relative; }
  .inputwrap .icon{ position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--ink-soft); width:17px; height:17px; }
  input{
    width:100%; padding:11px 14px 11px 40px; border:1.5px solid var(--line); border-radius:10px;
    font-size:14.5px; font-family:inherit; transition:border-color .18s ease, box-shadow .18s ease;
  }
  input:focus{ border-color:var(--brand); box-shadow:0 0 0 3px rgba(15,61,62,0.1); outline:none; }

  button{ width:100%; margin-top:20px; padding:13px; background:var(--brand); color:#fff; border:none; border-radius:10px; font-weight:700; font-size:14.5px; cursor:pointer; transition:background .18s ease, transform .12s ease; }
  button:hover{ background:var(--brand-deep); }
  button:active{ transform:scale(0.98); }

  .error{ background:#FCE7DA; color:#B1471B; padding:11px 13px; border-radius:9px; font-size:13px; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
  .error .icon{ width:16px; height:16px; flex:none; }
</style>
</head>
<body>
<div class="wrap">
  <a href="/customer/home.php" class="wordmark"><div class="chip"></div>Vendorly</a>
  <div class="card">
    <div class="badge-icon"><?= icon('lock') ?></div>
    <h1>Save your account</h1>
    <p class="sub">Set a password to keep your order and chat history and make it faster next time — nothing changes about what you already have.</p>

    <?php foreach ($errors as $error): ?>
      <div class="error"><?= icon('x') ?><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <?php if ($needsRealEmail): ?>
        <label>Email</label>
        <div class="inputwrap"><?= icon('mail') ?><input type="email" name="email" required></div>
      <?php endif; ?>
      <label>Create a password</label>
      <div class="inputwrap"><?= icon('lock') ?><input type="password" name="password" minlength="8" required></div>

      <button type="submit">Save my account</button>
    </form>
  </div>
</div>
</body>
</html>
