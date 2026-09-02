<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/resend.php';

if (current_user()) {
    redirect('/index.php');
}

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $email = trim(post('email'));

    if (!is_login_rate_limited($ip)) {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        record_login_attempt($ip); // also throttles reset requests from the same IP

        if ($user && !$user['is_banned']) {
            $token = create_password_reset((int) $user['id']);
            $resetUrl = (defined('APP_URL') ? APP_URL : '') . '/reset-password.php?token=' . $token;
            send_transactional_email('password_reset', $user['email'], [
                'name' => $user['name'],
                'reset_url' => $resetUrl,
                'site_name' => get_setting('branding')['siteName'],
            ]);
        }
    }
    // Always show the same message, whether or not that email exists — don't leak account existence.
    $sent = true;
}

$page_title = 'Forgot Password';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-10">
  <div class="card card-pad" style="max-width:420px;margin:0 auto;">
    <h1 class="text-lg">Reset your password</h1>
    <?php if ($sent): ?>
      <p class="text-sm mt-3">If an account exists for that email, we've sent a link to reset your password. It expires in 1 hour.</p>
    <?php else: ?>
      <p class="text-sm text-muted mt-1">Enter your account email and we'll send you a reset link.</p>
      <form method="post" class="mt-4">
        <?= csrf_field() ?>
        <div class="field"><label>Email</label><input type="email" name="email" required></div>
        <button class="btn btn-primary w-full" style="justify-content:center;">Send Reset Link</button>
      </form>
    <?php endif; ?>
    <a href="/login.php" class="link text-xs" style="display:block;text-align:center;margin-top:.75rem;">&larr; Back to log in</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
