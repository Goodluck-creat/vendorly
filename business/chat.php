<?php
require_once __DIR__ . '/../includes/chat.php';
require_once __DIR__ . '/../includes/icons.php';
require_once __DIR__ . '/../includes/business_layout.php';
require_once __DIR__ . '/../includes/marketplace.php';
start_secure_session();
require_role('business');

$bizStmt = db()->prepare('SELECT * FROM businesses WHERE id = :id');
$bizStmt->execute(['id' => current_business_id()]);
$businessRow = $bizStmt->fetch();

$conversations = get_business_conversations(current_user_id());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Messages — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1{ font-family:'Sora',sans-serif; }
  a{ text-decoration:none; color:inherit; }
  .icon{ width:20px; height:20px; vertical-align:middle; }

  .wrap{ max-width:700px; margin:0 auto; padding:32px 32px 60px; }
  .top-link{ display:inline-flex; align-items:center; gap:6px; color:var(--brand); font-size:13.5px; font-weight:600; }
  .top-link .icon{ width:16px; height:16px; }
  <?= business_layout_styles() ?>
  @media (max-width:700px){ .wrap{ padding:24px 18px 50px; } }
  h1{ font-size:22px; margin:16px 0 20px; }

  .convo{ display:flex; align-items:center; gap:12px; background:#fff; border:1px solid var(--line); border-radius:13px; padding:14px; margin-bottom:10px; }
  .avatar{ width:44px; height:44px; border-radius:50%; background:var(--brand-tint); color:var(--brand); display:flex; align-items:center; justify-content:center; flex:none; }
  .cinfo{ flex:1; min-width:0; }
  .cname{ font-size:14px; font-weight:700; margin-bottom:2px; display:flex; align-items:center; gap:6px; }
  .cpreview{ font-size:12.5px; color:var(--ink-soft); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .unread-dot{ width:8px; height:8px; border-radius:50%; background:var(--accent); flex:none; }
  .empty{ text-align:center; padding:60px 20px; color:var(--ink-soft); }
</style>
</head>
<body>
<?= business_layout_head('chat', $businessRow['business_name']) ?>
<div class="wrap">
  <h1>Messages</h1>

  <?php if (empty($conversations)): ?>
    <div class="empty"><?= icon('chat') ?><div>No conversations yet.</div></div>
  <?php else: ?>
    <?php foreach ($conversations as $c): ?>
      <a class="convo" href="/business/chat_thread.php?customer_id=<?= (int) $c['customer_id'] ?>">
        <div class="avatar"><?= icon('user') ?></div>
        <div class="cinfo">
          <div class="cname">
            <?= htmlspecialchars($c['customer_name']) ?>
            <?php if ((int) $c['unread_count'] > 0): ?><span class="unread-dot"></span><?php endif; ?>
          </div>
          <div class="cpreview"><?= htmlspecialchars($c['last_message'] ?? '') ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?= business_layout_foot() ?>
</body>
</html>
