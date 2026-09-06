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
 * Delete a product image file from disk, given its stored relative path. Safe to call with null.
 */
function delete_product_image(?string $relativePath): void {
    if (!$relativePath) return;
    $fullPath = __DIR__ . '/../' . $relativePath;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}
