<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
require_role('rider');
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Dashboard — Vendorly</title>
<style>body{font-family:sans-serif;background:#F5F7F5;padding:40px;color:#132323;}
a{color:#0F3D3E;}</style></head>
<body>
  <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?> 👋</h1>
  <p>This is the rider dashboard stub. Role-based routing is confirmed working —
  you landed here because your account's role is <strong>rider</strong>.</p>
  <p><a href="/logout.php">Log out</a></p>
</body></html>
