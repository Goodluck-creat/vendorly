<?php
require_once __DIR__ . '/../includes/chat.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_role('business');

$customerId = (int) ($_GET['customer_id'] ?? 0);

$custStmt = db()->prepare('SELECT * FROM users WHERE id = :id AND role = "customer" LIMIT 1');
$custStmt->execute(['id' => $customerId]);
$customer = $custStmt->fetch();
if (!$customer) {
    header('Location: /business/chat.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $text = trim($_POST['message'] ?? '');
    if ($text !== '') {
        send_message(current_user_id(), $customerId, $text);
    }
    header('Location: /business/chat_thread.php?customer_id=' . $customerId);
    exit;
}

mark_thread_read(current_user_id(), $customerId);
$messages = get_thread($customerId, current_user_id());
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Chat with <?= htmlspecialchars($customer['name']) ?> — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  html,body{ height:100%; }
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); display:flex; flex-direction:column; }
  a{ text-decoration:none; }
  .icon{ width:20px; height:20px; vertical-align:middle; }

  .topbar{ display:flex; align-items:center; gap:10px; padding:14px 18px; background:#fff; border-bottom:1px solid var(--line); }
  .topbar a{ color:var(--brand); display:flex; align-items:center; }
  .topbar .icon{ width:18px; height:18px; }
  .topbar strong{ font-size:14.5px; }
  .topbar span{ display:block; font-size:11.5px; color:var(--ink-soft); }
  .topbar .call{ margin-left:auto; color:var(--brand); }

  .thread{ flex:1; overflow-y:auto; padding:16px 18px; max-width:600px; width:100%; margin:0 auto; box-sizing:border-box; }
  .bubble{ max-width:78%; padding:10px 14px; border-radius:14px; font-size:13.5px; margin:6px 0; line-height:1.5; background:#fff; border:1px solid var(--line); }
  .bubble.mine{ margin-left:auto; background:var(--brand); color:#fff; border-color:var(--brand); }
  .bubble-time{ font-size:10px; color:var(--ink-soft); margin-top:3px; }
  .bubble.mine + .bubble-time{ text-align:right; }
  .empty-thread{ text-align:center; color:var(--ink-soft); font-size:13px; padding:40px 20px; }

  .composer{ background:#fff; border-top:1px solid var(--line); padding:12px 18px; }
  .composer-inner{ max-width:600px; margin:0 auto; display:flex; gap:8px; }
  .composer input{ flex:1; padding:11px 14px; border:1.5px solid var(--line); border-radius:22px; font-size:14px; font-family:inherit; }
  .composer input:focus{ border-color:var(--brand); outline:none; }
  .composer button{ width:42px; height:42px; border-radius:50%; background:var(--brand); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; flex:none; transform:rotate(180deg); }
  .composer button:hover{ background:var(--brand-deep); }
</style>
</head>
<body>
<div class="topbar">
  <a href="/business/chat.php"><?= icon('arrow-left') ?></a>
  <div><strong><?= htmlspecialchars($customer['name']) ?></strong><span><?= htmlspecialchars($customer['phone']) ?></span></div>
  <a href="tel:<?= htmlspecialchars($customer['phone']) ?>" class="call"><?= icon('phone') ?></a>
</div>

<div class="thread" id="thread">
  <?php if (empty($messages)): ?>
    <div class="empty-thread"><?= icon('chat') ?><br>No messages yet.</div>
  <?php else: ?>
    <?php foreach ($messages as $m): $mine = (int) $m['sender_id'] === current_user_id(); ?>
      <div class="bubble <?= $mine ? 'mine' : '' ?>"><?= nl2br(htmlspecialchars($m['message_text'])) ?></div>
      <div class="bubble-time"><?= date('g:i A, M j', strtotime($m['created_at'])) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<form class="composer" method="POST">
  <div class="composer-inner">
    <?= csrf_field() ?>
    <input type="text" name="message" placeholder="Type a reply..." autocomplete="off" required autofocus>
    <button type="submit"><?= icon('arrow-left', 'icon') ?></button>
  </div>
</form>

<script>
  const thread = document.getElementById('thread');
  thread.scrollTop = thread.scrollHeight;
</script>
</body>
</html>
