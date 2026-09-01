<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$hub = get_setting('seasonal_hub');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $hub['freeSurplusBannerEnabled'] = post('freeSurplusBannerEnabled') === '1';
    $hub['freeSurplusBannerText'] = trim(post('freeSurplusBannerText'));
    $items = [];
    foreach ($hub['items'] as $i => $item) {
        $items[] = [
            'key' => $item['key'],
            'icon' => $item['icon'],
            'label' => trim(post("item_label_$i", $item['label'])),
            'enabled' => post("item_enabled_$i") === '1',
        ];
    }
    $hub['items'] = $items;
    set_setting('seasonal_hub', $hub);
    log_admin_action($me['id'], 'settings.update', 'site_settings', 'seasonal_hub');
    flash('success', 'Saved.');
    redirect('/admin/feature-toggles.php');
}

$page_title = 'Site Feature Toggles';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Site Feature Toggles</h1>
<p class="text-sm text-muted mt-1">Control the seasonal hub pill bar shown under the header.</p>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?>
  <label class="checkbox-field"><input type="checkbox" name="freeSurplusBannerEnabled" value="1" <?= $hub['freeSurplusBannerEnabled'] ? 'checked' : '' ?>> Show "Free Surplus" banner</label>
  <div class="field mt-2"><input type="text" name="freeSurplusBannerText" value="<?= e($hub['freeSurplusBannerText']) ?>"></div>

  <h3 class="text-lg mt-4">Seasonal pills</h3>
  <div class="stack mt-2">
    <?php foreach ($hub['items'] as $i => $item): ?>
      <div class="flex items-center gap-3" style="border:1px solid var(--slate-200);border-radius:.5rem;padding:.6rem;">
        <input type="checkbox" name="item_enabled_<?= $i ?>" value="1" <?= $item['enabled'] ? 'checked' : '' ?>>
        <input type="text" name="item_label_<?= $i ?>" value="<?= e($item['label']) ?>" style="flex:1;">
        <span class="text-xs text-muted"><?= e($item['key']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
  <button class="btn btn-primary mt-4">Save Changes</button>
</form>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
