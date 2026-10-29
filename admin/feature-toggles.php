<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

/**
 * Admin -> Show & Hide: one page with an on/off switch for every part of the public site.
 * Section switches live in the 'visibility' setting; switches for individual list items (a menu
 * link, a footer badge...) set the 'enabled' flag on that item inside its own setting, the same
 * flag Branding & Homepage edits, so both pages always agree.
 */

$settings = get_all_settings();
$vis = $settings['visibility'];
$header = $settings['header'];
$homepage = $settings['homepage'];
$footer = $settings['footer'];
$hub = $settings['seasonal_hub'];

// prefix => [setting key, list key within that setting]
$itemLists = [
    'nav' => ['header', 'primaryNav'],
    'slides' => ['homepage', 'heroSlides'],
    'points' => ['homepage', 'heroPoints'],
    'steps' => ['homepage', 'steps'],
    'badges' => ['homepage', 'trustBadges'],
    'features' => ['footer', 'features'],
    'social' => ['footer', 'socialLinks'],
    'help' => ['footer', 'trustLinks'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys(default_settings()['visibility']) as $key) {
        if ($key === 'menuAlign') {
            $vis[$key] = post('menuAlign') === 'left' ? 'left' : 'center';
        } else {
            $vis[$key] = post("vis_$key") === '1';
        }
    }
    set_setting('visibility', $vis);

    $changed = [];
    foreach ($itemLists as $prefix => [$settingKey, $listKey]) {
        $current = $changed[$settingKey] ?? $settings[$settingKey];
        foreach ($current[$listKey] as $i => $item) {
            $current[$listKey][$i]['enabled'] = post("{$prefix}_$i") === '1';
        }
        $changed[$settingKey] = $current;
    }
    foreach ($changed as $settingKey => $value) {
        set_setting($settingKey, $value);
    }

    $hub['freeSurplusBannerEnabled'] = post('freeSurplusBannerEnabled') === '1';
    $hub['freeSurplusBannerText'] = trim(post('freeSurplusBannerText')) ?: $hub['freeSurplusBannerText'];
    foreach ($hub['items'] as $i => $item) {
        $hub['items'][$i]['label'] = trim(post("hub_label_$i", $item['label'])) ?: $item['label'];
        $hub['items'][$i]['enabled'] = post("hub_$i") === '1';
    }
    set_setting('seasonal_hub', $hub);

    log_admin_action($me['id'], 'settings.update', 'site_settings', 'visibility');
    flash('success', 'Saved. Your changes are live.');
    redirect('/admin/feature-toggles.php');
}

/** One labelled on/off switch. */
function toggle(string $name, bool $on, string $label, string $hint = ''): string
{
    return '<label class="switch-row"><span class="switch"><input type="checkbox" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '><span class="switch-track"></span></span>'
        . '<span><span class="switch-label">' . e($label) . '</span>' . ($hint !== '' ? '<small>' . e($hint) . '</small>' : '') . '</span></label>';
}

/** Switches for each item of an editable list, indented under its section switch. */
function item_toggles(string $prefix, array $items, string $labelField): string
{
    if (!$items) {
        return '';
    }
    $html = '<div class="switch-children">';
    foreach ($items as $i => $item) {
        $label = trim((string) ($item[$labelField] ?? '')) ?: 'Item ' . ($i + 1);
        $html .= toggle("{$prefix}_$i", shown($item), $label);
    }
    return $html . '</div>';
}

$page_title = 'Show & Hide';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<span class="section-eyebrow">Show &amp; Hide</span>
<h1 class="section-title mt-1">Turn any part of the site <em class="serif-accent">on or off</em></h1>
<p class="text-sm text-muted mt-1">Switch something off and it disappears from the site straight away. Nothing is deleted, so you can switch it back on any time. To change the words themselves, use <a class="link" href="/admin/branding.php">Branding &amp; Homepage</a>.</p>

<form method="post" class="mt-4">
  <?= csrf_field() ?>

  <div class="card card-pad">
    <h2 class="text-lg">Menu bar</h2>
    <p class="text-xs text-muted mt-1">The dark bar under the header.</p>
    <div class="switch-list mt-3">
      <?= toggle('vis_menuBar', visible('menuBar'), 'Show the menu bar') ?>
      <div class="switch-row" style="cursor:default;">
        <span class="switch-spacer"></span>
        <span>
          <span class="switch-label">Line up the menu</span>
          <span class="flex gap-3 mt-1">
            <label class="checkbox-field"><input type="radio" name="menuAlign" value="center" <?= ($vis['menuAlign'] ?? 'center') !== 'left' ? 'checked' : '' ?>> In the middle</label>
            <label class="checkbox-field"><input type="radio" name="menuAlign" value="left" <?= ($vis['menuAlign'] ?? 'center') === 'left' ? 'checked' : '' ?>> On the left</label>
          </span>
        </span>
      </div>
      <?= toggle('vis_menuLinks', visible('menuLinks'), 'Main menu links') ?>
      <?= item_toggles('nav', $header['primaryNav'], 'label') ?>
      <?= toggle('vis_seasonalPills', visible('seasonalPills'), 'Seasonal pills', 'Back to School, Winter Library and so on. You can rename them here.') ?>
      <div class="switch-children">
        <?php foreach ($hub['items'] as $i => $item): ?>
          <div class="switch-row">
            <label class="switch"><input type="checkbox" name="hub_<?= $i ?>" value="1" <?= !empty($item['enabled']) ? 'checked' : '' ?> aria-label="Show <?= e($item['label']) ?>"><span class="switch-track"></span></label>
            <input type="text" name="hub_label_<?= $i ?>" value="<?= e($item['label']) ?>" style="max-width:16rem;">
          </div>
        <?php endforeach; ?>
      </div>
      <div class="switch-row">
        <label class="switch"><input type="checkbox" name="freeSurplusBannerEnabled" value="1" <?= !empty($hub['freeSurplusBannerEnabled']) ? 'checked' : '' ?> aria-label="Show the free items link"><span class="switch-track"></span></label>
        <span style="flex:1;"><span class="switch-label">"Free stuff" link</span><input type="text" name="freeSurplusBannerText" value="<?= e($hub['freeSurplusBannerText']) ?>" class="mt-1" style="max-width:16rem;"></span>
      </div>
    </div>
  </div>

  <div class="card card-pad mt-4">
    <h2 class="text-lg">Header</h2>
    <div class="switch-list mt-3">
      <?= toggle('vis_headerSearch', visible('headerSearch'), 'Search box') ?>
      <?= toggle('vis_headerPostButton', visible('headerPostButton'), '"Post Listing" button') ?>
      <?= toggle('vis_headerNotifications', visible('headerNotifications'), 'Messages bell') ?>
      <?= toggle('vis_headerWishlist', visible('headerWishlist'), 'Wishlist heart') ?>
      <?= toggle('vis_headerCart', visible('headerCart'), 'Cart') ?>
    </div>
  </div>

  <div class="card card-pad mt-4">
    <h2 class="text-lg">Homepage</h2>
    <div class="switch-list mt-3">
      <?= toggle('vis_homeHero', visible('homeHero'), 'Big photo banner at the top') ?>
      <div class="switch-children">
        <?= toggle('vis_homeHeroBadge', visible('homeHeroBadge'), 'Small badge above the headline') ?>
        <?= toggle('vis_homeHeroButtons', visible('homeHeroButtons'), 'Buttons') ?>
        <?= toggle('vis_homeHeroPoints', visible('homeHeroPoints'), 'Points under the buttons') ?>
        <?= item_toggles('points', $homepage['heroPoints'], 'label') ?>
        <p class="switch-subhead">Background photos</p>
        <?= item_toggles('slides', $homepage['heroSlides'], 'alt') ?>
      </div>

      <?= toggle('vis_homeWhy', visible('homeWhy'), '"Why teachers use it" band') ?>
      <div class="switch-children">
        <?= toggle('vis_homeWhyBadges', visible('homeWhyBadges'), 'Badge chips') ?>
        <?= item_toggles('badges', $homepage['trustBadges'], 'label') ?>
        <?= toggle('vis_homeWhySteps', visible('homeWhySteps'), 'Steps panel') ?>
        <?= item_toggles('steps', $homepage['steps'], 'title') ?>
      </div>

      <?= toggle('vis_homeCategories', visible('homeCategories'), 'Shop by category') ?>
      <?= toggle('vis_homeListings', visible('homeListings'), 'Newest listings') ?>
      <?= toggle('vis_homeRecentlyViewed', visible('homeRecentlyViewed'), 'Recently viewed', "Only appears for visitors who've looked at listings before.") ?>
    </div>
  </div>

  <div class="card card-pad mt-4">
    <h2 class="text-lg">Footer</h2>
    <div class="switch-list mt-3">
      <?= toggle('vis_footerFeatures', visible('footerFeatures'), 'Feature strip (the icons at the top of the footer)') ?>
      <?= item_toggles('features', $footer['features'], 'title') ?>
      <?= toggle('vis_footerAbout', visible('footerAbout'), 'About text') ?>
      <?= toggle('vis_footerEmail', visible('footerEmail'), 'Support email') ?>
      <?= toggle('vis_footerSocial', visible('footerSocial'), 'Social media links') ?>
      <?= item_toggles('social', $footer['socialLinks'], 'label') ?>
      <?= toggle('vis_footerCategories', visible('footerCategories'), '"Shop by category" column') ?>
      <?= toggle('vis_footerHelp', visible('footerHelp'), '"Help" column') ?>
      <?= item_toggles('help', $footer['trustLinks'], 'label') ?>
      <?= toggle('vis_footerCopyright', visible('footerCopyright'), 'Copyright line') ?>
    </div>
  </div>

  <div class="save-bar">
    <button class="btn btn-primary btn-lg">Save changes</button>
    <a class="link text-sm" href="/" target="_blank" rel="noopener">View the site &nearr;</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
