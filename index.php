<?php
require_once __DIR__ . '/includes/bootstrap.php';

$homepage = get_setting('homepage');
$page_title = 'Home';
$page_description = $homepage['heroSubtext'];
require __DIR__ . '/includes/layout_header.php';

$categories = db()->query(
    'SELECT c.id, c.name, c.slug, c.icon, COUNT(l.id) AS listing_count
     FROM categories c
     LEFT JOIN listings l ON l.category_id = c.id AND l.is_active = 1
     GROUP BY c.id, c.name, c.slug, c.icon
     ORDER BY c.name'
)->fetchAll();
$listings = db()->query(
    "SELECT l.*, u.name AS seller_name, u.is_verified AS seller_verified, c.name AS category_name,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) AS primary_image
     FROM listings l JOIN users u ON u.id = l.seller_id JOIN categories c ON c.id = l.category_id
     WHERE l.is_active = 1 ORDER BY l.created_at DESC LIMIT 8"
)->fetchAll();
?>

<section class="hero hero-centered" id="hero-slideshow" data-interval="5500">
  <div class="hero-slides">
    <?php foreach ($homepage['heroSlides'] as $i => $slide): ?>
      <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>" style="background-image:url('<?= e($slide['imageUrl']) ?>');" role="img" aria-label="<?= e($slide['alt']) ?>"></div>
    <?php endforeach; ?>
    <div class="hero-slide-overlay"></div>
  </div>
  <div class="container">
    <div class="hero-inner">
      <span class="pill-badge"><span style="width:6px;height:6px;border-radius:999px;background:#f87171;display:inline-block;"></span> <?= e($homepage['badgeText']) ?></span>
      <h1><?= e($homepage['heroHeadline']) ?></h1>
      <p class="lead"><?= e($homepage['heroSubtext']) ?></p>
      <div class="flex gap-3 mt-5" style="flex-wrap:wrap;">
        <a href="/browse.php" class="btn btn-hero-primary"><?= icon('chevron-right') ?> Browse Classroom Listings</a>
        <a href="/post-listing.php" class="btn btn-ghost-light"><?= icon('plus-circle') ?> Post Free Teacher Listing</a>
      </div>
      <div class="flex gap-5 mt-7" style="flex-wrap:wrap;justify-content:center;color:#dbeafe;font-size:.875rem;">
        <span class="flex items-center gap-2"><?= icon('shield') ?> 100% Verified Teachers</span>
        <span class="flex items-center gap-2"><?= icon('truck') ?> Local &amp; Media Mail Ship</span>
        <span class="flex items-center gap-2"><?= icon('piggy') ?> Save 50–80% vs Retail</span>
      </div>
      <?php if (count($homepage['heroSlides']) > 1): ?>
        <div class="hero-dots" role="tablist" aria-label="Hero image slides">
          <?php foreach ($homepage['heroSlides'] as $i => $slide): ?>
            <button type="button" class="hero-dot <?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>" aria-label="Show slide <?= $i + 1 ?>"></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="trust-bar">
  <div class="container grid">
    <div>
      <div class="flex gap-2" style="flex-wrap:wrap;">
        <?php foreach ($homepage['trustBadges'] as $tb): ?>
          <span class="badge-chip badge-<?= e($tb['tone']) ?>"><?= icon($tb['icon']) ?> <?= e($tb['label']) ?></span>
        <?php endforeach; ?>
      </div>
      <h2 class="mt-4 text-2xl" style="color:#fff;"><?= e($homepage['trustHeadline']) ?></h2>
      <span class="chip" style="background:var(--red-600);color:#fff;margin-top:.6rem;display:inline-block;"><?= e($homepage['trustBadgeState']) ?></span>
      <p class="text-sm mt-3" style="color:var(--slate-300);max-width:34rem;"><?= e($homepage['trustDescription']) ?></p>
    </div>
    <div class="trust-score-card">
      <div class="flex justify-between items-center">
        <span class="text-xs font-bold text-muted" style="text-transform:uppercase;">Educator Trust Score</span>
        <span class="chip" style="background:var(--amber-500);color:var(--slate-900);"><?= e($homepage['trustRating']) ?> ★</span>
      </div>
      <dl class="mt-3 text-sm" style="color:#fff;">
        <div class="flex justify-between mt-2"><dt style="color:var(--slate-400);">Verified Educator Satisfaction:</dt><dd class="font-bold" style="color:var(--emerald-500);"><?= e($homepage['trustSatisfactionPct']) ?>% Positive</dd></div>
        <div class="flex justify-between mt-2"><dt style="color:var(--slate-400);">Escrow Dispute Rate:</dt><dd class="font-bold" style="color:var(--emerald-500);"><?= e($homepage['trustDisputeRate']) ?></dd></div>
        <div class="flex justify-between mt-2"><dt style="color:var(--slate-400);">School Districts Enrolled:</dt><dd class="font-bold" style="color:var(--amber-400);"><?= e($homepage['trustDistricts']) ?></dd></div>
      </dl>
      <a href="/browse.php" class="btn btn-amber w-full mt-4" style="justify-content:center;"><?= icon('check') ?> Read Verified Teacher Reviews (<?= e($homepage['trustReviewCount']) ?>)</a>
    </div>
  </div>
</section>

<?php if ($categories): ?>
<?php
$categoryLimit = 10;
$shownCategories = array_slice($categories, 0, $categoryLimit);
$moreCategoriesCount = max(0, count($categories) - $categoryLimit);
?>
<section class="section-band py-8">
  <div class="container">
    <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:.5rem;">
      <div>
        <span class="section-eyebrow">Browse by subject</span>
        <h2 class="text-xl mt-1">Shop by Category</h2>
      </div>
      <?php if ($moreCategoriesCount > 0): ?>
        <a href="/browse.php" class="link">View all categories &rarr;</a>
      <?php endif; ?>
    </div>
    <div class="category-grid mt-4">
      <?php foreach ($shownCategories as $c): ?>
        <a href="/browse.php?category=<?= e($c['slug']) ?>" class="category-card cat-<?= category_accent($c['slug']) ?>">
          <span class="category-card-icon"><?= icon(category_icon($c['icon'])) ?></span>
          <span class="category-card-name"><?= e($c['name']) ?></span>
          <span class="category-card-count"><?= (int) $c['listing_count'] ?> listing<?= (int) $c['listing_count'] === 1 ? '' : 's' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section-tint py-8">
  <div class="container">
    <div class="flex justify-between items-center">
      <div>
        <span class="section-eyebrow">Fresh finds</span>
        <h2 class="text-xl mt-1">Featured Classroom Listings</h2>
      </div>
      <a href="/browse.php" class="link">View all &rarr;</a>
    </div>
    <?php if (!$listings): ?>
      <p class="text-sm text-muted mt-4">No listings yet — be the first to post one.</p>
    <?php endif; ?>
    <div class="listing-grid mt-4">
      <?php foreach ($listings as $l): include __DIR__ . '/includes/partials/listing_card.php'; endforeach; ?>
    </div>
  </div>
</section>

<section class="py-8 hidden" id="recently-viewed">
  <div class="container">
    <span class="section-eyebrow">Pick up where you left off</span>
    <h2 class="text-xl mt-1">Recently Viewed</h2>
    <div class="grid grid-4 mt-4" id="recently-viewed-list"></div>
  </div>
</section>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
