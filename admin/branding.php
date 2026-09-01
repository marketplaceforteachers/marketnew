<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

function zip_post_rows(array $fieldNames): array
{
    $arrays = [];
    $count = 0;
    foreach ($fieldNames as $key => $postName) {
        $arrays[$key] = $_POST[$postName] ?? [];
        $count = max($count, count($arrays[$key]));
    }
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $row = [];
        $allBlank = true;
        foreach ($arrays as $key => $arr) {
            $val = trim((string) ($arr[$i] ?? ''));
            $row[$key] = $val;
            if ($val !== '') $allBlank = false;
        }
        if (!$allBlank) $rows[] = $row;
    }
    return $rows;
}

$ICON_KEYS = ['book', 'search', 'map-pin', 'plus-circle', 'bell', 'heart', 'shopping-bag', 'star', 'shield', 'truck', 'piggy', 'zap', 'gift', 'sparkles', 'message', 'check', 'badge-check', 'gavel', 'layout-grid', 'wallet', 'alert-triangle', 'school', 'user-group', 'lock', 'phone', 'mail', 'share', 'arrow-right'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $group = post('group');
    if ($group === 'branding') {
        set_setting('branding', [
            'siteName' => trim(post('siteName')),
            'tagline' => trim(post('tagline')),
            'accentColor' => trim(post('accentColor')),
            'logoUrl' => trim(post('logoUrl')) ?: null,
        ]);
    } elseif ($group === 'header') {
        set_setting('header', [
            'primaryNav' => zip_post_rows(['label' => 'nav_label', 'href' => 'nav_href']),
        ]);
    } elseif ($group === 'homepage_hero') {
        $current = get_setting('homepage');
        foreach (['badgeText', 'heroHeadline', 'heroSubtext'] as $f) {
            if (isset($_POST[$f])) $current[$f] = trim(post($f));
        }
        $current['heroSlides'] = zip_post_rows(['imageUrl' => 'hs_url', 'alt' => 'hs_alt']);
        set_setting('homepage', $current);
    } elseif ($group === 'homepage_trust') {
        $current = get_setting('homepage');
        foreach (['trustHeadline', 'trustBadgeState', 'trustDescription', 'trustDisputeRate', 'trustDistricts'] as $f) {
            if (isset($_POST[$f])) $current[$f] = trim(post($f));
        }
        foreach (['trustRating', 'trustReviewCount', 'trustSatisfactionPct'] as $f) {
            if (isset($_POST[$f])) $current[$f] = (float) post($f);
        }
        $current['trustBadges'] = zip_post_rows(['icon' => 'tb_icon', 'tone' => 'tb_tone', 'label' => 'tb_label']);
        set_setting('homepage', $current);
    } elseif ($group === 'footer') {
        set_setting('footer', [
            'description' => trim(post('description')),
            'address' => trim(post('address')),
            'phone' => trim(post('phone')),
            'supportEmail' => trim(post('supportEmail')),
            'features' => zip_post_rows(['icon' => 'ff_icon', 'color' => 'ff_color', 'title' => 'ff_title', 'desc' => 'ff_desc']),
            'trustLinks' => zip_post_rows(['label' => 'tl_label', 'href' => 'tl_href']),
            'socialLinks' => zip_post_rows(['label' => 'sl_label', 'href' => 'sl_href']),
        ]);
    }
    log_admin_action($me['id'], 'settings.update', 'site_settings', $group);
    flash('success', 'Saved.');
    redirect('/admin/branding.php');
}

$branding = get_setting('branding');
$header = get_setting('header');
$homepage = get_setting('homepage');
$footer = get_setting('footer');
$page_title = 'Branding & Homepage';
require __DIR__ . '/../includes/admin_layout_header.php';

function icon_datalist(string $id, array $keys): string
{
    $html = "<datalist id=\"$id\">";
    foreach ($keys as $k) $html .= '<option value="' . e($k) . '">';
    return $html . '</datalist>';
}
?>
<h1 class="text-xl">Branding &amp; Homepage Content</h1>
<p class="text-sm text-muted mt-1">Everything here is live on the public site immediately after saving. Icon fields accept any key from the icon library (start typing to see suggestions).</p>
<?= icon_datalist('icon-keys', $ICON_KEYS) ?>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="group" value="branding">
  <h2 class="text-lg">Brand identity</h2>
  <div class="grid grid-2 mt-2">
    <div class="field"><label>Site Name</label><input type="text" name="siteName" value="<?= e($branding['siteName']) ?>"></div>
    <div class="field"><label>Tagline</label><input type="text" name="tagline" value="<?= e($branding['tagline']) ?>"></div>
    <div class="field"><label>Accent Color</label><input type="color" name="accentColor" value="<?= e($branding['accentColor']) ?>"></div>
    <div class="field"><label>Logo Image URL (optional)</label><input type="text" name="logoUrl" value="<?= e($branding['logoUrl'] ?? '') ?>"></div>
  </div>
  <button class="btn btn-primary mt-2">Save</button>
</form>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="group" value="header">
  <h2 class="text-lg">Main navigation menu</h2>
  <p class="text-sm text-muted mt-1">The links shown in the dark bar under the header, before the seasonal pills.</p>
  <div id="nav-rows-list" class="stack mt-2">
    <?php foreach ($header['primaryNav'] as $item): ?>
      <div class="dynamic-row flex gap-2 mt-2">
        <input type="text" name="nav_label[]" placeholder="Label" value="<?= e($item['label']) ?>" style="flex:1;">
        <input type="text" name="nav_href[]" placeholder="/page.php" value="<?= e($item['href']) ?>" style="flex:1;">
        <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
      </div>
    <?php endforeach; ?>
  </div>
  <template id="nav-rows">
    <div class="dynamic-row flex gap-2 mt-2">
      <input type="text" name="nav_label[]" placeholder="Label" style="flex:1;">
      <input type="text" name="nav_href[]" placeholder="/page.php" style="flex:1;">
      <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
    </div>
  </template>
  <button type="button" class="link text-xs mt-2" data-add-row="nav-rows"><?= icon('plus') ?> Add menu link</button>
  <div><button class="btn btn-primary mt-3">Save Menu</button></div>
</form>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="group" value="homepage_hero">
  <h2 class="text-lg">Hero section</h2>
  <div class="field mt-2"><label>Badge Text</label><input type="text" name="badgeText" value="<?= e($homepage['badgeText']) ?>"></div>
  <div class="field"><label>Hero Headline</label><input type="text" name="heroHeadline" value="<?= e($homepage['heroHeadline']) ?>"></div>
  <div class="field"><label>Hero Subtext</label><textarea name="heroSubtext" rows="3"><?= e($homepage['heroSubtext']) ?></textarea></div>

  <h3 class="text-lg mt-4">Background slideshow</h3>
  <p class="text-sm text-muted mt-1">Real photos that crossfade behind the hero text. Use image URLs from your own hosting or a licensed photo library — the defaults are generic placeholder stock photos and should be replaced before launch.</p>
  <div id="hs-rows-list" class="stack mt-2">
    <?php foreach ($homepage['heroSlides'] as $slide): ?>
      <div class="dynamic-row flex gap-2 mt-2">
        <input type="text" name="hs_url[]" placeholder="https://.../photo.jpg" value="<?= e($slide['imageUrl']) ?>" style="flex:2;">
        <input type="text" name="hs_alt[]" placeholder="Alt text" value="<?= e($slide['alt']) ?>" style="flex:1;">
        <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
      </div>
    <?php endforeach; ?>
  </div>
  <template id="hs-rows">
    <div class="dynamic-row flex gap-2 mt-2">
      <input type="text" name="hs_url[]" placeholder="https://.../photo.jpg" style="flex:2;">
      <input type="text" name="hs_alt[]" placeholder="Alt text" style="flex:1;">
      <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
    </div>
  </template>
  <button type="button" class="link text-xs mt-2" data-add-row="hs-rows"><?= icon('plus') ?> Add slide</button>

  <div><button class="btn btn-primary mt-3">Save Hero</button></div>
</form>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="group" value="homepage_trust">
  <h2 class="text-lg">Trust bar</h2>
  <div class="field mt-2"><label>Trust Headline</label><input type="text" name="trustHeadline" value="<?= e($homepage['trustHeadline']) ?>"></div>
  <div class="grid grid-2">
    <div class="field"><label>Badge / State Line</label><input type="text" name="trustBadgeState" value="<?= e($homepage['trustBadgeState']) ?>"></div>
    <div class="field"><label>Rating (e.g. 4.9)</label><input type="number" step="0.1" name="trustRating" value="<?= e((string) $homepage['trustRating']) ?>"></div>
    <div class="field"><label>Review Count</label><input type="number" name="trustReviewCount" value="<?= e((string) $homepage['trustReviewCount']) ?>"></div>
    <div class="field"><label>Satisfaction %</label><input type="number" step="0.1" name="trustSatisfactionPct" value="<?= e((string) $homepage['trustSatisfactionPct']) ?>"></div>
    <div class="field"><label>Dispute Rate Text</label><input type="text" name="trustDisputeRate" value="<?= e($homepage['trustDisputeRate']) ?>"></div>
    <div class="field"><label>Districts Text</label><input type="text" name="trustDistricts" value="<?= e($homepage['trustDistricts']) ?>"></div>
  </div>
  <div class="field"><label>Trust Description</label><textarea name="trustDescription" rows="3"><?= e($homepage['trustDescription']) ?></textarea></div>

  <h3 class="text-lg mt-4">Trust badge chips</h3>
  <div id="tb-rows-list" class="stack mt-2">
    <?php foreach ($homepage['trustBadges'] as $tb): ?>
      <div class="dynamic-row flex gap-2 mt-2">
        <input type="text" name="tb_icon[]" list="icon-keys" placeholder="icon" value="<?= e($tb['icon']) ?>" style="width:9rem;">
        <select name="tb_tone[]" style="width:8rem;">
          <?php foreach (['amber', 'emerald', 'royal'] as $tone): ?>
            <option value="<?= $tone ?>" <?= $tb['tone'] === $tone ? 'selected' : '' ?>><?= ucfirst($tone) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="tb_label[]" placeholder="Label" value="<?= e($tb['label']) ?>" style="flex:1;">
        <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
      </div>
    <?php endforeach; ?>
  </div>
  <template id="tb-rows">
    <div class="dynamic-row flex gap-2 mt-2">
      <input type="text" name="tb_icon[]" list="icon-keys" placeholder="icon" style="width:9rem;">
      <select name="tb_tone[]" style="width:8rem;">
        <option value="amber">Amber</option><option value="emerald">Emerald</option><option value="royal">Royal</option>
      </select>
      <input type="text" name="tb_label[]" placeholder="Label" style="flex:1;">
      <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
    </div>
  </template>
  <button type="button" class="link text-xs mt-2" data-add-row="tb-rows"><?= icon('plus') ?> Add badge chip</button>
  <div><button class="btn btn-primary mt-3">Save Trust Bar</button></div>
</form>

<form method="post" class="card card-pad mt-4">
  <?= csrf_field() ?><input type="hidden" name="group" value="footer">
  <h2 class="text-lg">Footer</h2>
  <div class="field mt-2"><label>Description</label><textarea name="description" rows="2"><?= e($footer['description'] ?? '') ?></textarea></div>
  <div class="grid grid-2">
    <div class="field"><label>Address</label><input type="text" name="address" value="<?= e($footer['address']) ?>"></div>
    <div class="field"><label>Phone</label><input type="text" name="phone" value="<?= e($footer['phone'] ?? '') ?>"></div>
    <div class="field"><label>Support Email</label><input type="email" name="supportEmail" value="<?= e($footer['supportEmail']) ?>"></div>
  </div>

  <h3 class="text-lg mt-4">Feature strip (top of footer)</h3>
  <div id="ff-rows-list" class="stack mt-2">
    <?php foreach ($footer['features'] as $f): ?>
      <div class="dynamic-row grid mt-2" style="grid-template-columns:8rem 8rem 1fr 1fr auto;gap:.5rem;align-items:start;">
        <input type="text" name="ff_icon[]" list="icon-keys" placeholder="icon" value="<?= e($f['icon']) ?>">
        <input type="text" name="ff_color[]" placeholder="var(--royal-600)" value="<?= e($f['color']) ?>">
        <input type="text" name="ff_title[]" placeholder="Title" value="<?= e($f['title']) ?>">
        <input type="text" name="ff_desc[]" placeholder="Description" value="<?= e($f['desc']) ?>">
        <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
      </div>
    <?php endforeach; ?>
  </div>
  <template id="ff-rows">
    <div class="dynamic-row grid mt-2" style="grid-template-columns:8rem 8rem 1fr 1fr auto;gap:.5rem;align-items:start;">
      <input type="text" name="ff_icon[]" list="icon-keys" placeholder="icon">
      <input type="text" name="ff_color[]" placeholder="var(--royal-600)">
      <input type="text" name="ff_title[]" placeholder="Title">
      <input type="text" name="ff_desc[]" placeholder="Description">
      <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
    </div>
  </template>
  <button type="button" class="link text-xs mt-2" data-add-row="ff-rows"><?= icon('plus') ?> Add feature</button>

  <h3 class="text-lg mt-4">Trust &amp; Educator Center links</h3>
  <div id="tl-rows-list" class="stack mt-2">
    <?php foreach ($footer['trustLinks'] as $l): ?>
      <div class="dynamic-row flex gap-2 mt-2">
        <input type="text" name="tl_label[]" placeholder="Label" value="<?= e($l['label']) ?>" style="flex:1;">
        <input type="text" name="tl_href[]" placeholder="/page.php" value="<?= e($l['href']) ?>" style="flex:1;">
        <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
      </div>
    <?php endforeach; ?>
  </div>
  <template id="tl-rows">
    <div class="dynamic-row flex gap-2 mt-2">
      <input type="text" name="tl_label[]" placeholder="Label" style="flex:1;">
      <input type="text" name="tl_href[]" placeholder="/page.php" style="flex:1;">
      <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
    </div>
  </template>
  <button type="button" class="link text-xs mt-2" data-add-row="tl-rows"><?= icon('plus') ?> Add link</button>

  <h3 class="text-lg mt-4">Social links</h3>
  <div id="sl-rows-list" class="stack mt-2">
    <?php foreach ($footer['socialLinks'] as $s): ?>
      <div class="dynamic-row flex gap-2 mt-2">
        <input type="text" name="sl_label[]" placeholder="Facebook" value="<?= e($s['label']) ?>" style="flex:1;">
        <input type="text" name="sl_href[]" placeholder="https://..." value="<?= e($s['href']) ?>" style="flex:1;">
        <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
      </div>
    <?php endforeach; ?>
  </div>
  <template id="sl-rows">
    <div class="dynamic-row flex gap-2 mt-2">
      <input type="text" name="sl_label[]" placeholder="Facebook" style="flex:1;">
      <input type="text" name="sl_href[]" placeholder="https://..." style="flex:1;">
      <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
    </div>
  </template>
  <button type="button" class="link text-xs mt-2" data-add-row="sl-rows"><?= icon('plus') ?> Add social link</button>

  <div><button class="btn btn-primary mt-3">Save Footer</button></div>
</form>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
