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

function register_user(string $name, string $email, string $password, string $role): ?array
{
    $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return null;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = db()->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $hash, $role]);
    $userId = (int) db()->lastInsertId();
    login_user($userId);

    enroll_in_drips($role === 'teacher' ? 'teacher_registered' : 'buyer_registered', $userId);

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    return $stmt->fetch();
}
