<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'Terms of Service';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-md py-10">
  <h1 class="text-2xl">Terms of Service</h1>
  <p class="text-sm text-muted mt-2">Last updated <?= date('F Y') ?></p>

  <div class="stack mt-6 text-sm" style="color:var(--slate-700);line-height:1.7;">
    <p><?= e($branding['siteName']) ?> ("we", "us") operates a peer-to-peer marketplace connecting verified
    USA teachers, schools, and classroom donors to buy, sell, exchange, and fund classroom supplies.
    By creating an account or using the site, you agree to these terms.</p>

    <h2 class="text-lg mt-4">Accounts &amp; Verification</h2>
    <p>Teacher accounts may submit school credentials for verification. We reserve the right to
    approve, reject, or revoke Verified Educator status at our discretion.</p>

    <h2 class="text-lg mt-4">Listings &amp; Transactions</h2>
    <p>Sellers are responsible for the accuracy of their listings. Payments are processed by
    Stripe, PayPal, or via school purchase order, subject to each provider's own terms. A platform
    fee is deducted from each sale as disclosed at checkout.</p>

    <h2 class="text-lg mt-4">Buyer Protection</h2>
    <p>Funds are held until delivery is confirmed or a dispute window closes. Disputes are reviewed
    by our team, which may issue a full refund, partial refund, or release funds to the seller.</p>

    <h2 class="text-lg mt-4">Prohibited Use</h2>
    <p>No counterfeit, unsafe, or non-classroom-related items. No harassment of other users. We may
    suspend accounts that violate these terms.</p>

    <h2 class="text-lg mt-4">Contact</h2>
    <p>Questions about these terms can be sent to
    <a class="link" href="mailto:<?= e($footer['supportEmail'] ?? get_setting('footer')['supportEmail']) ?>"><?= e($footer['supportEmail'] ?? get_setting('footer')['supportEmail']) ?></a>.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
