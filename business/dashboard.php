<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
require_role('business');

$stmt = db()->prepare('SELECT status FROM users WHERE id = :id');
$stmt->execute(['id' => current_user_id()]);
$status = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Dashboard — Vendorly</title>
<style>body{font-family:sans-serif;background:#F5F7F5;padding:40px;color:#132323;}
a{color:#0F3D3E;} .badge{background:#FCE7DA;color:#B1471B;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:700;}</style></head>
<body>
  <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h1>
  <p>This is the business dashboard stub. Role-based routing is confirmed working —
  you landed here because your account's role is <strong>business</strong>.</p>
  <?php if ($status === 'pending'): ?>
    <p><span class="badge">Pending verification</span> — your account is awaiting admin approval (Stage 6).</p>
  <?php endif; ?>
  <p><a href="/logout.php">Log out</a></p>
</body></html>
