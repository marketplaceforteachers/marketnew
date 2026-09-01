<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role('teacher', 'admin');

$grades = ['Pre-K', 'K-2', '2nd-4th', '4th-8th', 'K-5', '6th-8th', '9th-12th'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim(post('title'));
    $description = trim(post('description'));
    $categoryId = (int) post('category_id');
    $price = (float) post('price');
    $gradeLevel = trim(post('grade_level'));
    $conditionType = post('condition_type');
    $shippingType = post('shipping_type');
    $shippingFee = (float) post('shipping_fee', 0);
    $imageUrls = array_filter(array_map('trim', post('image_urls', [])));

    if (strlen($title) < 3 || !$categoryId) {
        $error = 'Please fill in a title and choose a category.';
    } else {
        $slug = slugify($title);
        db()->prepare(
            'INSERT INTO listings (seller_id, category_id, title, slug, description, price, grade_level, condition_type, shipping_type, shipping_fee, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([$me['id'], $categoryId, $title, $slug, $description, $price, $gradeLevel, $conditionType, $shippingType, $shippingFee]);
        $listingId = (int) db()->lastInsertId();

        foreach (array_values($imageUrls) as $i => $url) {
            db()->prepare('INSERT INTO listing_images (listing_id, image_url, is_primary) VALUES (?, ?, ?)')
                ->execute([$listingId, $url, $i === 0 ? 1 : 0]);
        }

        enroll_in_drips('listing_posted', (int) $me['id']);

        flash('success', 'Listing posted!');
        redirect('/listing.php?slug=' . $slug);
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$page_title = 'Post a Listing';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-xl">Post a Free Teacher Listing</h1>
  <p class="text-sm text-muted mt-1">Zero listing fees. Your item goes live immediately.</p>

  <form method="post" class="card card-pad mt-4">
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

    <div class="field"><label>Title</label><input type="text" name="title" required minlength="3" value="<?= old('title') ?>"></div>
    <div class="field"><label>Description</label><textarea name="description" rows="4" required><?= old('description') ?></textarea></div>

    <div class="grid grid-2">
      <div class="field">
        <label>Category</label>
        <select name="category_id" required>
          <option value="">Select…</option>
          <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Grade Level</label>
        <select name="grade_level">
          <?php foreach ($grades as $g): ?><option value="<?= e($g) ?>"><?= e($g) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid grid-2">
      <div class="field"><label>Price ($0 = Free)</label><input type="number" name="price" min="0" step="0.01" required></div>
      <div class="field">
        <label>Condition</label>
        <select name="condition_type">
          <option value="new">New</option>
          <option value="like_new">Like New</option>
          <option value="good" selected>Good</option>
          <option value="fair">Fair</option>
          <option value="digital_download">Digital Download</option>
        </select>
      </div>
    </div>

    <div class="grid grid-2">
      <div class="field">
        <label>Fulfillment</label>
        <select name="shipping_type">
          <option value="both" selected>Ship or Local Pickup</option>
          <option value="carrier">Ship Only</option>
          <option value="local_pickup">Local Pickup Only</option>
        </select>
      </div>
      <div class="field"><label>Shipping Fee</label><input type="number" name="shipping_fee" min="0" step="0.01" value="0"></div>
    </div>

    <div class="field">
      <label>Photo URLs</label>
      <div id="photo-rows-list" class="stack">
        <div class="dynamic-row flex gap-2"><input type="url" name="image_urls[]" placeholder="https://..."></div>
      </div>
      <template id="photo-rows">
        <div class="dynamic-row flex gap-2 mt-2">
          <input type="url" name="image_urls[]" placeholder="https://...">
          <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
        </div>
      </template>
      <button type="button" class="link text-xs mt-2" data-add-row="photo-rows"><?= icon('plus') ?> Add another photo</button>
    </div>

    <button class="btn btn-primary w-full mt-2" style="justify-content:center;">Post Listing</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
