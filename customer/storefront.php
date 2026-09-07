<?php
require_once __DIR__ . '/../includes/marketplace.php';
start_secure_session();
// The canonical shopfront now lives at /{slug} (see shop.php + .htaccess rewrite).
// This file just catches any old ?id= links and forwards them to the clean URL,
// generating a slug on the fly for any business that predates this feature.

$businessId = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM businesses WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $businessId]);
$business = $stmt->fetch();

if (!$business) {
    header('Location: /customer/home.php');
    exit;
}

$business = ensure_business_has_slug($business);
header('Location: /' . $business['slug'], true, 301);
exit;
