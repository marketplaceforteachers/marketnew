<?php
/** Expects $l — a listings row joined with seller_name, seller_verified, category_name, primary_image. */
$conditionLabels = ['new' => 'New', 'like_new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair', 'digital_download' => 'Digital Download'];
$cartPayload = json_encode([
    'id' => (int) $l['id'],
    'title' => $l['title'],
    'slug' => $l['slug'],
    'price' => (float) $l['price'],
    'shippingFee' => (float) $l['shipping_fee'],
    'categoryName' => $l['category_name'],
    'gradeLevel' => $l['grade_level'],
    'primaryImageUrl' => $l['primary_image'],
]);
?>
<div class="card listing-card">
  <a href="/listing.php?slug=<?= e($l['slug']) ?>" class="listing-img">
    <?php if ($l['primary_image']): ?>
      <img src="<?= e($l['primary_image']) ?>" alt="<?= e($l['title']) ?>">
    <?php else: ?>
      <span class="listing-img-placeholder"><?= icon('shield') ?></span>
    <?php endif; ?>
    <?php if ($l['seller_verified']): ?>
      <span class="chip chip-emerald"><?= icon('shield') ?> Verified Teacher</span>
    <?php endif; ?>
    <button type="button" class="wishlist-btn" onclick="event.preventDefault()" aria-label="Save to wishlist"><?= icon('heart') ?></button>
  </a>
  <div class="listing-body">
    <div class="flex gap-1" style="flex-wrap:wrap;">
      <span class="chip chip-royal"><?= e($l['category_name']) ?></span>
      <span class="chip chip-slate"><?= e($l['grade_level']) ?></span>
    </div>
    <a href="/listing.php?slug=<?= e($l['slug']) ?>" class="font-bold text-sm" style="color:var(--text);"><?= e($l['title']) ?></a>
    <p class="text-xs text-muted"><?= e($conditionLabels[$l['condition_type']] ?? $l['condition_type']) ?></p>
    <div class="flex justify-between items-center mt-1">
      <span class="price"><?= $l['price'] == 0 ? 'Free' : money((float) $l['price']) ?></span>
      <span class="text-xs text-muted flex items-center gap-1"><?= icon('map-pin') ?> <?= $l['shipping_type'] === 'local_pickup' ? 'Local Pickup' : 'Ships' ?></span>
    </div>
    <button type="button" class="btn btn-primary w-full" style="justify-content:center;" data-add-to-cart='<?= e($cartPayload) ?>'>Add to Cart</button>
  </div>
</div>
