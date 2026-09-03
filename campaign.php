<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int) param('id');
$stmt = db()->prepare("SELECT c.*, u.name AS teacher_name FROM fundraising_campaigns c JOIN users u ON u.id = c.teacher_id WHERE c.id = ?");
$stmt->execute([$id]);
$campaign = $stmt->fetch();
if (!$campaign) {
    http_response_code(404);
    $page_title = 'Not Found';
    $page_noindex = true;
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="container py-10 text-center"><p>Campaign not found.</p></div>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

$stmt = db()->prepare('SELECT donor_name, amount, created_at FROM donations WHERE campaign_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$id]);
$donations = $stmt->fetchAll();

$stripeGateway = get_gateway('stripe');
$pct = $campaign['target_funds'] > 0 ? min(100, round($campaign['current_funds'] / $campaign['target_funds'] * 100)) : 0;
$page_title = $campaign['title'];
$page_description = $campaign['story'] ? truncate($campaign['story'], 160) : ($campaign['teacher_name'] . "'s classroom fundraising campaign on " . get_setting('branding')['siteName'] . '.');
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-2xl"><?= e($campaign['title']) ?></h1>
  <p class="text-sm text-muted mt-1">by <?= e($campaign['teacher_name']) ?></p>
  <p class="text-sm mt-4" style="white-space:pre-line;"><?= e($campaign['story']) ?></p>

  <div class="progress-track mt-5"><div class="progress-fill" style="width:<?= $pct ?>%;background:var(--amber-500);"></div></div>
  <p class="text-sm font-bold mt-2"><?= money((float) $campaign['current_funds']) ?> raised of <?= money((float) $campaign['target_funds']) ?> goal</p>

  <div id="thank-you" class="card card-pad mt-6 hidden" style="background:var(--emerald-50);border-color:var(--emerald-100);">
    <p style="color:var(--emerald-700);font-weight:700;">Thank you for your donation! A receipt has been emailed to you.</p>
  </div>

  <div id="donate-card" class="card card-pad mt-6">
    <h2 class="text-lg">Make a donation</h2>
    <?php if (!$stripeGateway['isEnabled']): ?>
      <p class="text-sm mt-2" style="color:var(--amber-600);">Donations aren't enabled yet — an admin needs to configure Stripe.</p>
    <?php else: ?>
      <div id="donate-form">
        <div class="flex gap-2 mt-2" style="flex-wrap:wrap;">
          <button type="button" class="btn btn-outline" data-amount="10">$10</button>
          <button type="button" class="btn btn-outline" data-amount="25">$25</button>
          <button type="button" class="btn btn-outline" data-amount="50">$50</button>
          <button type="button" class="btn btn-outline" data-amount="100">$100</button>
          <input type="number" id="donate-amount" value="25" style="width:6rem;">
        </div>
        <div class="field mt-2"><input type="text" id="donor-name" placeholder="Your name"></div>
        <div class="field"><input type="email" id="donor-email" placeholder="Email"></div>
        <p id="donate-error" class="flash flash-error hidden"></p>
        <button type="button" id="donate-continue" class="btn btn-amber">Continue to payment</button>
      </div>
      <div id="donate-payment" class="mt-3 hidden"></div>
    <?php endif; ?>
  </div>

  <h3 class="text-lg mt-6">Recent donors</h3>
  <ul class="stack mt-2 text-sm">
    <?php foreach ($donations as $d): ?><li><?= e($d['donor_name'] ?: 'Anonymous') ?> gave <?= money((float) $d['amount']) ?></li><?php endforeach; ?>
  </ul>
</div>

<?php if ($stripeGateway['isEnabled']): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
document.querySelectorAll('[data-amount]').forEach((btn) => btn.addEventListener('click', () => {
  document.getElementById('donate-amount').value = btn.dataset.amount;
}));

document.getElementById('donate-continue').addEventListener('click', async () => {
  const amount = Number(document.getElementById('donate-amount').value);
  const donorName = document.getElementById('donor-name').value;
  const donorEmail = document.getElementById('donor-email').value;
  const errorEl = document.getElementById('donate-error');
  if (!amount || !donorName || !donorEmail) {
    errorEl.textContent = 'Enter your name, email, and an amount.'; errorEl.classList.remove('hidden'); return;
  }
  const res = await fetch('/api/ajax/donation_intent.php', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ campaignId: <?= $id ?>, amount, donorName, donorEmail }),
  });
  const data = await res.json();
  if (!res.ok) { errorEl.textContent = data.error; errorEl.classList.remove('hidden'); return; }

  document.getElementById('donate-form').classList.add('hidden');
  const panel = document.getElementById('donate-payment');
  panel.classList.remove('hidden');
  const stripe = Stripe('<?= e($stripeGateway['config']['publishableKey']) ?>');
  const elements = stripe.elements({ clientSecret: data.clientSecret });
  panel.innerHTML = '<div id="stripe-el"></div><button id="donate-pay-btn" class="btn btn-amber w-full mt-3" style="justify-content:center;">Donate now</button>';
  elements.create('payment').mount('#stripe-el');
  document.getElementById('donate-pay-btn').addEventListener('click', async () => {
    const result = await stripe.confirmPayment({ elements, redirect: 'if_required' });
    if (result.error) { errorEl.textContent = result.error.message; errorEl.classList.remove('hidden'); return; }
    if (result.paymentIntent) await fetch('/api/ajax/donation_confirm.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ paymentIntentId: result.paymentIntent.id }) });
    document.getElementById('donate-card').classList.add('hidden');
    document.getElementById('thank-you').classList.remove('hidden');
  });
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
