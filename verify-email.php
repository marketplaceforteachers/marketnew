<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/resend.php';

$token = $_GET['token'] ?? '';
$linkAttempted = $token !== '';
$success = $linkAttempted ? consume_email_verification($token) : false;
$codeError = null;
$resent = false;

if (!$success && $_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'verify_code') {
    verify_csrf();
    $me = current_user();
    if (!$me) {
        redirect('/login.php?redirect=' . urlencode('/verify-email.php'));
    }
    if (consume_email_verification_code((int) $me['id'], post('code'))) {
        $success = true;
    } else {
        $codeError = 'That code is invalid or has expired.';
    }
} elseif (!$success && $_SERVER['REQUEST_METHOD'] === 'POST' && post('form') === 'resend_verification') {
    verify_csrf();
    $me = current_user();
    if (!$me) {
        redirect('/login.php?redirect=' . urlencode('/verify-email.php'));
    }
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
    $resent = true;
}

$page_title = 'Verify Email';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-10">
  <div class="card card-pad text-center" style="max-width:420px;margin:0 auto;">
    <?php if ($success): ?>
      <span style="color:var(--emerald-600);"><?= icon('badge-check') ?></span>
      <h1 class="text-lg mt-2">Email verified!</h1>
      <p class="text-sm text-muted mt-2">Your email address is confirmed. Thanks for verifying.</p>
      <a href="<?= current_user() ? '/account.php' : '/login.php' ?>" class="btn btn-primary mt-4">
        <?= current_user() ? 'Go to My Account' : 'Log In' ?>
      </a>
    <?php else: ?>
      <?php if ($linkAttempted): ?>
        <h1 class="text-lg">This link is invalid or has expired</h1>
        <p class="text-sm text-muted mt-2">Verification links expire after 24 hours.</p>
      <?php else: ?>
        <h1 class="text-lg">Verify your email address</h1>
        <p class="text-sm text-muted mt-2">We sent a verification link and a 6-digit code to your email. Click the link, or enter the code below.</p>
      <?php endif; ?>

      <?php if (current_user()): ?>
        <?php if ($resent): ?><div class="flash flash-success mt-3">Verification email sent — check your inbox.</div><?php endif; ?>
        <?php if ($codeError): ?><div class="flash flash-error mt-3"><?= e($codeError) ?></div><?php endif; ?>
        <form method="post" class="mt-4 text-left">
          <?= csrf_field() ?><input type="hidden" name="form" value="verify_code">
          <div class="field"><label>6-digit code</label><input type="text" name="code" maxlength="6" placeholder="123456"></div>
          <button class="btn btn-primary w-full" style="justify-content:center;">Verify with Code</button>
        </form>
        <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="form" value="resend_verification">
          <button type="submit" class="link text-xs" style="background:none;border:none;cursor:pointer;">Resend the email &rarr;</button>
        </form>
      <?php else: ?>
        <a href="/login.php" class="link text-xs" style="display:block;margin-top:.75rem;">Log In &rarr;</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
