<?php
require_once __DIR__ . '/includes/auth.php';
start_secure_session();

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'customer'];

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
<style>
  body{font-family:sans-serif;background:#F5F7F5;margin:0;padding:40px 16px;color:#132323;}
  .card{max-width:380px;margin:0 auto;background:#fff;border-radius:14px;padding:28px;box-shadow:0 10px 30px rgba(15,61,62,0.08);}
  h1{font-size:20px;margin:0 0 18px;}
  label{display:block;font-size:12.5px;font-weight:600;margin:14px 0 5px;}
  input,select{width:100%;padding:10px 12px;border:1.5px solid #DAE3E1;border-radius:9px;font-size:14px;box-sizing:border-box;}
  button{width:100%;margin-top:20px;padding:12px;background:#0F3D3E;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:14px;cursor:pointer;}
  .error{background:#FCE7DA;color:#B1471B;padding:10px 12px;border-radius:8px;font-size:13px;margin-bottom:10px;}
  .link{text-align:center;margin-top:16px;font-size:13px;}
  a{color:#0F3D3E;}
</style>
</head>
<body>
  <div class="card">
    <h1>Create your Vendorly account</h1>
    <?php foreach ($errors as $error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <form method="POST" action="/register.php">
      <?= csrf_field() ?>
      <label>I am a...</label>
      <select name="role">
        <option value="customer" <?= $old['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
        <option value="business" <?= $old['role'] === 'business' ? 'selected' : '' ?>>Business</option>
        <option value="rider" <?= $old['role'] === 'rider' ? 'selected' : '' ?>>Rider</option>
      </select>
      <label>Full name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($old['name']) ?>" required>
      <label>Email</label>
      <input type="email" name="email" value="<?= htmlspecialchars($old['email']) ?>" required>
      <label>Phone</label>
      <input type="tel" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required>
      <label>Password</label>
      <input type="password" name="password" minlength="8" required>
      <button type="submit">Create account</button>
    </form>
    <div class="link">Already have an account? <a href="/login.php">Log in</a></div>
  </div>
</body>
</html>
