<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = param('slug');
$stmt = db()->prepare(
    "SELECT l.*, u.name AS seller_name, u.is_verified AS seller_verified, u.id AS seller_id, c.name AS category_name
     FROM listings l JOIN users u ON u.id = l.seller_id JOIN categories c ON c.id = l.category_id
     WHERE l.slug = ? LIMIT 1"
);
$stmt->execute([$slug]);
$listing = $stmt->fetch();

if (!$listing) {
    http_response_code(404);
    $page_title = 'Not Found';
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="container py-10 text-center"><p>Listing not found.</p></div>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

$stmt = db()->prepare('SELECT image_url FROM listing_images WHERE listing_id = ? ORDER BY is_primary DESC');
$stmt->execute([$listing['id']]);
$images = array_column($stmt->fetchAll(), 'image_url');

// Don't let a seller's own visits inflate their listing's view count.
if (!current_user() || (int) current_user()['id'] !== (int) $listing['seller_id']) {
    db()->prepare('UPDATE listings SET view_count = view_count + 1 WHERE id = ?')->execute([$listing['id']]);
}

$relatedStmt = db()->prepare(
    "SELECT l.*, u.name AS seller_name, u.is_verified AS seller_verified, c.name AS category_name,
            (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) AS primary_image
     FROM listings l JOIN users u ON u.id = l.seller_id JOIN categories c ON c.id = l.category_id
     WHERE l.category_id = ? AND l.id != ? AND l.is_active = 1 ORDER BY l.created_at DESC LIMIT 4"
);
$relatedStmt->execute([$listing['category_id'], $listing['id']]);
$related = $relatedStmt->fetchAll();

$stmt = db()->prepare(
    "SELECT r.*, u.name AS reviewer_name FROM reviews r JOIN users u ON u.id = r.reviewer_id WHERE r.seller_id = ? ORDER BY r.created_at DESC"
);
$stmt->execute([$listing['seller_id']]);
$reviews = $stmt->fetchAll();

// Handle "message seller" / "make an offer" form.
$me = current_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact_seller') {
    verify_csrf();
    if (!$me) {
        redirect('/login.php?redirect=' . urlencode('/listing.php?slug=' . $slug));
    }
    $offerAmount = trim($_POST['offer_amount'] ?? '');

    $stmt = db()->prepare(
        'SELECT id FROM message_threads WHERE buyer_id = ? AND seller_id = ? AND (listing_id = ? OR (listing_id IS NULL AND ? IS NULL))'
    );
    $stmt->execute([$me['id'], $listing['seller_id'], $listing['id'], $listing['id']]);
    $thread = $stmt->fetch();
    if (!$thread) {
        db()->prepare('INSERT INTO message_threads (listing_id, buyer_id, seller_id) VALUES (?, ?, ?)')
            ->execute([$listing['id'], $me['id'], $listing['seller_id']]);
        $threadId = (int) db()->lastInsertId();
    } else {
        $threadId = (int) $thread['id'];
    }

    $body = $offerAmount !== ''
        ? "I'd like to offer $" . e($offerAmount) . " for \"{$listing['title']}\"."
        : "Hi, I'm interested in \"{$listing['title']}\".";

    db()->prepare('INSERT INTO messages (thread_id, sender_id, recipient_id, listing_id, body) VALUES (?, ?, ?, ?, ?)')
        ->execute([$threadId, $me['id'], $listing['seller_id'], $listing['id'], $body]);

    flash('success', 'Message sent to the seller.');
    redirect('/messages.php?thread=' . $threadId);
}

$conditionLabels = ['new' => 'New', 'like_new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair', 'digital_download' => 'Digital Download'];
$page_title = $listing['title'];
$page_description = truncate($listing['description'], 160);
$page_image = $images[0] ?? null;
require __DIR__ . '/includes/layout_header.php';

$cartPayload = json_encode([
    'id' => (int) $listing['id'], 'title' => $listing['title'], 'slug' => $listing['slug'],
    'price' => (float) $listing['price'], 'shippingFee' => (float) $listing['shipping_fee'],
    'categoryName' => $listing['category_name'], 'gradeLevel' => $listing['grade_level'],
    'primaryImageUrl' => $images[0] ?? null,
]);
?>
<div class="container py-8">
  <div class="grid grid-2">
    <div>
      <div class="listing-img" style="aspect-ratio:4/3;border-radius:.8rem;">
        <?php if ($images): ?><img src="<?= e($images[0]) ?>" alt="<?= e($listing['title']) ?>"><?php endif; ?>
      </div>
    </div>
    <div>
      <div class="flex gap-1" style="flex-wrap:wrap;">
        <span class="chip chip-royal"><?= e($listing['category_name']) ?></span>
        <span class="chip chip-slate"><?= e($listing['grade_level']) ?></span>
        <span class="chip chip-slate"><?= e($conditionLabels[$listing['condition_type']] ?? '') ?></span>
      </div>
      <h1 class="text-2xl mt-3"><?= e($listing['title']) ?></h1>
      <div class="flex items-center gap-2 mt-2 text-sm text-muted">
        <?php if ($listing['seller_verified']): ?><span class="flex items-center gap-1" style="color:var(--emerald-600);font-weight:700;"><?= icon('shield') ?> Verified Teacher</span><?php endif; ?>
        <span>&middot; <a href="/seller.php?id=<?= (int) $listing['seller_id'] ?>" class="link">Sold by <?= e($listing['seller_name']) ?></a></span>
      </div>
      <p class="text-2xl font-extrabold mt-4"><?= $listing['price'] == 0 ? 'Free' : money((float) $listing['price']) ?></p>
      <p class="text-sm text-muted mt-2 flex items-center gap-2">
        <?= icon('truck') ?>
        <?php if ($listing['shipping_type'] === 'local_pickup'): ?>Local pickup only
        <?php elseif ($listing['shipping_type'] === 'both'): ?>Ships (+<?= money((float) $listing['shipping_fee']) ?>) or local pickup
        <?php else: ?>Ships — <?= money((float) $listing['shipping_fee']) ?><?php endif; ?>
      </p>
      <p class="text-sm mt-4" style="white-space:pre-line;"><?= e($listing['description']) ?></p>

      <div class="flex gap-3 mt-5" style="flex-wrap:wrap;">
        <button type="button" class="btn btn-primary" data-add-to-cart='<?= e($cartPayload) ?>'>Add to Cart</button>
        <?php if (!$me || $me['id'] != $listing['seller_id']): ?>
        <form method="post" style="display:inline;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="contact_seller">
          <button type="submit" class="btn btn-outline"><?= icon('message') ?> Message Seller</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if (!$me || $me['id'] != $listing['seller_id']): ?>
      <div class="card card-pad mt-4" style="border-style:dashed;">
        <p class="text-xs font-bold text-muted" style="text-transform:uppercase;">Make an Offer</p>
        <form method="post" class="flex gap-2 mt-2">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="contact_seller">
          <input type="number" name="offer_amount" placeholder="$" style="width:7rem;">
          <button type="submit" class="btn btn-amber">Send Offer</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="mt-8">
    <h2 class="text-lg">Seller Reviews</h2>
    <?php if (!$reviews): ?><p class="text-sm text-muted mt-2">No reviews yet.</p><?php endif; ?>
    <div class="stack mt-3">
      <?php foreach ($reviews as $r): ?>
        <div class="card card-pad">
          <div class="flex items-center gap-1">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <span style="color:<?= $i <= $r['rating'] ? 'var(--amber-400)' : 'var(--slate-200)' ?>;"><?= icon('star') ?></span>
            <?php endfor; ?>
            <span class="text-xs text-muted" style="margin-left:.5rem;"><?= e($r['reviewer_name']) ?></span>
          </div>
          <p class="text-sm mt-1"><?= e($r['comment']) ?></p>
          <?php if ($r['seller_reply']): ?><p class="text-xs text-muted mt-1"><em>Seller reply: <?= e($r['seller_reply']) ?></em></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($related): ?>
  <div class="mt-8">
    <h2 class="text-lg">Related Listings</h2>
    <div class="grid grid-4 mt-4">
      <?php foreach ($related as $l): include __DIR__ . '/includes/partials/listing_card.php'; endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    trackRecentlyViewed({
      id: <?= (int) $listing['id'] ?>,
      title: <?= json_encode($listing['title']) ?>,
      slug: <?= json_encode($listing['slug']) ?>,
      price: <?= (float) $listing['price'] ?>,
      image: <?= json_encode($images[0] ?? null) ?>
    });
  });
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
