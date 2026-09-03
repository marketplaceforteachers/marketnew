<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/uploads.php';
$me = require_auth();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'avatar') {
    verify_csrf();
    $result = handle_image_upload($_FILES['avatar'] ?? [], 'avatars');
    if ($result['ok']) {
        db()->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$result['url'], $me['id']]);
        flash('success', 'Profile photo updated.');
    } else {
        flash('error', $result['error']);
    }
    redirect('/account.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'remove_avatar') {
    verify_csrf();
    db()->prepare('UPDATE users SET avatar_url = NULL WHERE id = ?')->execute([$me['id']]);
    flash('success', 'Profile photo removed.');
    redirect('/account.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'password') {
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'profile') {
    verify_csrf();
    $firstName = trim(post('first_name'));
    $lastName = trim(post('last_name'));
    if (strlen($firstName) < 1 || strlen($lastName) < 1) {
        $error = 'First and last name are required.';
    } else {
        db()->prepare('UPDATE users SET first_name = ?, last_name = ?, name = ?, phone = ?, address_line1 = ?, city = ?, state = ?, zip_code = ? WHERE id = ?')
            ->execute([
                $firstName, $lastName, trim("$firstName $lastName"),
                trim(post('phone')) ?: null,
                trim(post('address_line1')) ?: null,
                trim(post('city')) ?: null,
                trim(post('state')) ?: null,
                trim(post('zip_code')) ?: null,
                $me['id'],
            ]);
        flash('success', 'Profile updated.');
        redirect('/account.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'resend_verification') {
    verify_csrf();
    require_once __DIR__ . '/includes/resend.php';
    if (!$me['email_verified_at']) {
        $verification = create_email_verification((int) $me['id']);
        $verifyUrl = (defined('APP_URL') ? APP_URL : '') . '/verify-email.php?token=' . $verification['token'];
        send_transactional_email('email_verification', $me['email'], [
            'name' => $me['name'],
            'verify_url' => $verifyUrl,
            'code' => $verification['code'],
            'site_name' => get_setting('branding')['siteName'],
        ]);
    }
    flash('success', 'Verification email sent — check your inbox.');
    redirect('/account.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'verify_code') {
    verify_csrf();
    if (consume_email_verification_code((int) $me['id'], post('code'))) {
        flash('success', 'Email verified!');
    } else {
        flash('error', 'That code is invalid or has expired.');
    }
    redirect('/account.php');
}

$page_title = 'My Account';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-xl">My Account</h1>
  <p class="text-sm text-muted mt-1"><?= e($me['name']) ?> &middot; <?= e($me['email']) ?></p>

  <div class="card card-pad mt-4">
    <h2 class="text-lg">Profile Photo</h2>
    <div class="flex gap-4 mt-2" style="align-items:center;flex-wrap:wrap;">
      <?php if ($me['avatar_url']): ?>
        <img src="<?= e($me['avatar_url']) ?>" alt="" style="width:4.5rem;height:4.5rem;border-radius:999px;object-fit:cover;flex-shrink:0;">
      <?php else: ?>
        <span class="avatar-btn" style="width:4.5rem;height:4.5rem;font-size:1.5rem;border-radius:999px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <?= e(strtoupper(substr($me['name'], 0, 2))) ?>
        </span>
      <?php endif; ?>
      <div style="flex:1;min-width:14rem;">
        <form method="post" enctype="multipart/form-data" class="flex gap-2" style="align-items:center;flex-wrap:wrap;">
          <?= csrf_field() ?><input type="hidden" name="form" value="avatar">
          <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" required>
          <button type="submit" class="btn btn-outline" style="padding:.5rem .9rem;font-size:.82rem;">Upload</button>
        </form>
        <?php if ($me['avatar_url']): ?>
          <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="form" value="remove_avatar">
            <button type="submit" class="link text-xs" style="background:none;border:none;cursor:pointer;color:var(--red-600);">Remove photo</button>
          </form>
        <?php endif; ?>
        <p class="text-xs text-muted mt-1">JPEG, PNG, or WebP. Optional — buyers and sellers can leave this blank.</p>
      </div>
    </div>
  </div>

  <?php if (!$me['email_verified_at']): ?>
    <div class="flash flash-error mt-4">
      <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:.5rem;">
        <span><?= icon('alert-triangle') ?> Your email address isn't verified yet.</span>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="resend_verification">
          <button type="submit" class="link text-xs" style="background:none;border:none;cursor:pointer;">Resend verification email</button>
        </form>
      </div>
      <form method="post" class="flex gap-2 mt-2" style="align-items:center;flex-wrap:wrap;">
        <?= csrf_field() ?><input type="hidden" name="form" value="verify_code">
        <span class="text-xs">Link not working? Enter the 6-digit code from the email:</span>
        <input type="text" name="code" maxlength="6" placeholder="123456" style="width:7rem;">
        <button type="submit" class="btn btn-outline" style="padding:.35rem .75rem;font-size:.8rem;">Verify</button>
      </form>
    </div>
  <?php endif; ?>

  <?php if ($error): ?><div class="flash flash-error mt-4"><?= e($error) ?></div><?php endif; ?>

  <div class="card card-pad mt-4">
    <h2 class="text-lg">Profile</h2>
    <form method="post" class="mt-2">
      <?= csrf_field() ?><input type="hidden" name="form" value="profile">
      <div class="grid grid-2">
        <div class="field"><label>First Name</label><input type="text" name="first_name" value="<?= e($me['first_name'] ?? '') ?>" required></div>
        <div class="field"><label>Last Name</label><input type="text" name="last_name" value="<?= e($me['last_name'] ?? '') ?>" required></div>
      </div>
      <div class="field"><label>Phone</label><input type="tel" name="phone" value="<?= e($me['phone'] ?? '') ?>"></div>
      <div class="field"><label>Address</label><input type="text" name="address_line1" value="<?= e($me['address_line1'] ?? '') ?>"></div>
      <div class="grid grid-3">
        <div class="field"><label>City</label><input type="text" name="city" value="<?= e($me['city'] ?? '') ?>"></div>
        <div class="field"><label>State</label><input type="text" name="state" maxlength="2" style="text-transform:uppercase;" value="<?= e($me['state'] ?? '') ?>"></div>
        <div class="field"><label>ZIP</label><input type="text" name="zip_code" value="<?= e($me['zip_code'] ?? '') ?>"></div>
      </div>
      <button class="btn btn-primary mt-2">Save Profile</button>
    </form>
  </div>

  <div class="card card-pad mt-4">
    <h2 class="text-lg">Change Password</h2>
    <form method="post" class="mt-2">
      <?= csrf_field() ?><input type="hidden" name="form" value="password">
      <div class="field"><label>Current Password</label><input type="password" name="currentPassword" required></div>
      <div class="field"><label>New Password</label><input type="password" name="newPassword" required minlength="8"></div>
      <div class="field"><label>Confirm New Password</label><input type="password" name="confirmPassword" required minlength="8"></div>
      <button class="btn btn-primary mt-2">Update Password</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
