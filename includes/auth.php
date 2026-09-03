<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/drips.php';

function current_user(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    if (empty($_SESSION['user_id'])) {
        $cached = null;
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $cached = $user ?: null;
    return $cached;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        redirect('/login.php?redirect=' . $redirect);
    }
    if ($user['is_banned']) {
        session_destroy();
        redirect('/login.php?banned=1');
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_auth();
    if (!in_array($user['role'], $roles, true)) {
        redirect('/index.php');
    }
    return $user;
}

function require_admin(): array
{
    return require_role('admin');
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

function attempt_login(string $email, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    if ($user['is_banned']) {
        return null;
    }
    login_user((int) $user['id']);
    return $user;
}

/** Basic brute-force throttle: max 10 failed attempts per IP per 15-minute rolling window. */
function is_login_rate_limited(string $ip): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM login_attempts WHERE ip_address = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
    $stmt->execute([$ip]);
    return (int) $stmt->fetch()['c'] >= 10;
}

function record_login_attempt(string $ip): void
{
    db()->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$ip]);
}

/** Issues a password reset token (returns the raw token to email; only its hash is stored). */
function create_password_reset(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
        ->execute([$userId, $hash]);
    return $token;
}

/** Returns the user row for a valid, unexpired, unused reset token, or null. */
function get_user_for_reset_token(string $token): ?array
{
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        "SELECT u.* FROM password_resets pr JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1"
    );
    $stmt->execute([$hash]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function consume_password_reset(string $token, string $newPassword): void
{
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        "SELECT pr.id, pr.user_id FROM password_resets pr
         WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1"
    );
    $stmt->execute([$hash]);
    $reset = $stmt->fetch();
    if (!$reset) {
        return;
    }
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$passwordHash, $reset['user_id']]);
    db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$reset['id']]);
}

/** Issues an email-verification token (24h expiry) and returns the raw token to embed in the email link. */
function create_email_verification(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    db()->prepare('INSERT INTO email_verifications (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')
        ->execute([$userId, $hash]);
    return $token;
}

/** Marks the matching user's email verified and consumes the token. Returns true if a valid token was found. */
function consume_email_verification(string $token): bool
{
    $hash = hash('sha256', $token);
    $stmt = db()->prepare('SELECT id, user_id FROM email_verifications WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$row['user_id']]);
    db()->prepare('DELETE FROM email_verifications WHERE user_id = ?')->execute([$row['user_id']]);
    return true;
}

function register_user(string $firstName, string $lastName, string $email, string $password, string $role, array $profile = []): ?array
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return null;
    }
    $name = trim("$firstName $lastName");
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare(
        'INSERT INTO users (name, first_name, last_name, email, password_hash, role, account_type, phone, zip_code, school_name, school_email, district, state)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $name, $firstName, $lastName, $email, $hash, $role,
        $profile['account_type'] ?? null,
        $profile['phone'] ?? null,
        $profile['zip_code'] ?? null,
        $profile['school_name'] ?? null,
        $profile['school_email'] ?? null,
        $profile['district'] ?? null,
        $profile['state'] ?? null,
    ]);
    $userId = (int) db()->lastInsertId();
    login_user($userId);

    enroll_in_drips($role === 'teacher' ? 'teacher_registered' : 'buyer_registered', $userId);

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}
