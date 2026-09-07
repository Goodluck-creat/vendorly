<?php
require_once __DIR__ . '/../includes/marketplace.php';
require_once __DIR__ . '/../includes/icons.php';
start_secure_session();
require_role('business');

$businessId = current_business_id();
$productId  = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['product_id']) ? (int) $_POST['product_id'] : null);
$isEdit     = $productId !== null;

$product = ['name' => '', 'description' => '', 'category' => '', 'price' => '', 'pricing_mode' => 'fixed', 'stock_quantity' => '', 'status' => 'active', 'image_url' => null];
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
    $galleryPhotos = get_product_media($productId);
} else {
    $galleryPhotos = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media_id'])) {
    verify_csrf();
    $mediaProductId = (int) ($_POST['media_product_id'] ?? 0);
    delete_product_media((int) $_POST['delete_media_id'], $businessId);
    header('Location: /business/product_form.php?id=' . $mediaProductId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $product['name']           = trim($_POST['name'] ?? '');
    $product['description']    = trim($_POST['description'] ?? '');
    $product['category']       = trim($_POST['category'] ?? '');
    $product['price']          = $_POST['price'] ?? '';
    $product['pricing_mode']   = $_POST['pricing_mode'] ?? 'fixed';
    $product['stock_quantity'] = $_POST['stock_quantity'] ?? '';
    $product['status']         = $_POST['status'] ?? 'active';

    if ($product['name'] === '') $errors[] = 'Product name is required.';
    if (!is_numeric($product['price']) || (float) $product['price'] <= 0) $errors[] = 'Enter a valid price.';
    if (!in_array($product['pricing_mode'], ['fixed', 'negotiable'], true)) $errors[] = 'Invalid pricing mode.';
    if (!is_numeric($product['stock_quantity']) || (int) $product['stock_quantity'] < 0) $errors[] = 'Enter a valid stock quantity.';
    if (!in_array($product['status'], ['active', 'out_of_stock', 'archived'], true)) $errors[] = 'Invalid status.';

    $makeSignature = isset($_POST['is_signature']);

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
                     price=:price, pricing_mode=:pricing_mode, stock_quantity=:stock, status=:status, image_url=:image
                     WHERE id=:id AND business_id=:bid'
                );
                $stmt->execute([
                    'name' => $product['name'], 'description' => $product['description'],
                    'category' => $product['category'], 'price' => $product['price'], 'pricing_mode' => $product['pricing_mode'],
                    'stock' => $product['stock_quantity'], 'status' => $product['status'],
                    'image' => $imageToStore, 'id' => $productId, 'bid' => $businessId,
                ]);
                // If a new image replaced an old one, clean up the old file
                if ($newImagePath && !empty($existing['image_url'])) {
                    delete_product_image($existing['image_url']);
                }
                $targetProductId = $productId;
                $_SESSION['flash'] = 'Product updated.';
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO products (business_id, name, description, category, price, pricing_mode, stock_quantity, status, image_url)
                     VALUES (:bid, :name, :description, :category, :price, :pricing_mode, :stock, :status, :image)'
                );
                $stmt->execute([
                    'bid' => $businessId, 'name' => $product['name'], 'description' => $product['description'],
                    'category' => $product['category'], 'price' => $product['price'], 'pricing_mode' => $product['pricing_mode'],
                    'stock' => $product['stock_quantity'], 'status' => $product['status'], 'image' => $newImagePath,
                ]);
                $targetProductId = (int) db()->lastInsertId();
                $_SESSION['flash'] = 'Product added.';
            }

            // Gallery photos — appended to whatever's already there, doesn't touch the primary photo above.
            save_product_media($targetProductId, $_FILES['gallery'] ?? []);

            // Signature dish — only one per business, ever.
            if ($makeSignature) {
                set_signature_product($businessId, $targetProductId);
            } elseif ($isEdit && !empty($existing['is_signature'])) {
                // They unchecked it on the item that WAS the signature dish — clear it, don't leave it dangling.
                clear_signature_product($businessId);
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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --brand:#0F3D3E; --brand-deep:#082627; --brand-tint:#E4EEED; --accent:#FF7A45; --ink:#132323; --ink-soft:#5C6E6C; --line:#DAE3E1; --paper:#F5F7F5; }
  *{box-sizing:border-box;}
  body{ margin:0; font-family:'Inter',sans-serif; color:var(--ink); background:var(--paper); }
  h1{ font-family:'Sora',sans-serif; }
  a{ text-decoration:none; }
  .icon{ width:20px; height:20px; vertical-align:middle; }
  :focus-visible{ outline:2.5px solid var(--accent); outline-offset:2px; }

  .wrap{ max-width:520px; margin:0 auto; padding:28px 20px 60px; }
  .top-link{ display:inline-flex; align-items:center; gap:6px; color:var(--brand); font-size:13.5px; font-weight:600; }
  .top-link .icon{ width:16px; height:16px; }
  h1{ font-size:22px; margin:16px 0 20px; }

  .card{ background:#fff; border:1px solid var(--line); border-radius:16px; padding:24px; animation: rise .45s ease both; box-shadow:0 8px 26px rgba(15,34,34,.05); }
  @keyframes rise{ from{ opacity:0; transform:translateY(12px); } to{ opacity:1; transform:translateY(0); } }
  @media (prefers-reduced-motion: reduce){ .card{ animation:none; } }

  label{ display:block; font-size:12.5px; font-weight:600; margin:16px 0 6px; }
  input,textarea,select{
    width:100%; padding:11px 13px; border:1.5px solid var(--line); border-radius:10px; font-size:14px;
    font-family:inherit; box-sizing:border-box; transition:border-color .18s ease, box-shadow .18s ease;
  }
  input:focus,textarea:focus,select:focus{ border-color:var(--brand); box-shadow:0 0 0 3px rgba(15,61,62,0.1); outline:none; }
  textarea{ min-height:80px; resize:vertical; }
  .row2{ display:flex; gap:12px; }
  .row2 > *{ flex:1; }

  .radio-card{ display:flex; gap:10px; align-items:flex-start; border:1.5px solid var(--line); border-radius:11px; padding:12px; cursor:pointer; font-weight:400; transition:all .16s ease; }
  .radio-card input{ width:auto; margin-top:3px; }
  .radio-card strong{ display:block; font-size:12.5px; font-weight:700; }
  .radio-card span{ display:block; font-size:11px; color:var(--ink-soft); margin-top:2px; line-height:1.4; }
  .radio-card:has(input:checked){ border-color:var(--brand); background:var(--brand-tint); }
  .radio-card:hover{ border-color:var(--brand); }

  button[type=submit]{
    display:flex; align-items:center; justify-content:center; gap:8px;
    width:100%; margin-top:22px; padding:13px; background:var(--brand); color:#fff; border:none;
    border-radius:11px; font-weight:700; font-size:14.5px; cursor:pointer; transition:background .18s ease, transform .12s ease;
  }
  button[type=submit]:hover{ background:var(--brand-deep); }
  button[type=submit]:active{ transform:scale(0.98); }
  button[type=submit] .icon{ width:18px; height:18px; }

  .error{ display:flex; align-items:center; gap:8px; background:#FCE7DA; color:#B1471B; padding:11px 13px; border-radius:9px; font-size:13px; margin-bottom:10px; }
  .error .icon{ width:16px; height:16px; flex:none; }

  .current-image{ width:80px; height:80px; border-radius:11px; object-fit:cover; margin:8px 0; display:block; }
  .gallery-grid{ display:flex; flex-wrap:wrap; gap:8px; margin:8px 0; }
  .gallery-thumb-wrap{ position:relative; width:64px; height:64px; }
  .gallery-thumb-wrap img{ width:100%; height:100%; object-fit:cover; border-radius:9px; }
  .remove-thumb{
    position:absolute; top:-6px; right:-6px; width:22px; height:22px; border-radius:50%; background:#B1471B;
    color:#fff; border:2px solid #fff; cursor:pointer; padding:0; display:flex; align-items:center; justify-content:center;
    transition:transform .14s ease;
  }
  .remove-thumb:hover{ transform:scale(1.1); }
  .remove-thumb .icon{ width:12px; height:12px; }

  .field-hint{ font-weight:400; color:var(--ink-soft); }

  @media (max-width:420px){ .wrap{ padding:22px 16px 50px; } .card{ padding:18px; } .row2{ gap:8px; } }
</style>
</head>
<body>
<div class="wrap">
  <a href="/business/products.php" class="top-link"><?= icon('arrow-left', 'icon') ?>Products</a>
  <h1><?= $isEdit ? 'Edit product' : 'Add a product' ?></h1>
  <div class="card">
    <?php foreach ($errors as $error): ?>
      <div class="error"><?= icon('x') ?><?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($isEdit): ?><input type="hidden" name="product_id" value="<?= (int) $productId ?>"><?php endif; ?>

      <label>Product name</label>
      <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" placeholder="e.g. Jollof Rice & Chicken" required>

      <label>Category</label>
      <input type="text" name="category" list="category-suggestions" value="<?= htmlspecialchars($product['category'] ?? '') ?>" placeholder="e.g. Meals">
      <datalist id="category-suggestions">
        <option value="Meals">
        <option value="Snacks & Small Chops">
        <option value="Drinks">
        <option value="Desserts & Baked Goods">
        <option value="Packaged Foods">
      </datalist>

      <label>Pricing</label>
      <div class="row2">
        <label class="radio-card">
          <input type="radio" name="pricing_mode" value="fixed" <?= $product['pricing_mode'] === 'fixed' ? 'checked' : '' ?>>
          <div><strong>Fixed price</strong><span>Customer orders directly at this price — best for food & everyday items</span></div>
        </label>
        <label class="radio-card">
          <input type="radio" name="pricing_mode" value="negotiable" <?= $product['pricing_mode'] === 'negotiable' ? 'checked' : '' ?>>
          <div><strong>Negotiable</strong><span>Customer chats to agree a price first — best for higher-value items</span></div>
        </label>
      </div>

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

      <label>Extra gallery photos <span class="field-hint">(different angles — customers can click through them)</span></label>
      <?php if (!empty($galleryPhotos)): ?>
        <div class="gallery-grid">
          <?php foreach ($galleryPhotos as $g): ?>
            <div class="gallery-thumb-wrap">
              <img src="/<?= htmlspecialchars($g['image_url']) ?>" alt="">
              <button type="submit" form="delete-media-<?= (int) $g['id'] ?>" class="remove-thumb" title="Remove photo"><?= icon('x', 'icon') ?></button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <input type="file" name="gallery[]" accept="image/jpeg,image/png,image/webp" multiple>

      <?php if ($isEdit): ?>
        <label class="radio-card" style="margin-top:16px;">
          <input type="checkbox" name="is_signature" <?= !empty($product['is_signature']) ? 'checked' : '' ?>>
          <div><strong><?= icon('star', 'icon') ?> Feature as this shop's signature dish</strong><span>Shown animated at the top of your shopfront — only one item can be featured at a time</span></div>
        </label>
      <?php endif; ?>

      <button type="submit"><?= icon($isEdit ? 'check' : 'plus', 'icon') ?><?= $isEdit ? 'Save changes' : 'Add product' ?></button>
    </form>

    <?php foreach ($galleryPhotos as $g): ?>
      <form id="delete-media-<?= (int) $g['id'] ?>" method="POST" style="display:none;">
        <?= csrf_field() ?>
        <input type="hidden" name="delete_media_id" value="<?= (int) $g['id'] ?>">
        <input type="hidden" name="media_product_id" value="<?= (int) $productId ?>">
      </form>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
