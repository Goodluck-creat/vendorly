<?php
// includes/marketplace.php — helpers for Stage 2: products, businesses, image uploads.

require_once __DIR__ . '/auth.php';

/**
 * Get the businesses.id row for the currently logged-in business user.
 * Returns null if somehow there isn't one (shouldn't happen post-registration, but never trust that blindly).
 */
function current_business_id(): ?int {
    $userId = current_user_id();
    if (!$userId) return null;

    $stmt = db()->prepare('SELECT id FROM businesses WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

/**
 * Handle a product image upload safely.
 * - Validates it's actually an image (not just trusting the file extension or client-sent MIME type)
 * - Re-encodes it via GD (strips any embedded scripts/metadata, normalizes format)
 * - Resizes down if oversized
 * Returns the relative path to store in the DB (e.g. "uploads/products/xxxx.jpg"), or null if no file was uploaded.
 * Throws an Exception with a user-facing message if a file was uploaded but is invalid.
 */
function handle_product_image_upload(array $file): ?string {
    if (empty($file['name']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // no image provided — perfectly valid, not every product needs one immediately
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed. Please try again.');
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        throw new Exception('Image is too large. Please use a file under 4MB.');
    }

    // Never trust the client-supplied MIME type or file extension — inspect the actual file content.
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        throw new Exception('That file doesn\'t look like a valid image.');
    }

    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (!in_array($imageInfo[2], $allowedTypes, true)) {
        throw new Exception('Please upload a JPG, PNG, or WEBP image.');
    }

    // Load via GD based on real detected type, then re-save as a fresh JPEG —
    // this strips anything malicious embedded in the original file and normalizes the format.
    switch ($imageInfo[2]) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($file['tmp_name']);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($file['tmp_name']);
            break;
        case IMAGETYPE_WEBP:
            $src = imagecreatefromwebp($file['tmp_name']);
            break;
        default:
            throw new Exception('Unsupported image type.');
    }
    if (!$src) {
        throw new Exception('Could not process that image. Please try a different file.');
    }

    // Resize down if wider than 1200px, preserving aspect ratio
    $origWidth  = imagesx($src);
    $origHeight = imagesy($src);
    $maxWidth   = 1200;

    if ($origWidth > $maxWidth) {
        $newWidth  = $maxWidth;
        $newHeight = (int) round($origHeight * ($maxWidth / $origWidth));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        imagedestroy($src);
        $src = $resized;
    }

    $uploadDir = __DIR__ . '/../uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $fullPath = $uploadDir . $filename;

    imagejpeg($src, $fullPath, 82); // 82 = good quality, reasonable file size
    imagedestroy($src);

    return 'uploads/products/' . $filename;
}

/**
 * Fetch every gallery photo for a product, in display order.
 */
function get_product_media(int $productId): array {
    $stmt = db()->prepare('SELECT * FROM product_media WHERE product_id = :pid ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['pid' => $productId]);
    return $stmt->fetchAll();
}

/**
 * Process any number of newly uploaded gallery photos for a product, appending them
 * after whatever's already there. Each file goes through the same real-content
 * validation and GD re-encoding as the primary product photo — multiple files
 * doesn't mean multiple chances to slip something malicious through.
 */
function save_product_media(int $productId, array $files): void {
    if (empty($files['name'][0] ?? null)) return; // nothing uploaded, nothing to do

    $pdo = db();
    $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), -1) FROM product_media WHERE product_id = ' . (int) $productId)->fetchColumn();

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;

        $singleFile = [
            'name' => $files['name'][$i], 'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i],
        ];
        try {
            $path = handle_product_image_upload($singleFile);
            if ($path) {
                $maxOrder++;
                $pdo->prepare('INSERT INTO product_media (product_id, image_url, sort_order) VALUES (:pid, :url, :ord)')
                    ->execute(['pid' => $productId, 'url' => $path, 'ord' => $maxOrder]);
            }
        } catch (Exception $e) {
            // One bad file in a batch shouldn't kill the rest — skip it, keep going.
            error_log('Gallery image skipped: ' . $e->getMessage());
        }
    }
}

/**
 * Delete a single gallery photo — checks it actually belongs to a product owned by
 * this business before touching anything, same ownership discipline as everywhere else.
 */
function delete_product_media(int $mediaId, int $businessId): void {
    $stmt = db()->prepare(
        'SELECT pm.id, pm.image_url FROM product_media pm
         JOIN products p ON p.id = pm.product_id
         WHERE pm.id = :mid AND p.business_id = :bid LIMIT 1'
    );
    $stmt->execute(['mid' => $mediaId, 'bid' => $businessId]);
    $media = $stmt->fetch();
    if (!$media) return;

    db()->prepare('DELETE FROM product_media WHERE id = :id')->execute(['id' => $mediaId]);
    delete_product_image($media['image_url']);
}

/**
 * Mark a product as this business's one signature dish — the animated showcase item.
 * Unsets any previous signature for the same business first, so there's only ever one.
 */
function set_signature_product(int $businessId, int $productId): void {
    $pdo = db();
    $pdo->prepare('UPDATE products SET is_signature = FALSE WHERE business_id = :bid')->execute(['bid' => $businessId]);
    $pdo->prepare('UPDATE products SET is_signature = TRUE WHERE id = :id AND business_id = :bid')
        ->execute(['id' => $productId, 'bid' => $businessId]);
}

function clear_signature_product(int $businessId): void {
    db()->prepare('UPDATE products SET is_signature = FALSE WHERE business_id = :bid')->execute(['bid' => $businessId]);
}

/**
 * Get the business's signature dish, if they've set one.
 */
function get_signature_product(int $businessId): ?array {
    $stmt = db()->prepare(
        "SELECT * FROM products WHERE business_id = :bid AND is_signature = TRUE AND status = 'active' LIMIT 1"
    );
    $stmt->execute(['bid' => $businessId]);
    return $stmt->fetch() ?: null;
}

/**
 * Delete a product image file from disk, given its stored relative path. Safe to call with null.
 */
function delete_product_image(?string $relativePath): void {
    if (!$relativePath) return;
    $fullPath = __DIR__ . '/../' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
