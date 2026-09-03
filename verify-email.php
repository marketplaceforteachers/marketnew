<?php
require_once __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? '';
$success = $token ? consume_email_verification($token) : false;

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
      <p class="text-sm text-muted mt-2">Verification links expire after 24 hours. If you're logged in, you can request a new one from your account page.</p>
      <a href="<?= current_user() ? '/account.php' : '/login.php' ?>" class="link text-xs" style="display:block;margin-top:.75rem;">
        <?= current_user() ? 'Go to My Account' : 'Log In' ?> &rarr;
      </a>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
