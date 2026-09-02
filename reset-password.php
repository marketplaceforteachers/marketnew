<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

$token = $_GET['token'] ?? post('token', '');
$user = $token ? get_user_for_reset_token($token) : null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    verify_csrf();
    $newPassword = post('newPassword');
    $confirmPassword = post('confirmPassword');

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        consume_password_reset($token, $newPassword);
        flash('success', 'Password updated — you can now log in.');
        redirect('/login.php');
    }
}

$page_title = 'Reset Password';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-10">
  <div class="card card-pad" style="max-width:420px;margin:0 auto;">
    <h1 class="text-lg">Set a new password</h1>
    <?php if (!$user): ?>
      <p class="text-sm mt-3">This reset link is invalid or has expired.</p>
      <a href="/forgot-password.php" class="link text-xs" style="display:block;margin-top:.75rem;">Request a new link &rarr;</a>
    <?php else: ?>
      <p class="text-sm text-muted mt-1">for <?= e($user['email']) ?></p>
      <?php if ($error): ?><div class="flash flash-error mt-2"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field"><label>New Password</label><input type="password" name="newPassword" required minlength="8"></div>
        <div class="field"><label>Confirm New Password</label><input type="password" name="confirmPassword" required minlength="8"></div>
        <button class="btn btn-primary w-full" style="justify-content:center;">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
