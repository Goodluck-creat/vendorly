<?php
require_once __DIR__ . '/../includes/marketplace.php';
start_secure_session();
require_role('business');

$businessId = current_business_id();
$stmt = db()->prepare('SELECT status FROM users WHERE id = :id');
$stmt->execute(['id' => current_user_id()]);
$accountStatus = $stmt->fetchColumn();

$countStmt = db()->prepare("SELECT
    SUM(status = 'active') AS active_count,
    COUNT(*) AS total_count
    FROM products WHERE business_id = :bid");
$countStmt->execute(['bid' => $businessId]);
$counts = $countStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-tint:#E4EEED; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; --accent:#FF7A45; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink); margin:0; }
  .wrap{ max-width:700px; margin:0 auto; padding:32px 20px; }
  h1{ font-size:22px; margin:0 0 6px; }
  .badge{ display:inline-block; background:#FCE7DA; color:#B1471B; padding:4px 10px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:20px; }
  .statgrid{ display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:24px; }
  .statcard{ background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px; }
  .statcard .val{ font-size:24px; font-weight:800; color:var(--brand); }
  .statcard .lbl{ font-size:12.5px; color:var(--ink-soft); }
  .btn{ display:inline-flex; padding:12px 20px; background:var(--brand); color:#fff; border-radius:9px; font-weight:700; font-size:14px; text-decoration:none; }
  a.logout{ display:block; margin-top:24px; font-size:13px; color:var(--ink-soft); }
</style>
</head>
<body>
<div class="wrap">
  <h1>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></h1>
  <?php if ($accountStatus === 'pending'): ?>
    <div class="badge">Pending verification</div>
  <?php endif; ?>

  <div class="statgrid">
    <div class="statcard">
      <div class="val"><?= (int) ($counts['active_count'] ?? 0) ?></div>
      <div class="lbl">Active products</div>
    </div>
    <div class="statcard">
      <div class="val"><?= (int) ($counts['total_count'] ?? 0) ?></div>
      <div class="lbl">Total products</div>
    </div>
  </div>

  <a href="/business/products.php" class="btn">Manage products</a>
  <a href="/logout.php" class="logout">Log out</a>
</div>
</body>
</html>
