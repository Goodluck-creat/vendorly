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
            $pdo->prepare('INSERT INTO businesses (user_id, business_name) VALUES (:uid, :bname)')
                ->execute(['uid' => $userId, 'bname' => $name]);
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
