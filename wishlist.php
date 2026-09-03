<?php
require_once __DIR__ . '/includes/bootstrap.php';
$id = (int) param('id');
$stmt = db()->prepare("SELECT w.*, u.name AS teacher_name FROM wishlists w JOIN users u ON u.id = w.teacher_id WHERE w.id = ?");
$stmt->execute([$id]);
$wishlist = $stmt->fetch();
if (!$wishlist) {
    http_response_code(404);
    $page_title = 'Not Found';
    $page_noindex = true;
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="container py-10 text-center"><p>Wishlist not found.</p></div>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

$stmt = db()->prepare('SELECT * FROM wishlist_items WHERE wishlist_id = ?');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$pct = $wishlist['goal_amount'] > 0 ? min(100, round($wishlist['raised_amount'] / $wishlist['goal_amount'] * 100)) : 0;
$page_title = $wishlist['title'];
$page_description = $wishlist['teacher_name'] . "'s classroom wishlist" . ($wishlist['school'] ? ' at ' . $wishlist['school'] : '') . ' — help fund supplies for their students.';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-2xl flex items-center gap-2"><?= icon('gift') ?> <?= e($wishlist['title']) ?></h1>
  <p class="text-sm text-muted mt-1"><?= e($wishlist['teacher_name']) ?><?= $wishlist['school'] ? ' · ' . e($wishlist['school']) : '' ?><?= $wishlist['grade'] ? ' · ' . e($wishlist['grade']) : '' ?></p>
  <div class="progress-track mt-4"><div class="progress-fill" style="width:<?= $pct ?>%;"></div></div>
  <p class="text-sm font-bold mt-2"><?= money((float) $wishlist['raised_amount']) ?> raised of <?= money((float) $wishlist['goal_amount']) ?> goal</p>

  <h2 class="text-lg mt-6">Items on this list</h2>
  <div class="stack mt-3">
    <?php foreach ($items as $item): ?>
      <div class="card card-pad flex justify-between items-center">
        <div>
          <p class="font-bold" style="<?= $item['is_funded'] ? 'color:var(--slate-400);text-decoration:line-through;' : '' ?>"><?= e($item['item_name']) ?></p>
          <p class="text-xs text-muted"><?= money((float) $item['price']) ?></p>
        </div>
        <?php if ($item['is_funded']): ?>
          <span class="text-xs" style="color:var(--emerald-600);font-weight:700;">Funded</span>
        <?php elseif ($item['listing_id']): ?>
          <a href="/browse.php" class="btn btn-primary"><?= icon('shopping-bag') ?> Buy this item</a>
        <?php else: ?>
          <span class="text-xs text-muted">Contact the teacher to fund</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$items): ?><p class="text-sm text-muted">No items added yet.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
