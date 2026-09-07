<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';
start_secure_session();

$errors = [];
$oldEmail = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? null;
if ($redirect && !str_starts_with($redirect, '/')) {
    $redirect = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (too_many_attempts('login', 5, 300)) {
        $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $oldEmail = trim($_POST['email_or_phone'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $user = attempt_login($oldEmail, $password);
            if ($user === null) {
                $errors[] = 'Incorrect email/phone or password.';
            } else {
                login_session($user);
                header('Location: ' . ($redirect ?: role_home_path($user['role'])));
                exit;
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log in — Vendorly</title>
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

  .split{ min-height:100vh; display:grid; grid-template-columns:1fr 1fr; }
  .brandside{
    background:linear-gradient(150deg, var(--brand), var(--brand-deep));
    color:#fff; display:flex; flex-direction:column; justify-content:space-between;
    padding:44px 48px;
  }
  .wordmark{ display:flex; align-items:center; gap:10px; font-family:'Sora',sans-serif; font-weight:800; font-size:19px; text-decoration:none; color:#fff; }
  .wordmark .chip{ width:30px; height:30px; border-radius:9px; background:rgba(255,255,255,.18); }
  .brand-copy{ max-width:360px; animation: rise .6s ease both; }
  .brand-copy h1{ font-size:30px; line-height:1.2; margin-bottom:12px; }
  .brand-copy p{ font-size:14.5px; color:#CFE3E0; line-height:1.6; }
  .brand-foot{ font-size:12.5px; color:#9FC0BC; }

  .formside{ display:flex; align-items:center; justify-content:center; padding:40px 24px; }
  .formcard{ width:100%; max-width:380px; animation: rise .6s ease .1s both; }
  .formcard h2{ font-family:'Sora',sans-serif; font-size:23px; margin:0 0 6px; }
  .formcard .sub{ font-size:13.5px; color:var(--ink-soft); margin:0 0 26px; }

  label{ display:block; font-size:12.5px; font-weight:600; margin:16px 0 6px; }
  .inputwrap{ position:relative; }
  .inputwrap .icon{ position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--ink-soft); width:17px; height:17px; }
  input{
    width:100%; padding:12px 14px 12px 40px; border:1.5px solid var(--line); border-radius:10px;
    font-size:14.5px; font-family:inherit; transition:border-color .18s ease, box-shadow .18s ease;
  }
  input:focus{ border-color:var(--brand); box-shadow:0 0 0 3px rgba(15,61,62,0.1); outline:none; }

  button{
    width:100%; margin-top:22px; padding:13px; background:var(--brand); color:#fff; border:none;
    border-radius:10px; font-weight:700; font-size:14.5px; cursor:pointer;
    transition:background .18s ease, transform .12s ease;
  }
  button:hover{ background:var(--brand-deep); }
  button:active{ transform:scale(0.98); }

  .error{
    background:#FCE7DA; color:#B1471B; padding:11px 13px; border-radius:9px; font-size:13px;
    margin-bottom:14px; display:flex; align-items:center; gap:8px;
  }
  .error .icon{ width:16px; height:16px; flex:none; }

  .link{ text-align:center; margin-top:22px; font-size:13.5px; color:var(--ink-soft); }
  .link a{ color:var(--brand); font-weight:700; text-decoration:none; }

  @keyframes rise{ from{ opacity:0; transform:translateY(12px); } to{ opacity:1; transform:translateY(0); } }
  @media (prefers-reduced-motion: reduce){ .brand-copy, .formcard{ animation:none; } }

  @media (max-width:820px){
    .split{ grid-template-columns:1fr; }
    .brandside{ padding:32px 24px; }
    .brand-copy{ display:none; }
    .brand-foot{ display:none; }
  }
  @media (max-width:480px){
    .formside{ padding:28px 18px; }
  }
</style>
</head>
<body>
<div class="split">
  <div class="brandside">
    <a href="/index.php" class="wordmark"><div class="chip"></div>Vendorly</a>
    <div class="brand-copy">
      <h1>Local shops, real delivery.</h1>
      <p>Chat with a real seller, agree on a price, and track delivery to a location a rider can actually find.</p>
    </div>
    <div class="brand-foot">A GLOVATECH product</div>
  </div>

  <div class="formside">
    <div class="formcard">
      <h2>Welcome back</h2>
      <p class="sub">Log in to your Vendorly account</p>

      <?php foreach ($errors as $error): ?>
        <div class="error"><?= icon('x') ?><?= htmlspecialchars($error) ?></div>
      <?php endforeach; ?>

      <form method="POST" action="/login.php">
        <?= csrf_field() ?>
        <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>"><?php endif; ?>

        <label>Email or phone</label>
        <div class="inputwrap">
          <?= icon('user') ?>
          <input type="text" name="email_or_phone" value="<?= htmlspecialchars($oldEmail) ?>" required>
        </div>

        <label>Password</label>
        <div class="inputwrap">
          <?= icon('lock') ?>
          <input type="password" name="password" required>
        </div>

        <button type="submit">Log in</button>
      </form>

      <div class="link">Don't have an account? <a href="/register.php">Create one</a></div>
    </div>
  </div>
</div>
</body>
</html>
