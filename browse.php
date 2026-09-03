<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page_title = 'Browse Classroom Listings';
require __DIR__ . '/includes/layout_header.php';

$category = param('category');
$grade = param('grade');
$condition = param('condition');
$shipping = param('shipping');
$q = param('q');
$sort = param('sort') ?: 'newest';
$sortOptions = [
    'newest' => 'l.created_at DESC',
    'price_asc' => 'l.price ASC',
    'price_desc' => 'l.price DESC',
    'popular' => 'l.view_count DESC',
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

$categories = db()->query('SELECT id, name, slug FROM categories ORDER BY name')->fetchAll();

$conditions = ['l.is_active = 1'];
$params = [];
if ($category) { $conditions[] = 'c.slug = ?'; $params[] = $category; }
if ($grade) { $conditions[] = 'l.grade_level = ?'; $params[] = $grade; }
if ($condition) { $conditions[] = 'l.condition_type = ?'; $params[] = $condition; }
if ($shipping) { $conditions[] = "(l.shipping_type = ? OR l.shipping_type = 'both')"; $params[] = $shipping; }
if ($q) { $conditions[] = '(l.title LIKE ? OR l.description LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$sql = "SELECT l.*, u.name AS seller_name, u.is_verified AS seller_verified, c.name AS category_name,
               (SELECT image_url FROM listing_images WHERE listing_id = l.id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM listings l JOIN users u ON u.id = l.seller_id JOIN categories c ON c.id = l.category_id
        WHERE " . implode(' AND ', $conditions) . " ORDER BY $orderBy LIMIT 60";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();

$grades = ['Pre-K', 'K-2', '2nd-4th', '4th-8th', 'K-5', '6th-8th', '9th-12th'];
$conditionOptions = ['new' => 'New', 'like_new' => 'Like New', 'good' => 'Good', 'fair' => 'Fair', 'digital_download' => 'Digital Download'];
?>
<div class="container py-8">
  <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:1rem;">
    <h1 class="text-xl">Browse Classroom Listings</h1>
    <form method="get" class="flex gap-2">
      <?php foreach (['category' => $category, 'grade' => $grade, 'condition' => $condition, 'shipping' => $shipping, 'sort' => $sort] as $k => $v): ?>
        <?php if ($v): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <input type="text" name="q" placeholder="Search listings..." value="<?= e($q) ?>">
      <button class="btn btn-primary">Search</button>
    </form>
  </div>

  <div class="grid layout-filters mt-6">
    <aside>
      <h2 class="flex items-center gap-2 font-bold text-sm"><?= icon('toggle') ?> Filters</h2>
      <form method="get" class="stack mt-2">
        <?php if ($q): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <div class="field">
          <label>Category</label>
          <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= e($c['slug']) ?>" <?= $category === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Grade</label>
          <select name="grade" onchange="this.form.submit()">
            <option value="">Any Grade</option>
            <?php foreach ($grades as $g): ?>
              <option value="<?= e($g) ?>" <?= $grade === $g ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Condition</label>
          <select name="condition" onchange="this.form.submit()">
            <option value="">Any Condition</option>
            <?php foreach ($conditionOptions as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= $condition === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Fulfillment</label>
          <select name="shipping" onchange="this.form.submit()">
            <option value="">Shipping or Pickup</option>
            <option value="carrier" <?= $shipping === 'carrier' ? 'selected' : '' ?>>Carrier Shipping</option>
            <option value="local_pickup" <?= $shipping === 'local_pickup' ? 'selected' : '' ?>>Local Pickup</option>
          </select>
        </div>
        <?php if ($category || $grade || $condition || $shipping || $q): ?>
          <a href="/browse.php" class="link text-xs">Clear all filters</a>
        <?php endif; ?>
      </form>
    </aside>

    <div>
      <div class="flex justify-between items-center mb-2">
        <span class="text-sm text-muted"><?= count($listings) ?> result<?= count($listings) === 1 ? '' : 's' ?></span>
        <select onchange="const p=new URLSearchParams(location.search);p.set('sort',this.value);location.search=p.toString();" style="width:auto;">
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
          <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most viewed</option>
        </select>
      </div>
      <?php if (!$listings): ?>
        <p class="text-sm text-muted">No listings match these filters yet.</p>
      <?php endif; ?>
      <div class="grid grid-3">
        <?php foreach ($listings as $l): $l['seller_verified'] = $l['seller_verified']; include __DIR__ . '/includes/partials/listing_card.php'; endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
