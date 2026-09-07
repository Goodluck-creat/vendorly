<?php
// includes/chat.php — Stage 3 chat. A conversation is identified by (customer_id, business_id) —
// scoped to the shopfront, exactly as agreed: chatting with Ifeoma's Kitchen is a separate
// thread from chatting with Mama Nkechi's Kitchen, never mixed.

require_once __DIR__ . '/auth.php';

function send_message(int $senderId, int $receiverId, string $text, ?int $orderId = null): void {
    $text = trim($text);
    if ($text === '') return;
    db()->prepare('INSERT INTO messages (order_id, sender_id, receiver_id, message_text) VALUES (:oid, :sid, :rid, :text)')
        ->execute(['oid' => $orderId, 'sid' => $senderId, 'rid' => $receiverId, 'text' => $text]);
}

/**
 * Full message thread between a customer and a business's owner user, oldest first.
 */
function get_thread(int $customerId, int $businessUserId): array {
    $stmt = db()->prepare(
        "SELECT * FROM messages
         WHERE (sender_id = :a1 AND receiver_id = :b1) OR (sender_id = :b2 AND receiver_id = :a2)
         ORDER BY created_at ASC"
    );
    $stmt->execute(['a1' => $customerId, 'b1' => $businessUserId, 'b2' => $businessUserId, 'a2' => $customerId]);
    return $stmt->fetchAll();
}

function mark_thread_read(int $readerId, int $otherUserId): void {
    db()->prepare('UPDATE messages SET is_read = TRUE WHERE receiver_id = :me AND sender_id = :other')
        ->execute(['me' => $readerId, 'other' => $otherUserId]);
}

/**
 * List every conversation a business has, most recently active first, with the
 * last message preview and unread count — the business's chat inbox.
 */
function get_business_conversations(int $businessUserId): array {
    $stmt = db()->prepare(
        "SELECT
            u.id AS customer_id, u.name AS customer_name, u.phone AS customer_phone, u.status AS customer_status,
            (SELECT message_text FROM messages m2
             WHERE (m2.sender_id = u.id AND m2.receiver_id = :buid1) OR (m2.sender_id = :buid2 AND m2.receiver_id = u.id)
             ORDER BY m2.created_at DESC LIMIT 1) AS last_message,
            (SELECT created_at FROM messages m3
             WHERE (m3.sender_id = u.id AND m3.receiver_id = :buid3) OR (m3.sender_id = :buid4 AND m3.receiver_id = u.id)
             ORDER BY m3.created_at DESC LIMIT 1) AS last_message_at,
            (SELECT COUNT(*) FROM messages m4 WHERE m4.sender_id = u.id AND m4.receiver_id = :buid5 AND m4.is_read = FALSE) AS unread_count
         FROM users u
         WHERE u.id IN (
            SELECT sender_id FROM messages WHERE receiver_id = :buid6
            UNION
            SELECT receiver_id FROM messages WHERE sender_id = :buid7
         )
         ORDER BY last_message_at DESC"
    );
    $stmt->execute([
        'buid1' => $businessUserId, 'buid2' => $businessUserId, 'buid3' => $businessUserId,
        'buid4' => $businessUserId, 'buid5' => $businessUserId, 'buid6' => $businessUserId, 'buid7' => $businessUserId,
    ]);
    return $stmt->fetchAll();
}
