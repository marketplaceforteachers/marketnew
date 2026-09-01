<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = current_user();
$wishlists = db()->query("SELECT w.*, u.name AS teacher_name FROM wishlists w JOIN users u ON u.id = w.teacher_id ORDER BY w.created_at DESC")->fetchAll();
$page_title = 'Classroom Wishlists';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container py-8">
  <div class="flex justify-between items-center" style="flex-wrap:wrap;">
    <div>
      <h1 class="text-xl flex items-center gap-2"><?= icon('gift') ?> Classroom Wishlists</h1>
      <p class="text-sm text-muted mt-1">Support a teacher's classroom by funding items on their registry.</p>
    </div>
    <?php if ($me && $me['role'] === 'teacher'): ?>
      <a href="/wishlist-new.php" class="btn btn-primary"><?= icon('plus') ?> New Wishlist</a>
    <?php endif; ?>
  </div>

  <?php if (!$wishlists): ?><p class="text-sm text-muted mt-6">No wishlists published yet.</p><?php endif; ?>
  <div class="grid grid-3 mt-4">
    <?php foreach ($wishlists as $w): $pct = $w['goal_amount'] > 0 ? min(100, round($w['raised_amount'] / $w['goal_amount'] * 100)) : 0; ?>
      <a href="/wishlist.php?id=<?= $w['id'] ?>" class="card card-pad">
        <p class="font-bold"><?= e($w['title']) ?></p>
        <p class="text-xs text-muted"><?= e($w['teacher_name']) ?><?= $w['school'] ? ' · ' . e($w['school']) : '' ?><?= $w['grade'] ? ' · ' . e($w['grade']) : '' ?></p>
        <div class="progress-track mt-3"><div class="progress-fill" style="width:<?= $pct ?>%;"></div></div>
        <p class="text-xs font-bold mt-1"><?= money((float) $w['raised_amount']) ?> of <?= money((float) $w['goal_amount']) ?> raised</p>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
