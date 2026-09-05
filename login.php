<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$redirectTo = safe_local_redirect_path($_GET['redirect'] ?? null);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $mode = post('mode', 'login');
    $email = trim(post('email'));
    $password = post('password');

    if ($mode === 'register') {
        $firstName = trim(post('first_name'));
        $lastName = trim(post('last_name'));
        $role = in_array(post('role'), ['teacher', 'buyer'], true) ? post('role') : 'buyer';
        $accountTypeOptions = $role === 'teacher'
            ? ['teacher', 'educator', 'school']
            : ['parent', 'donor', 'other'];
        $submittedAccountType = $role === 'teacher' ? post('account_type_teacher') : post('account_type_buyer');
        $profile = [
            'account_type' => in_array($submittedAccountType, $accountTypeOptions, true) ? $submittedAccountType : null,
            'phone' => trim(post('phone')) ?: null,
            'zip_code' => trim(post('zip_code')) ?: null,
            'school_name' => trim(post('school_name')) ?: null,
            'school_email' => trim(post('school_email')) ?: null,
            'district' => trim(post('district')) ?: null,
            'state' => trim(post('state')) ?: null,
        ];
        if (strlen($firstName) < 1 || strlen($lastName) < 1) {
            $error = 'First and last name are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!trim(post('phone'))) {
            $error = 'Phone number is required.';
        } elseif (!trim(post('zip_code'))) {
            $error = 'ZIP/postal code is required.';
        } else {
            $user = register_user($firstName, $lastName, $email, $password, $role, $profile);
            if (!$user) {
                $error = 'An account with that email already exists.';
                $mode = 'register';
            } else {
                send_transactional_email('welcome', $user['email'], ['teacher_name' => $user['name'], 'site_name' => get_setting('branding')['siteName']]);
                $verification = create_email_verification((int) $user['id']);
                $verifyUrl = (defined('APP_URL') ? APP_URL : '') . '/verify-email.php?token=' . $verification['token'];
                send_transactional_email('email_verification', $user['email'], [
                    'name' => $user['name'],
                    'verify_url' => $verifyUrl,
                    'code' => $verification['code'],
                    'site_name' => get_setting('branding')['siteName'],
                ]);
                redirect(safe_local_redirect_path(post('redirect_to', '/index.php')));
            }
        }
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (is_login_rate_limited($ip)) {
            $error = 'Too many login attempts. Please wait 15 minutes and try again.';
        } else {
            $user = attempt_login($email, $password);
            if (!$user) {
                record_login_attempt($ip);
                $error = 'Invalid email or password.';
            } else {
                redirect(safe_local_redirect_path(post('redirect_to', '/index.php')));
            }
        }
    }
}

require_once __DIR__ . '/includes/resend.php';
$page_title = 'Log In';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-10">
  <div class="card card-pad" style="max-width:420px;margin:0 auto;">
    <div class="flex" style="background:var(--surface-2);border-radius:.5rem;padding:.25rem;">
      <a href="/login.php?mode=login" class="btn w-full" style="justify-content:center;background:<?= $mode === 'login' ? 'var(--royal-600)' : 'transparent' ?>;color:<?= $mode === 'login' ? '#fff' : 'var(--slate-500)' ?>;">Log In</a>
      <a href="/login.php?mode=register" class="btn w-full" style="justify-content:center;background:<?= $mode === 'register' ? 'var(--royal-600)' : 'transparent' ?>;color:<?= $mode === 'register' ? '#fff' : 'var(--slate-500)' ?>;">Sign Up</a>
    </div>

    <form method="post" class="mt-4">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="<?= e($mode) ?>">
      <input type="hidden" name="redirect_to" value="<?= e($redirectTo) ?>">
      <?php if ($mode === 'register'): $oldRole = post('role') ?: 'teacher'; ?>
        <div class="grid grid-2">
          <div class="field"><label>First Name</label><input type="text" name="first_name" required value="<?= old('first_name') ?>"></div>
          <div class="field"><label>Last Name</label><input type="text" name="last_name" required value="<?= old('last_name') ?>"></div>
        </div>
      <?php endif; ?>
      <div class="field"><label>Email</label><input type="email" name="email" required value="<?= old('email') ?>"></div>
      <div class="field"><label>Password</label><input type="password" name="password" required minlength="8"></div>
      <?php if ($mode === 'register'): ?>
        <div class="grid grid-2">
          <div class="field"><label>Phone Number</label><input type="tel" name="phone" required value="<?= old('phone') ?>"></div>
          <div class="field"><label>ZIP / Postal Code</label><input type="text" name="zip_code" required value="<?= old('zip_code') ?>"></div>
        </div>
        <div class="field">
          <label>I am a…</label>
          <select name="role" id="role-select" onchange="
            document.getElementById('teacher-fields').classList.toggle('hidden', this.value !== 'teacher');
            document.getElementById('account-type-teacher').classList.toggle('hidden', this.value !== 'teacher');
            document.getElementById('account-type-buyer').classList.toggle('hidden', this.value === 'teacher');
          ">
            <option value="teacher" <?= $oldRole === 'teacher' ? 'selected' : '' ?>>Teacher (selling &amp; buying)</option>
            <option value="buyer" <?= $oldRole === 'buyer' ? 'selected' : '' ?>>Buyer / Parent / Donor</option>
          </select>
        </div>
        <div class="field <?= $oldRole === 'teacher' ? '' : 'hidden' ?>" id="account-type-teacher">
          <label>Account Type</label>
          <select name="account_type_teacher">
            <option value="teacher" <?= old('account_type_teacher') === 'teacher' ? 'selected' : '' ?>>Teacher</option>
            <option value="educator" <?= old('account_type_teacher') === 'educator' ? 'selected' : '' ?>>Educator (non-classroom)</option>
            <option value="school" <?= old('account_type_teacher') === 'school' ? 'selected' : '' ?>>School / Institution</option>
          </select>
        </div>
        <div class="field <?= $oldRole === 'buyer' ? '' : 'hidden' ?>" id="account-type-buyer">
          <label>Account Type</label>
          <select name="account_type_buyer">
            <option value="parent" <?= old('account_type_buyer') === 'parent' ? 'selected' : '' ?>>Parent</option>
            <option value="donor" <?= old('account_type_buyer') === 'donor' ? 'selected' : '' ?>>Donor</option>
            <option value="other" <?= old('account_type_buyer') === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div id="teacher-fields" class="<?= $oldRole === 'teacher' ? '' : 'hidden' ?>">
          <div class="field"><label>School or District Name</label><input type="text" name="school_name" placeholder="e.g. Pennsylvania Elementary" value="<?= old('school_name') ?>"></div>
          <div class="field"><label>School/District Email <span class="text-muted" style="text-transform:none;font-weight:400;">(for verification, optional)</span></label><input type="email" name="school_email" placeholder="you@yourschool.edu" value="<?= old('school_email') ?>"></div>
          <div class="grid grid-2">
            <div class="field"><label>District</label><input type="text" name="district" placeholder="e.g. Oklahoma City Public Schools" value="<?= old('district') ?>"></div>
            <div class="field"><label>State</label><input type="text" name="state" maxlength="2" placeholder="OK" style="text-transform:uppercase;" value="<?= old('state') ?>"></div>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <button class="btn btn-primary w-full mt-2" style="justify-content:center;"><?= $mode === 'register' ? 'Create Account' : 'Log In' ?></button>
    </form>
    <p class="text-xs text-center mt-3"><a href="/forgot-password.php" class="link">Forgot your password?</a></p>
    <a href="/index.php" class="link text-xs" style="display:block;text-align:center;margin-top:.5rem;">&larr; Back to marketplace</a>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
