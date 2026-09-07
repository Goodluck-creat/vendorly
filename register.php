<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/icons.php';
start_secure_session();

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'role' => $_GET['role'] ?? 'customer'];
if (!in_array($old['role'], ['customer', 'business', 'rider'], true)) {
    $old['role'] = 'customer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim(strtolower($_POST['email'] ?? ''));
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['role']  = $_POST['role'] ?? 'customer';
    $password     = $_POST['password'] ?? '';

    if ($old['name'] === '') $errors[] = 'Name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($old['phone'] === '') $errors[] = 'Phone number is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (empty($errors)) {
        try {
            $userId = register_user($old['name'], $old['email'], $old['phone'], $password, $old['role']);
            $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();

            login_session($user);
            header('Location: ' . role_home_path($user['role']));
            exit;
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
<title>Create your account — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1,h2{ font-family:'Sora',sans-serif; }
  .icon{ width:20px; height:20px; vertical-align:middle; }
  :focus-visible{ outline:2.5px solid var(--accent); outline-offset:2px; }

  .wrap{ max-width:440px; margin:0 auto; padding:40px 20px 60px; }
  .wordmark{ display:flex; align-items:center; gap:9px; font-weight:800; font-size:17px; text-decoration:none; color:var(--ink); margin-bottom:28px; justify-content:center; }
  .wordmark .chip{ width:26px; height:26px; border-radius:8px; background:linear-gradient(135deg,var(--brand),var(--brand-deep)); }

  .card{ background:#fff; border:1px solid var(--line); border-radius:16px; padding:28px; animation: rise .5s ease both; box-shadow:0 10px 32px rgba(15,34,34,.06); }
  @keyframes rise{ from{ opacity:0; transform:translateY(14px); } to{ opacity:1; transform:translateY(0); } }
  @media (prefers-reduced-motion: reduce){ .card{ animation:none; } }

  h2{ font-size:21px; margin:0 0 4px; text-align:center; }
  .sub{ font-size:13.5px; color:var(--ink-soft); text-align:center; margin:0 0 24px; }

  .rolegrid{ display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:20px; }
  .rolecard{
    border:1.5px solid var(--line); border-radius:12px; padding:14px 8px; text-align:center; cursor:pointer;
    transition:all .16s ease; display:flex; flex-direction:column; align-items:center; gap:6px;
  }
  .rolecard .icon{ color:var(--ink-soft); transition:color .16s ease; }
  .rolecard span{ font-size:12px; font-weight:700; color:var(--ink-soft); }
  .rolecard input{ display:none; }
  .rolecard:has(input:checked){ border-color:var(--brand); background:var(--brand-tint); }
  .rolecard:has(input:checked) .icon,
  .rolecard:has(input:checked) span{ color:var(--brand); }
  .rolecard:hover{ border-color:var(--brand); }

  label{ display:block; font-size:12.5px; font-weight:600; margin:14px 0 6px; }
  .inputwrap{ position:relative; }
  .inputwrap .icon{ position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--ink-soft); width:17px; height:17px; }
  input[type=text],input[type=email],input[type=tel],input[type=password]{
    width:100%; padding:11px 14px 11px 40px; border:1.5px solid var(--line); border-radius:10px;
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

  .error{ background:#FCE7DA; color:#B1471B; padding:11px 13px; border-radius:9px; font-size:13px; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
  .error .icon{ width:16px; height:16px; flex:none; }

  .link{ text-align:center; margin-top:20px; font-size:13.5px; color:var(--ink-soft); }
  .link a{ color:var(--brand); font-weight:700; text-decoration:none; }

  @media (max-width:400px){ .card{ padding:22px 18px; } .rolegrid{ gap:6px; } }
</style>
</head>
<body>
<div class="wrap">
  <a href="/index.php" class="wordmark"><div class="chip"></div>Vendorly</a>
  <div class="card">
    <h2>Create your account</h2>
    <p class="sub">Join Vendorly as a customer, business, or rider</p>

    <?php foreach ($errors as $error): ?>
      <div class="error"><?= icon('x') ?><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <form method="POST" action="/register.php">
      <?= csrf_field() ?>

      <div class="rolegrid">
        <label class="rolecard">
          <input type="radio" name="role" value="customer" <?= $old['role'] === 'customer' ? 'checked' : '' ?>>
          <?= icon('user') ?>
          <span>Customer</span>
        </label>
        <label class="rolecard">
          <input type="radio" name="role" value="business" <?= $old['role'] === 'business' ? 'checked' : '' ?>>
          <?= icon('store') ?>
          <span>Business</span>
        </label>
        <label class="rolecard">
          <input type="radio" name="role" value="rider" <?= $old['role'] === 'rider' ? 'checked' : '' ?>>
          <?= icon('bike') ?>
          <span>Rider</span>
        </label>
      </div>

      <label>Full name</label>
      <div class="inputwrap"><?= icon('user') ?><input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required></div>

      <label>Email</label>
      <div class="inputwrap"><?= icon('mail') ?><input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required></div>

      <label>Phone</label>
      <div class="inputwrap"><?= icon('phone') ?><input type="tel" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required></div>

      <label>Password</label>
      <div class="inputwrap"><?= icon('lock') ?><input type="password" name="password" minlength="8" required></div>

      <button type="submit">Create account</button>
    </form>
    <div class="link">Already have an account? <a href="/login.php">Log in</a></div>
  </div>
</div>
</body>
</html>
