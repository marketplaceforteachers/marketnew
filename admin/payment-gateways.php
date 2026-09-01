<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save_fee') {
        set_setting('fees', ['platformFeePercent' => (float) post('platformFeePercent')]);
        log_admin_action($me['id'], 'settings.update', 'site_settings', 'fees');
        flash('success', 'Platform fee saved.');
    } elseif ($action === 'save_gateway') {
        $gateway = post('gateway');
        if (in_array($gateway, GATEWAY_IDS, true)) {
            $current = get_gateway($gateway)['config'];
            $isEnabled = post('isEnabled') === '1';
            foreach (default_gateway_config($gateway) as $key => $default) {
                $value = trim(post("cfg_$key", ''));
                if ($value !== '' && !str_contains($value, '•')) {
                    $current[$key] = $value;
                } elseif (str_contains($value, '•')) {
                    // masked/unchanged — keep existing
                } else {
                    $current[$key] = $value;
                }
            }
            set_gateway($gateway, $isEnabled, $current);
            log_admin_action($me['id'], 'payment_gateway.update', 'payment_gateway_configs', $gateway);
            flash('success', ucfirst($gateway) . ' settings saved.');
        }
    }
    redirect('/admin/payment-gateways.php');
}

$gateways = get_all_gateways();
$fees = get_setting('fees');
$secretFields = ['stripe' => ['secretKey', 'webhookSecret'], 'paypal' => ['clientSecret'], 'school_po' => []];
$fieldLabels = [
    'publishableKey' => 'Publishable key', 'secretKey' => 'Secret key', 'webhookSecret' => 'Webhook signing secret',
    'clientId' => 'Client ID', 'clientSecret' => 'Client secret', 'environment' => 'Environment (sandbox / live)',
    'instructions' => 'Buyer instructions', 'payableTo' => 'Payable to',
];
function is_gateway_configured(string $gateway, array $config): bool
{
    $required = ['stripe' => ['secretKey'], 'paypal' => ['clientId', 'clientSecret'], 'school_po' => []];
    if (!$required[$gateway]) return true;
    foreach ($required[$gateway] as $f) {
        if (empty($config[$f])) return false;
    }
    return true;
}

$page_title = 'Payment Gateways';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Payment Gateway Settings</h1>
<p class="text-sm text-muted mt-1">Add live keys here to turn on real checkout. Secret fields already saved show masked — leave untouched to keep them, or type a new value to replace them.</p>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="action" value="save_fee">
  <h2 class="text-lg">Platform fee</h2>
  <p class="text-xs text-muted mt-1">Percent withheld from each sale before seller payout.</p>
  <div class="flex items-center gap-3 mt-2">
    <input type="number" step="0.1" min="0" max="100" name="platformFeePercent" value="<?= e((string) $fees['platformFeePercent']) ?>" style="width:7rem;">
    <span>%</span>
    <button class="btn btn-primary">Save</button>
  </div>
</form>

<?php foreach (['stripe' => 'Stripe', 'paypal' => 'PayPal', 'school_po' => 'School Purchase Order (Manual Invoicing)'] as $gwId => $gwLabel):
  $gw = $gateways[$gwId];
  $needsCreds = !empty($secretFields[$gwId]) || $gwId !== 'school_po';
  $configured = is_gateway_configured($gwId, $gw['config']);
?>
<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="action" value="save_gateway"><input type="hidden" name="gateway" value="<?= $gwId ?>">
  <div class="flex justify-between items-center">
    <h2 class="text-lg flex items-center gap-2"><?= icon('credit-card') ?> <?= e($gwLabel) ?></h2>
    <label class="checkbox-field"><input type="checkbox" name="isEnabled" value="1" <?= $gw['isEnabled'] ? 'checked' : '' ?>> Enabled at checkout</label>
  </div>
  <p class="text-xs mt-1" style="color:<?= $configured ? 'var(--emerald-600)' : 'var(--slate-400)' ?>;font-weight:700;">
    <?= $gwId === 'school_po' ? 'No external credentials required' : ($configured ? 'Credentials on file' : 'Not configured yet') ?>
  </p>
  <div class="grid grid-2 mt-3">
    <?php foreach ($gw['config'] as $key => $value): ?>
      <div class="field">
        <label><?= e($fieldLabels[$key] ?? $key) ?></label>
        <input type="text" name="cfg_<?= e($key) ?>" value="<?= e(in_array($key, $secretFields[$gwId], true) && $value !== '' ? mask_secret($value) : $value) ?>">
      </div>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary mt-2">Save <?= e($gwLabel) ?></button>
</form>
<?php endforeach; ?>

<a href="https://dashboard.stripe.com/apikeys" target="_blank" class="link text-xs mt-3" style="display:inline-flex;align-items:center;gap:.3rem;">Get Stripe API keys <?= icon('external-link') ?></a>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
