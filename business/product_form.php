<?php
require_once __DIR__ . '/../includes/marketplace.php';
start_secure_session();
require_role('business');

$businessId = current_business_id();
$productId  = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['product_id']) ? (int) $_POST['product_id'] : null);
$isEdit     = $productId !== null;

$product = ['name' => '', 'description' => '', 'category' => '', 'price' => '', 'stock_quantity' => '', 'status' => 'active', 'image_url' => null];
$errors = [];

// If editing, load the existing product — and confirm it actually belongs to THIS business.
// Never trust a product_id from the request alone; a business must never be able to edit another's listing.
if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id AND business_id = :bid LIMIT 1');
    $stmt->execute(['id' => $productId, 'bid' => $businessId]);
    $existing = $stmt->fetch();
    if (!$existing) {
        header('Location: /business/products.php');
        exit;
    }
    $product = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $product['name']           = trim($_POST['name'] ?? '');
    $product['description']    = trim($_POST['description'] ?? '');
    $product['category']       = trim($_POST['category'] ?? '');
    $product['price']          = $_POST['price'] ?? '';
    $product['stock_quantity'] = $_POST['stock_quantity'] ?? '';
    $product['status']         = $_POST['status'] ?? 'active';

    if ($product['name'] === '') $errors[] = 'Product name is required.';
    if (!is_numeric($product['price']) || (float) $product['price'] <= 0) $errors[] = 'Enter a valid price.';
    if (!is_numeric($product['stock_quantity']) || (int) $product['stock_quantity'] < 0) $errors[] = 'Enter a valid stock quantity.';
    if (!in_array($product['status'], ['active', 'out_of_stock', 'archived'], true)) $errors[] = 'Invalid status.';

    $newImagePath = null;
    if (empty($errors)) {
        try {
            $newImagePath = handle_product_image_upload($_FILES['image'] ?? []);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        try {
            if ($isEdit) {
                $imageToStore = $newImagePath ?? $product['image_url'];
                $stmt = db()->prepare(
                    'UPDATE products SET name=:name, description=:description, category=:category,
                     price=:price, stock_quantity=:stock, status=:status, image_url=:image
                     WHERE id=:id AND business_id=:bid'
                );
                $stmt->execute([
                    'name' => $product['name'], 'description' => $product['description'],
                    'category' => $product['category'], 'price' => $product['price'],
                    'stock' => $product['stock_quantity'], 'status' => $product['status'],
                    'image' => $imageToStore, 'id' => $productId, 'bid' => $businessId,
                ]);
                // If a new image replaced an old one, clean up the old file
                if ($newImagePath && !empty($existing['image_url'])) {
                    delete_product_image($existing['image_url']);
                }
                $_SESSION['flash'] = 'Product updated.';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO products (business_id, name, description, category, price, stock_quantity, status, image_url)
                     VALUES (:bid, :name, :description, :category, :price, :stock, :status, :image)'
                );
                $stmt->execute([
                    'bid' => $businessId, 'name' => $product['name'], 'description' => $product['description'],
                    'category' => $product['category'], 'price' => $product['price'],
                    'stock' => $product['stock_quantity'], 'status' => $product['status'], 'image' => $newImagePath,
                ]);
                $_SESSION['flash'] = 'Product added.';
            }
            header('Location: /business/products.php');
            exit;
        } catch (Exception $e) {
            error_log('Product save failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong saving this product. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $isEdit ? 'Edit product' : 'Add product' ?> — Vendorly</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ font-family:'Inter',sans-serif; background:var(--paper); color:var(--ink); margin:0; }
  .wrap{ max-width:520px; margin:0 auto; padding:32px 20px; }
  a.top-link{ color:var(--brand); font-size:13.5px; text-decoration:none; font-weight:600; }
  h1{ font-size:22px; margin:16px 0 20px; }
  .card{ background:#fff; border:1px solid var(--line); border-radius:14px; padding:24px; }
  label{ display:block; font-size:12.5px; font-weight:600; margin:14px 0 5px; }
  input,textarea,select{ width:100%; padding:10px 12px; border:1.5px solid var(--line); border-radius:9px; font-size:14px; font-family:inherit; box-sizing:border-box; }
  textarea{ min-height:80px; resize:vertical; }
  .row2{ display:flex; gap:12px; }
  .row2 > *{ flex:1; }
  button{ width:100%; margin-top:20px; padding:12px; background:var(--brand); color:#fff; border:none; border-radius:9px; font-weight:700; font-size:14px; cursor:pointer; }
  .error{ background:#FCE7DA; color:#B1471B; padding:10px 12px; border-radius:8px; font-size:13px; margin-bottom:10px; }
  .current-image{ width:80px; height:80px; border-radius:9px; object-fit:cover; margin:8px 0; display:block; }
</style>
</head>
<body>
<div class="wrap">
  <a href="/business/products.php" class="top-link">&larr; Products</a>
  <h1><?= $isEdit ? 'Edit product' : 'Add a product' ?></h1>
  <div class="card">
    <?php foreach ($errors as $error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($isEdit): ?><input type="hidden" name="product_id" value="<?= (int) $productId ?>"><?php endif; ?>

      <label>Product name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>

      <label>Category</label>
      <input type="text" name="category" value="<?= htmlspecialchars($product['category'] ?? '') ?>" placeholder="e.g. Electronics">

      <div class="row2">
        <div>
          <label>Price (₦)</label>
          <input type="number" step="0.01" min="0" name="price" value="<?= htmlspecialchars((string) $product['price']) ?>" required>
        </div>
        <div>
          <label>Stock quantity</label>
          <input type="number" min="0" name="stock_quantity" value="<?= htmlspecialchars((string) $product['stock_quantity']) ?>" required>
        </div>
      </div>

      <label>Description</label>
      <textarea name="description"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>

      <label>Status</label>
      <select name="status">
        <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="out_of_stock" <?= $product['status'] === 'out_of_stock' ? 'selected' : '' ?>>Out of stock</option>
        <option value="archived" <?= $product['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
      </select>

      <label>Photo</label>
      <?php if (!empty($product['image_url'])): ?>
        <img class="current-image" src="/<?= htmlspecialchars($product['image_url']) ?>" alt="Current product photo">
      <?php endif; ?>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp">

      <button type="submit"><?= $isEdit ? 'Save changes' : 'Add product' ?></button>
    </form>
  </div>
</div>
</body>
</html>
