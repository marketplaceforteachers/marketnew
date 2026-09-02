<?php
// Expects bootstrap.php already required. Optional $page_title before include.
$settings = get_all_settings();
$branding = $settings['branding'];
$headerSettings = $settings['header'];
$seasonal = $settings['seasonal_hub'];
$me = current_user();
$flashes = get_flashes();
$pageTitle = isset($page_title) ? $page_title . ' — ' . $branding['siteName'] : $branding['siteName'];
$pageDescription = $page_description ?? $branding['tagline'];
$pageUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($pageUrl) ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:url" content="<?= e($pageUrl) ?>">
<meta property="og:site_name" content="<?= e($branding['siteName']) ?>">
<?php if (!empty($page_image)): ?><meta property="og:image" content="<?= e($page_image) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
<style>:root{--accent:<?= e($branding['accentColor']) ?>;}</style>
</head>
<body>
<header class="site-header">
  <div class="bar">
    <?= render_logo($branding, 'dark') ?>

    <form class="search-form" action="/browse.php" method="get">
      <span style="padding-left:.6rem;color:#94a3b8;"><?= icon('search') ?></span>
      <input type="text" name="q" placeholder="Search classroom supplies..." value="<?= old('q', $_GET['q'] ?? '') ?>">
    </form>

    <a href="/post-listing.php" class="btn btn-red"><?= icon('plus-circle') ?> Post Listing</a>

    <div class="flex items-center gap-2" style="position:relative;">
      <a href="<?= $me ? '/messages.php' : '/login.php' ?>" class="icon-btn"><?= icon('bell') ?></a>
      <a href="/wishlists.php" class="icon-btn"><?= icon('heart') ?></a>
      <a href="/cart.php" class="icon-btn">
        <?= icon('shopping-bag') ?>
        <span class="icon-badge" id="cart-badge" style="display:none;">0</span>
      </a>

      <?php if ($me): ?>
        <div class="account-menu">
          <button type="button" class="avatar-btn" onclick="document.getElementById('account-dropdown').classList.toggle('hidden')">
            <?= e(strtoupper(substr($me['name'], 0, 2))) ?>
          </button>
          <div class="account-dropdown hidden" id="account-dropdown">
            <p class="text-xs text-muted" style="padding:.4rem .9rem;"><?= e($me['email']) ?></p>
            <a href="/orders.php"><?= icon('package') ?> Your Orders</a>
            <a href="/messages.php"><?= icon('message') ?> Messages</a>
            <a href="/account.php"><?= icon('lock') ?> My Account</a>
            <?php if ($me['role'] === 'teacher'): ?>
              <a href="/seller-dashboard.php"><?= icon('layout-grid') ?> Seller Dashboard</a>
              <a href="/seller-verification.php"><?= icon('badge-check') ?> Educator Verification</a>
            <?php endif; ?>
            <?php if ($me['role'] === 'admin'): ?>
              <a href="/admin/index.php"><?= icon('layout-grid') ?> Admin Console</a>
            <?php endif; ?>
            <form action="/logout.php" method="post">
              <?= csrf_field() ?>
              <button type="submit" style="color:var(--red-600);"><?= icon('log-out') ?> Sign Out</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a href="/login.php" class="btn btn-ghost-light">Log In</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<nav class="primary-nav">
  <div class="bar">
    <?php foreach ($headerSettings['primaryNav'] as $navItem): ?>
      <a href="<?= e($navItem['href']) ?>"><?= e($navItem['label']) ?></a>
    <?php endforeach; ?>
    <?php if (!empty($seasonal['items'])): ?>
      <span class="primary-nav-sep"></span>
      <?php foreach ($seasonal['items'] as $item): if (empty($item['enabled'])) continue; ?>
        <a href="/browse.php?hub=<?= e($item['key']) ?>" class="hub-pill <?= ($_GET['hub'] ?? 'all') === $item['key'] ? 'active' : '' ?>"><?= e($item['label']) ?></a>
      <?php endforeach; ?>
      <?php if (!empty($seasonal['freeSurplusBannerEnabled'])): ?>
        <span class="hub-banner"><?= icon('gift') ?> <?= e($seasonal['freeSurplusBannerText']) ?></span>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</nav>

<main>
<?php foreach ($flashes as $f): ?>
  <div class="container mt-4">
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  </div>
<?php endforeach; ?>
