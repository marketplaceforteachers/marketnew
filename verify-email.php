<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';
$success = $token ? consume_email_verification($token) : false;
$codeError = null;

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
      <a href="<?= current_user() ? '/index.php' : '/login.php' ?>" class="btn btn-primary mt-4">
        <?= current_user() ? 'Back to Marketplace' : 'Log In' ?>
      </a>
    <?php else: ?>
      <h1 class="text-lg">This link is invalid or has expired</h1>
      <p class="text-sm text-muted mt-2">Verification links expire after 24 hours.</p>

      <?php if (current_user()): ?>
        <?php if ($codeError): ?><div class="flash flash-error mt-3"><?= e($codeError) ?></div><?php endif; ?>
        <form method="post" class="mt-4 text-left">
          <?= csrf_field() ?><input type="hidden" name="form" value="verify_code">
          <div class="field"><label>Enter the 6-digit code from your email instead</label><input type="text" name="code" maxlength="6" placeholder="123456"></div>
          <button class="btn btn-primary w-full" style="justify-content:center;">Verify with Code</button>
        </form>
        <a href="/account.php" class="link text-xs" style="display:block;margin-top:.75rem;">Resend the email &rarr;</a>
      <?php else: ?>
        <a href="/login.php" class="link text-xs" style="display:block;margin-top:.75rem;">Log In &rarr;</a>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
