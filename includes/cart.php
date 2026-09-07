<?php
// includes/cart.php — Stage 3 cart logic.
//
// A cart IS an order row (status='draft'). A customer/guest can only have ONE
// active cart at a time — tracked via $_SESSION['active_cart_business_id'].
// Switching to a different business doesn't merge or delete anything: the old
// draft simply stops being "active" and sits untouched, visible to that
// business later as an abandoned cart with the customer's contact info.

require_once __DIR__ . '/auth.php';

function get_active_cart_business_id(): ?int {
    return $_SESSION['active_cart_business_id'] ?? null;
}

function set_active_cart_business_id(int $businessId): void {
    $_SESSION['active_cart_business_id'] = $businessId;
}

function clear_active_cart_business_id(): void {
    unset($_SESSION['active_cart_business_id']);
}

/**
 * Find this customer's existing draft order for a business, or create one.
 * One draft per (customer, business) at a time — reused across visits until checkout.
 */
function get_or_create_draft_order(int $customerId, int $businessId): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = :cid AND business_id = :bid AND status = 'draft' LIMIT 1");
    $stmt->execute(['cid' => $customerId, 'bid' => $businessId]);
    $order = $stmt->fetch();
    if ($order) return $order;

    $orderCode = generate_order_code();
    $stmt = $pdo->prepare("INSERT INTO orders (order_code, customer_id, business_id, status) VALUES (:code, :cid, :bid, 'draft')");
    $stmt->execute(['code' => $orderCode, 'cid' => $customerId, 'bid' => $businessId]);
    $orderId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
    $stmt->execute(['id' => $orderId]);
    return $stmt->fetch();
}

/**
 * Does this customer already have a NON-EMPTY draft cart for a DIFFERENT business
 * than the one they're about to add to? If so, the caller should show the
 * switch-vendor warning instead of silently adding.
 */
function get_conflicting_cart(int $customerId, int $newBusinessId): ?array {
    $activeBusinessId = get_active_cart_business_id();
    if (!$activeBusinessId || $activeBusinessId === $newBusinessId) {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT o.*, b.business_name, b.slug, COUNT(oi.id) AS item_count
         FROM orders o
         JOIN businesses b ON b.id = o.business_id
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.customer_id = :cid AND o.business_id = :bid AND o.status = 'draft'
         GROUP BY o.id"
    );
    $stmt->execute(['cid' => $customerId, 'bid' => $activeBusinessId]);
    $cart = $stmt->fetch();

    if ($cart && (int) $cart['item_count'] > 0) {
        return $cart;
    }
    return null; // the "active" business's cart is actually empty — nothing to warn about
}

/**
 * Add a product to the customer's cart for its business. Returns the order,
 * or throws if there's an unresolved vendor conflict (caller should have
 * checked get_conflicting_cart() first and gotten user confirmation).
 */
function add_to_cart(int $customerId, int $productId, int $quantity = 1): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id AND status = 'active' LIMIT 1");
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch();
    if (!$product) {
        throw new Exception('This item is no longer available.');
    }

    $order = get_or_create_draft_order($customerId, (int) $product['business_id']);
    set_active_cart_business_id((int) $product['business_id']);

    // Already in the cart? Increment quantity instead of adding a duplicate line.
    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid AND product_id = :pid LIMIT 1');
    $stmt->execute(['oid' => $order['id'], 'pid' => $productId]);
    $existingItem = $stmt->fetch();

    if ($existingItem) {
        $pdo->prepare('UPDATE order_items SET quantity = quantity + :qty WHERE id = :id')
            ->execute(['qty' => $quantity, 'id' => $existingItem['id']]);
    } else {
        $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price_at_order) VALUES (:oid, :pid, :qty, :price)')
            ->execute(['oid' => $order['id'], 'pid' => $productId, 'qty' => $quantity, 'price' => $product['price']]);
    }

    return $order;
}

function update_cart_item_quantity(int $orderId, int $itemId, int $quantity): void {
    if ($quantity <= 0) {
        db()->prepare('DELETE FROM order_items WHERE id = :id AND order_id = :oid')->execute(['id' => $itemId, 'oid' => $orderId]);
    } else {
        db()->prepare('UPDATE order_items SET quantity = :qty WHERE id = :id AND order_id = :oid')
            ->execute(['qty' => $quantity, 'id' => $itemId, 'oid' => $orderId]);
    }
}

/**
 * Get the customer's current cart for a business, with line items and a total.
 * Returns null if there's no draft order (empty cart / never started).
 */
function get_cart(int $customerId, int $businessId): ?array {
    $stmt = db()->prepare("SELECT * FROM orders WHERE customer_id = :cid AND business_id = :bid AND status = 'draft' LIMIT 1");
    $stmt->execute(['cid' => $customerId, 'bid' => $businessId]);
    $order = $stmt->fetch();
    if (!$order) return null;

    $itemsStmt = db()->prepare(
        "SELECT oi.*, p.name, p.image_url, p.pricing_mode
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = :oid
         ORDER BY oi.id"
    );
    $itemsStmt->execute(['oid' => $order['id']]);
    $items = $itemsStmt->fetchAll();

    $total = 0;
    foreach ($items as $item) {
        $total += $item['unit_price_at_order'] * $item['quantity'];
    }

    $order['items'] = $items;
    $order['total'] = $total;
    return $order;
}

/**
 * Turn a draft cart into a real submitted order: stamps the agreed price,
 * flips status to pending_acceptance. The order_code was already assigned
 * when the draft was created, so nothing changes there.
 */
function checkout_cart(int $orderId, int $customerId): void {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT o.*, COUNT(oi.id) AS item_count, SUM(oi.quantity * oi.unit_price_at_order) AS total
        FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.id = :id AND o.customer_id = :cid AND o.status = 'draft' GROUP BY o.id");
    $stmt->execute(['id' => $orderId, 'cid' => $customerId]);
    $order = $stmt->fetch();

    if (!$order || (int) $order['item_count'] === 0) {
        throw new Exception('Your cart is empty.');
    }

    $pdo->prepare("UPDATE orders SET status = 'pending_acceptance', agreed_price = :total WHERE id = :id")
        ->execute(['total' => $order['total'], 'id' => $orderId]);

    clear_active_cart_business_id();
}
