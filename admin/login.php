<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$redirectTo = safe_local_redirect_path($_GET['redirect'] ?? post('redirect_to', '/admin/index.php'));
if (!str_starts_with($redirectTo, '/admin/')) {
    $redirectTo = '/admin/index.php';
}

$me = current_user();
if ($me && $me['role'] === 'admin') {
    redirect($redirectTo);
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (is_login_rate_limited($ip)) {
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } else {
        // Check the password and the role before logging anyone in, so a non-admin account never
        // gets a session from this page. The error is the same either way, so this page doesn't
        // reveal which emails belong to admins.
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([trim(post('email'))]);
        $user = $stmt->fetch();
        if (!$user || $user['is_banned'] || $user['role'] !== 'admin' || !password_verify(post('password'), $user['password_hash'])) {
            record_login_attempt($ip);
            $error = "That email and password don't match an admin account.";
        } else {
            login_user((int) $user['id']);
            log_admin_action((int) $user['id'], 'admin_login', 'user', (int) $user['id']);
            redirect($redirectTo);
        }
    }
}

$branding = get_setting('branding');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin sign in — <?= e($branding['siteName']) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@1,600;1,700&display=swap">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= @filemtime(__DIR__ . '/../assets/css/style.css') ?: '1' ?>">
</head>
<body class="admin-login-page">
  <div class="admin-login-wrap">
    <div class="admin-login-brand"><?= render_logo($branding, 'dark') ?></div>
    <div class="admin-login-card">
      <span class="section-eyebrow">Admin</span>
      <h1 class="section-title mt-1">Sign in to the <em class="serif-accent">back office</em></h1>
      <p class="text-sm text-muted mt-2">For site administrators only. Teachers and buyers can <a class="link" href="/login.php">log in here</a>.</p>

      <?php foreach (get_flashes() as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?> mt-4"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="flash flash-error mt-4" role="alert"><?= e($error) ?></div><?php endif; ?>

      <form method="post" class="mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
        <div class="field"><label for="admin-email">Email</label><input id="admin-email" type="email" name="email" required autocomplete="username" autofocus value="<?= old('email') ?>"></div>
        <div class="field"><label for="admin-password">Password</label><input id="admin-password" type="password" name="password" required autocomplete="current-password"></div>
        <button class="btn btn-primary btn-lg w-full mt-2"><?= icon('lock') ?> Sign in</button>
      </form>
      <p class="text-xs text-muted text-center mt-4"><a class="link" href="/forgot-password.php">Forgot your password?</a> &middot; <a class="link" href="/index.php">Back to the site</a></p>
    </div>
  </div>
</body>
</html>
