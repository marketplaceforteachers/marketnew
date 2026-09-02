<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (current_user()) {
    redirect('/index.php');
}

$mode = ($_GET['mode'] ?? 'login') === 'register' ? 'register' : 'login';
$redirectTo = $_GET['redirect'] ?? '/index.php';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $mode = post('mode', 'login');
    $email = trim(post('email'));
    $password = post('password');

    if ($mode === 'register') {
        $name = trim(post('name'));
        $role = in_array(post('role'), ['teacher', 'buyer'], true) ? post('role') : 'buyer';
        $profile = [
            'school_name' => trim(post('school_name')) ?: null,
            'district' => trim(post('district')) ?: null,
            'state' => trim(post('state')) ?: null,
        ];
        if (strlen($name) < 2 || strlen($password) < 8) {
            $error = 'Name must be at least 2 characters and password at least 8 characters.';
        } else {
            $user = register_user($name, $email, $password, $role, $profile);
            if (!$user) {
                $error = 'An account with that email already exists.';
                $mode = 'register';
            } else {
                send_transactional_email('welcome', $user['email'], ['teacher_name' => $user['name'], 'site_name' => get_setting('branding')['siteName']]);
                redirect(post('redirect_to', '/index.php'));
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
                redirect(post('redirect_to', '/index.php'));
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
      <?php if ($mode === 'register'): ?>
        <div class="field"><label>Full Name</label><input type="text" name="name" required></div>
      <?php endif; ?>
      <div class="field"><label>Email</label><input type="email" name="email" required></div>
      <div class="field"><label>Password</label><input type="password" name="password" required minlength="8"></div>
      <?php if ($mode === 'register'): ?>
        <div class="field">
          <label>I am a…</label>
          <select name="role" id="role-select" onchange="document.getElementById('teacher-fields').classList.toggle('hidden', this.value !== 'teacher')">
            <option value="teacher">Teacher (selling &amp; buying)</option>
            <option value="buyer">Buyer / Parent / Donor</option>
          </select>
        </div>
        <div id="teacher-fields">
          <div class="field"><label>School Name</label><input type="text" name="school_name" placeholder="e.g. Pennsylvania Elementary"></div>
          <div class="grid grid-2">
            <div class="field"><label>District</label><input type="text" name="district" placeholder="e.g. Oklahoma City Public Schools"></div>
            <div class="field"><label>State</label><input type="text" name="state" maxlength="2" placeholder="OK" style="text-transform:uppercase;"></div>
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
