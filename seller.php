<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int) param('id');
$stmt = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'teacher'");
$stmt->execute([$id]);
$seller = $stmt->fetch();

if (!$seller) {
    http_response_code(404);
    $page_title = 'Seller Not Found';
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="container py-10 text-center"><p>This seller could not be found.</p></div>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

$ratingStmt = db()->prepare('SELECT COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating FROM reviews WHERE seller_id = ?');
$ratingStmt->execute([$id]);
$ratingRow = $ratingStmt->fetch();

$listingsStmt = db()->prepare(
    "SELECT l.*, u.name AS seller_name, u.is_verified AS seller_verified, c.name AS category_name,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) AS primary_image
     FROM listings l JOIN users u ON u.id = l.seller_id JOIN categories c ON c.id = l.category_id
     WHERE l.seller_id = ? AND l.is_active = 1 ORDER BY l.created_at DESC LIMIT 60"
);
$listingsStmt->execute([$id]);
$listings = $listingsStmt->fetchAll();

$reviewsStmt = db()->prepare(
    "SELECT r.*, u.name AS reviewer_name FROM reviews r JOIN users u ON u.id = r.reviewer_id WHERE r.seller_id = ? ORDER BY r.created_at DESC LIMIT 10"
);
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll();

$page_title = $seller['name'] . "'s Store";
$page_description = trim($seller['bio'] ?? '') ?: ($seller['name'] . ' sells classroom supplies on ' . get_setting('branding')['siteName'] . '.');
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container py-8">
  <div class="card card-pad flex gap-4" style="align-items:flex-start;flex-wrap:wrap;">
    <span class="avatar-btn" style="width:4rem;height:4rem;font-size:1.5rem;border-radius:999px;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
      <?= e(strtoupper(substr($seller['name'], 0, 2))) ?>
    </span>
    <div style="flex:1;min-width:12rem;">
      <div class="flex items-center gap-2" style="flex-wrap:wrap;">
        <h1 class="text-xl"><?= e($seller['name']) ?></h1>
        <?php if ($seller['is_verified']): ?>
          <span class="flex items-center gap-1 text-xs" style="color:var(--emerald-600);font-weight:700;"><?= icon('shield') ?> Verified Teacher</span>
        <?php endif; ?>
      </div>
      <p class="text-sm text-muted mt-1">
        <?php if ($seller['school_name']): ?><?= e($seller['school_name']) ?><?php endif; ?>
        <?php if ($seller['district']): ?> &middot; <?= e($seller['district']) ?><?php endif; ?>
        <?php if ($seller['state']): ?> &middot; <?= e($seller['state']) ?><?php endif; ?>
      </p>
      <?php if ($ratingRow['cnt'] > 0): ?>
        <div class="flex items-center gap-1 mt-2 text-sm">
          <span style="color:var(--amber-500);"><?= icon('star') ?></span>
          <strong><?= number_format((float) $ratingRow['avg_rating'], 1) ?></strong>
          <span class="text-muted">(<?= (int) $ratingRow['cnt'] ?> review<?= (int) $ratingRow['cnt'] === 1 ? '' : 's' ?>)</span>
        </div>
      <?php endif; ?>
      <?php if (!empty($seller['bio'])): ?>
        <p class="text-sm mt-3"><?= e($seller['bio']) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-8">
    <h2 class="text-lg"><?= e($seller['name']) ?>'s Listings (<?= count($listings) ?>)</h2>
    <?php if (!$listings): ?>
      <p class="text-sm text-muted mt-3">This seller doesn't have any active listings right now.</p>
    <?php endif; ?>
    <div class="grid grid-4 mt-4">
      <?php foreach ($listings as $l): include __DIR__ . '/includes/partials/listing_card.php'; endforeach; ?>
    </div>
  </div>

  <?php if ($reviews): ?>
  <div class="mt-8">
    <h2 class="text-lg">Recent Reviews</h2>
    <div class="stack mt-3">
      <?php foreach ($reviews as $r): ?>
        <div class="card card-pad">
          <div class="flex items-center gap-1">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span style="color:<?= $i <= $r['rating'] ? 'var(--amber-400)' : 'var(--slate-200)' ?>;"><?= icon('star') ?></span>
            <?php endfor; ?>
            <span class="text-xs text-muted" style="margin-left:.5rem;"><?= e($r['reviewer_name']) ?></span>
          </div>
          <?php if ($r['comment']): ?><p class="text-sm mt-1"><?= e($r['comment']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
