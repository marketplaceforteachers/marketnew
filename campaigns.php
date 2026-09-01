<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = current_user();
$campaigns = db()->query("SELECT c.*, u.name AS teacher_name FROM fundraising_campaigns c JOIN users u ON u.id = c.teacher_id ORDER BY c.created_at DESC")->fetchAll();
$page_title = 'Classroom Fundraising';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container py-8">
  <div class="flex justify-between items-center" style="flex-wrap:wrap;">
    <div>
      <h1 class="text-xl flex items-center gap-2"><?= icon('heart') ?> Classroom Fundraising</h1>
      <p class="text-sm text-muted mt-1">Back a classroom project or book drive with a tax-deductible donation.</p>
    </div>
    <?php if ($me && $me['role'] === 'teacher'): ?>
      <a href="/campaign-new.php" class="btn btn-amber"><?= icon('plus') ?> New Campaign</a>
    <?php endif; ?>
  </div>

  <?php if (!$campaigns): ?><p class="text-sm text-muted mt-6">No active campaigns yet.</p><?php endif; ?>
  <div class="grid grid-2 mt-4">
    <?php foreach ($campaigns as $c): $pct = $c['target_funds'] > 0 ? min(100, round($c['current_funds'] / $c['target_funds'] * 100)) : 0; ?>
      <a href="/campaign.php?id=<?= $c['id'] ?>" class="card card-pad">
        <p class="font-bold"><?= e($c['title']) ?></p>
        <p class="text-xs text-muted mt-1"><?= e(truncate($c['story'] ?? '', 120)) ?></p>
        <p class="text-xs text-muted mt-2">by <?= e($c['teacher_name']) ?></p>
        <div class="progress-track mt-3"><div class="progress-fill" style="width:<?= $pct ?>%;background:var(--amber-500);"></div></div>
        <p class="text-xs font-bold mt-1"><?= money((float) $c['current_funds']) ?> of <?= money((float) $c['target_funds']) ?> raised</p>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
