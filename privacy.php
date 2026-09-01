<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'Privacy & FERPA Protection';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-md py-10">
  <h1 class="text-2xl">Privacy &amp; FERPA Protection</h1>
  <p class="text-sm text-muted mt-2">Last updated <?= date('F Y') ?></p>

  <div class="stack mt-6 text-sm" style="color:var(--slate-700);line-height:1.7;">
    <p>We collect only what's needed to run the marketplace: account details, listing content,
    order/shipping information, and verification documents you choose to submit.</p>

    <h2 class="text-lg mt-4">Educator &amp; Student Data</h2>
    <p>We do not collect student records. Teacher verification documents (school ID, district
    email) are stored in restricted, non-public storage and reviewed only by our admin team, in
    line with FERPA's focus on protecting education records.</p>

    <h2 class="text-lg mt-4">Payment Data</h2>
    <p>Card and PayPal details are handled directly by Stripe and PayPal — we never see or store
    full payment card numbers.</p>

    <h2 class="text-lg mt-4">What We Share</h2>
    <p>We don't sell personal data. Order details are shared only with the buyer and seller
    involved in that transaction, as needed to complete it.</p>

    <h2 class="text-lg mt-4">Your Choices</h2>
    <p>You can request account deletion or a copy of your data at any time by contacting us.</p>

    <h2 class="text-lg mt-4">Contact</h2>
    <p>Privacy questions: <a class="link" href="mailto:<?= e($footer['supportEmail'] ?? get_setting('footer')['supportEmail']) ?>"><?= e($footer['supportEmail'] ?? get_setting('footer')['supportEmail']) ?></a>.</p>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
