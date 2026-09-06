<?php
require_once __DIR__ . '/../includes/marketplace.php';
start_secure_session();
require_role('business');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /business/products.php');
    exit;
}
verify_csrf();

$businessId = current_business_id();
$productId  = (int) ($_POST['product_id'] ?? 0);

// Archiving, not deleting: a product may already be referenced by past orders (order_items has
// ON DELETE RESTRICT specifically to prevent losing that history). Archiving hides it from customers
// without breaking anything that already points to it.
$stmt = db()->prepare('UPDATE products SET status = "archived" WHERE id = :id AND business_id = :bid');
$stmt->execute(['id' => $productId, 'bid' => $businessId]);

$_SESSION['flash'] = 'Product archived.';
header('Location: /business/products.php');
exit;
