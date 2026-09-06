<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

$errors = [];
$oldEmail = '';
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? null;
if ($redirect && !str_starts_with($redirect, '/')) {
    $redirect = null; // never trust this as an open redirect target
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
<style>
  body{font-family:sans-serif;background:#F5F7F5;margin:0;padding:40px 16px;color:#132323;}
  .card{max-width:380px;margin:0 auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 10px 30px rgba(15,61,62,0.08);}
  h1{font-size:20px;margin:0 0 18px;}
  label{display:block;font-size:12.5px;font-weight:600;margin:14px 0 5px;}
  input{width:100%;padding:10px 12px;border:1.5px solid #DAE3E1;border-radius:9px;font-size:14px;box-sizing:border-box;}
  button{width:100%;margin-top:20px;padding:12px;background:#0F3D3E;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:14px;cursor:pointer;}
  .error{background:#FCE7DA;color:#B1471B;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:10px;}
  .link{text-align:center;margin-top:16px;font-size:13px;}
  a{color:#0F3D3E;}
</style>
</head>
<body>
  <div class="card">
    <h1>Log in to Vendorly</h1>
    <?php foreach ($errors as $error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <form method="POST" action="/login.php">
      <?= csrf_field() ?>
      <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>"><?php endif; ?>
      <label>Email or phone</label>
      <input type="text" name="email_or_phone" value="<?= htmlspecialchars($oldEmail) ?>" required>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit">Log in</button>
    </form>
    <div class="link">Don't have an account? <a href="/register.php">Create one</a></div>
  </div>
</body>
</html>
