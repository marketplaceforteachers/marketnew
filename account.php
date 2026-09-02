<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_auth();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $currentPassword = post('currentPassword');
    $newPassword = post('newPassword');
    $confirmPassword = post('confirmPassword');

    if (!password_verify($currentPassword, $me['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New password and confirmation do not match.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $me['id']]);
        flash('success', 'Password updated.');
        redirect('/account.php');
    }
}

$page_title = 'My Account';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-xl">My Account</h1>
  <p class="text-sm text-muted mt-1"><?= e($me['name']) ?> &middot; <?= e($me['email']) ?></p>

  <div class="card card-pad mt-4">
    <h2 class="text-lg">Change Password</h2>
    <?php if ($error): ?><div class="flash flash-error mt-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="mt-2">
      <?= csrf_field() ?>
      <div class="field"><label>Current Password</label><input type="password" name="currentPassword" required></div>
      <div class="field"><label>New Password</label><input type="password" name="newPassword" required minlength="8"></div>
      <div class="field"><label>Confirm New Password</label><input type="password" name="confirmPassword" required minlength="8"></div>
      <button class="btn btn-primary mt-2">Update Password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
