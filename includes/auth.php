<?php
// includes/auth.php — every registration, login, session, and role-check goes through here.

require_once __DIR__ . '/db.php';

/**
 * Start a session with secure settings. Call this at the top of every page.
 */
function start_secure_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,   // only send cookie over HTTPS in production/staging
        'httponly' => true,       // JS can never read the session cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Register a new user + their role-specific extension row, in one transaction.
 * Returns the new user's id, or throws an Exception with a user-facing message on failure.
 */
function register_user(string $name, string $email, string $phone, string $password, string $role): int {
    $allowedRoles = ['customer', 'business', 'rider'];
    if (!in_array($role, $allowedRoles, true)) {
        throw new Exception('Invalid role.');
    }
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters.');
    }

    $pdo = db();

    // Check for existing email/phone before attempting insert (friendlier error than a DB constraint failure)
    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email OR phone = :phone LIMIT 1');
    $check->execute(['email' => $email, 'phone' => $phone]);
    if ($check->fetch()) {
        throw new Exception('An account with that email or phone already exists.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, phone, password_hash, role, status)
             VALUES (:name, :email, :phone, :password_hash, :role, :status)'
        );
        // Business and rider accounts start pending until Stage 6 admin verification
        $status = ($role === 'customer') ? 'active' : 'pending';
        $stmt->execute([
            'name'          => $name,
            'email'         => $email,
            'phone'         => $phone,
            'password_hash' => $passwordHash,
            'role'          => $role,
            'status'        => $status,
        ]);
        $userId = (int) $pdo->lastInsertId();

        if ($role === 'business') {
            $slug = generate_unique_slug($name);
            $pdo->prepare('INSERT INTO businesses (user_id, business_name, slug) VALUES (:uid, :bname, :slug)')
                ->execute(['uid' => $userId, 'bname' => $name, 'slug' => $slug]);
        } elseif ($role === 'rider') {
            $pdo->prepare('INSERT INTO riders (user_id) VALUES (:uid)')
                ->execute(['uid' => $userId]);
        }

        $pdo->commit();
        return $userId;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Registration failed: ' . $e->getMessage());
        throw new Exception('Registration failed. Please try again.');
    }
}

/**
 * Attempt login. Returns the user row (array) on success, or null on failure.
 * Does not distinguish "wrong email" from "wrong password" in the message shown to the user —
 * that distinction is a gift to attackers, not to real users.
 */
function attempt_login(string $emailOrPhone, string $password): ?array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email_val OR phone = :phone_val LIMIT 1');
    $stmt->execute(['email_val' => $emailOrPhone, 'phone_val' => $emailOrPhone]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    if ($user['status'] === 'suspended') {
        throw new Exception('This account has been suspended. Contact support.');
    }
    return $user;
}

/**
 * Log a verified user into the session. Regenerates the session ID to prevent fixation attacks.
 */
function login_session(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['status']  = $user['status'];
}

function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function current_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Call at the top of any page that requires login. Redirects to /login.php if not authenticated.
 */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Call at the top of any role-specific page (e.g. business dashboard).
 * Redirects home if the logged-in user isn't the right role.
 */
function require_role(string $role): void {
    require_login();
    if (current_role() !== $role) {
        header('Location: /login.php');
        exit;
    }
}

function logout(): void {
    $_SESSION = [];
    session_destroy();
}

/**
 * Where to send a user right after login/registration, based on their role.
 */
function role_home_path(string $role): string {
    return match ($role) {
        'business' => '/business/dashboard.php',
        'rider'    => '/rider/dashboard.php',
        'admin'    => '/admin/overview.php',
        default    => '/customer/home.php',
    };
}

/**
 * Turn a business name into a clean, URL-safe slug, guaranteed unique against
 * existing businesses. Two shops both called "Mama Nkechi's Kitchen" get
 * mama-nkechis-kitchen and mama-nkechis-kitchen-2, not a collision.
 */
function generate_unique_slug(string $businessName): string {
    $base = strtolower(trim($businessName));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base); // anything not a-z0-9 becomes a hyphen
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'shop';
    }

    $pdo = db();
    $slug = $base;
    $suffix = 2;
    while (true) {
        $check = $pdo->prepare('SELECT id FROM businesses WHERE slug = :slug LIMIT 1');
        $check->execute(['slug' => $slug]);
        if (!$check->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $suffix;
        $suffix++;
    }
}

/**
 * Look up a business by its shopfront slug. If an older business somehow has
 * no slug yet (e.g. created before this feature existed), generate and save
 * one on the fly — no manual backfill script needed.
 */
function get_business_by_slug(string $slug): ?array {
    $stmt = db()->prepare('SELECT * FROM businesses WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    return $stmt->fetch() ?: null;
}

function ensure_business_has_slug(array $business): array {
    if (!empty($business['slug'])) {
        return $business;
    }
    $slug = generate_unique_slug($business['business_name']);
    db()->prepare('UPDATE businesses SET slug = :slug WHERE id = :id')
        ->execute(['slug' => $slug, 'id' => $business['id']]);
    $business['slug'] = $slug;
    return $business;
}

function business_shopfront_url(array $business): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'vendorly-staging.glovatech.com.ng';
    return $scheme . '://' . $host . '/' . $business['slug'];
}

/* ---------------------- Guest buyers (Tier 2: no password, real identity) ---------------------- */

/**
 * Create a guest buyer — a real users row with role=customer, status=guest, and an unusable
 * random password (they can't log in with it; they can only "claim" the account later by setting
 * a real one). Logs them into the session immediately, exactly like a normal login.
 *
 * If the phone or email already belongs to an existing ACTIVE account, we don't silently create
 * a duplicate — we tell them to log in instead, same as the registration duplicate check.
 *
 * Returns the new (or existing, if already an active guest session) user array.
 */
function start_guest_session(string $name, string $phone, ?string $email): array {
    if (trim($name) === '') {
        throw new Exception('Name is required.');
    }
    if (trim($phone) === '') {
        throw new Exception('Phone number is required.');
    }

    $pdo = db();

    // Does an ACTIVE (real, password-set) account already use this phone or email?
    $check = $pdo->prepare(
        "SELECT id, status FROM users WHERE phone = :phone_val OR (email = :email_val AND email IS NOT NULL) LIMIT 1"
    );
    $check->execute(['phone_val' => $phone, 'email_val' => $email]);
    $existing = $check->fetch();

    if ($existing && $existing['status'] !== 'guest') {
        throw new Exception('An account with that phone or email already exists. Please log in instead.');
    }

    if ($existing && $existing['status'] === 'guest') {
        // They've chatted/ordered as a guest before with this phone — reuse the same identity
        // rather than fragmenting their history across two guest rows.
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $existing['id']]);
        $user = $stmt->fetch();
        login_session($user);
        return $user;
    }

    // Brand new guest — email is optional, so fall back to a unique placeholder to satisfy the
    // UNIQUE constraint without colliding with anyone else's real email.
    $email = $email ?: ('guest_' . bin2hex(random_bytes(6)) . '@no-email.vendorly.local');
    $unusablePassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT); // nobody knows this; only "claim" can set a real one

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, phone, password_hash, role, status)
         VALUES (:name, :email, :phone, :password_hash, :role, :status)'
    );
    $stmt->execute([
        'name' => $name, 'email' => $email, 'phone' => $phone,
        'password_hash' => $unusablePassword, 'role' => 'customer', 'status' => 'guest',
    ]);
    $userId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();

    login_session($user);
    return $user;
}

function is_guest(): bool {
    return is_logged_in() && ($_SESSION['status'] ?? null) === 'guest';
}

/**
 * Upgrade the currently logged-in guest into a fully registered account by setting a real password.
 * Same user_id throughout — their order and chat history simply becomes visible under a real account,
 * because it was always attached to this row.
 */
function claim_guest_account(string $password, ?string $email = null): void {
    if (!is_guest()) {
        throw new Exception('No guest session to upgrade.');
    }
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters.');
    }

    $pdo = db();
    $userId = current_user_id();

    $fields = ['password_hash = :hash', 'status = :status'];
    $params = ['hash' => password_hash($password, PASSWORD_DEFAULT), 'status' => 'active', 'id' => $userId];

    if ($email) {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1');
        $check->execute(['email' => $email, 'id' => $userId]);
        if ($check->fetch()) {
            throw new Exception('That email is already in use by another account.');
        }
        $fields[] = 'email = :email';
        $params['email'] = $email;
    }

    $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
    $pdo->prepare($sql)->execute($params);

    $_SESSION['status'] = 'active'; // keep the session's cached status in sync immediately
}

/**
 * Generate a customer-facing order code in the GLV-##### format, guaranteed unique against
 * existing orders. Used for both guest and registered orders alike — a guest tracks their
 * order by this code, exactly as if they'd registered.
 */
function generate_order_code(): string {
    $pdo = db();
    do {
        $code = 'GLV-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $check = $pdo->prepare('SELECT id FROM orders WHERE order_code = :code LIMIT 1');
        $check->execute(['code' => $code]);
    } while ($check->fetch());
    return $code;
}

/* ---------------------- CSRF protection ---------------------- */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid request. Please refresh the page and try again.');
    }
}

/* ---------------------- Simple rate limiting (login attempts) ---------------------- */

function too_many_attempts(string $key, int $maxAttempts = 5, int $windowSeconds = 300): bool {
    $now = time();
    $bucket = $_SESSION['rate_limit'][$key] ?? ['count' => 0, 'start' => $now];

    if ($now - $bucket['start'] > $windowSeconds) {
        $bucket = ['count' => 0, 'start' => $now]; // window expired, reset
    }
    $bucket['count']++;
    $_SESSION['rate_limit'][$key] = $bucket;

    return $bucket['count'] > $maxAttempts;
}
