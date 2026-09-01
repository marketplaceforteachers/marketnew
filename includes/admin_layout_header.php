<?php
// Expects bootstrap.php already required, and $me = require_admin() already called.
// Optional $page_title before include.
$branding = get_setting('branding');
$flashes = get_flashes();
$currentPage = basename($_SERVER['SCRIPT_NAME']);

$navItems = [
    'index.php' => ['icon' => 'layout-grid', 'label' => 'Executive Dashboard'],
    'risk.php' => ['icon' => 'alert-triangle', 'label' => 'Safety & Risk Monitor'],
    'listings.php' => ['icon' => 'package', 'label' => 'Listing Manager'],
    'orders.php' => ['icon' => 'file-bar', 'label' => 'Order Manager'],
    'categories.php' => ['icon' => 'layout-grid', 'label' => 'Category Manager'],
    'users.php' => ['icon' => 'user-group', 'label' => 'User Manager'],
    'verifications.php' => ['icon' => 'badge-check', 'label' => 'Teacher Verification'],
    'disputes.php' => ['icon' => 'gavel', 'label' => 'Dispute Arbitration'],
    'payment-gateways.php' => ['icon' => 'credit-card', 'label' => 'Payment Gateways'],
    'payouts.php' => ['icon' => 'wallet', 'label' => 'Seller Payouts'],
    'wishlists.php' => ['icon' => 'gift', 'label' => 'Wishlists & Fundraising'],
    'reviews.php' => ['icon' => 'star', 'label' => 'Reviews Moderation'],
    'messages.php' => ['icon' => 'message', 'label' => 'Messaging Oversight'],
    'email-templates.php' => ['icon' => 'message', 'label' => 'Email Template Studio'],
    'email-drips.php' => ['icon' => 'send', 'label' => 'Email Drip Campaigns'],
    'financial.php' => ['icon' => 'file-bar', 'label' => 'Financial & Tax Reporting'],
    'branding.php' => ['icon' => 'palette', 'label' => 'Branding & Homepage'],
    'feature-toggles.php' => ['icon' => 'toggle', 'label' => 'Site Feature Toggles'],
    'audit-log.php' => ['icon' => 'history', 'label' => 'Admin Audit Log'],
];
$pageTitle = isset($page_title) ? $page_title . ' — Admin' : 'Admin';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="stylesheet" href="/assets/css/style.css">
<style>:root{--accent:<?= e($branding['accentColor']) ?>;}</style>
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="logo-area">
      <?= render_logo($branding, 'dark') ?>
    </div>
    <nav class="admin-nav">
      <?php foreach ($navItems as $file => $item): ?>
        <a href="/admin/<?= $file ?>" class="<?= $currentPage === $file ? 'active' : '' ?>"><?= icon($item['icon']) ?> <?= e($item['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div style="padding:1rem;border-top:1px solid var(--slate-800);font-size:.75rem;color:var(--slate-400);">
      Signed in as <strong style="color:#fff;"><?= e($me['name']) ?></strong>
      <form action="/logout.php" method="post" class="mt-2"><?= csrf_field() ?><button class="link text-xs" style="background:none;border:none;color:#f87171;cursor:pointer;">Sign out</button></form>
    </div>
  </aside>
  <main class="admin-main">
    <?php foreach ($flashes as $f): ?>
      <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
