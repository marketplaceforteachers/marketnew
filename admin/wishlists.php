<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$wishlists = db()->query("SELECT w.*, u.name AS teacher_name FROM wishlists w JOIN users u ON u.id = w.teacher_id ORDER BY w.created_at DESC")->fetchAll();
$campaigns = db()->query("SELECT c.*, u.name AS teacher_name FROM fundraising_campaigns c JOIN users u ON u.id = c.teacher_id ORDER BY c.created_at DESC")->fetchAll();

$page_title = 'Wishlists & Fundraising';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Wishlist &amp; Fundraising Oversight</h1>
<p class="text-sm text-muted mt-1">Read-only view of every published wishlist and campaign.</p>

<h2 class="text-lg mt-6">Wishlists</h2>
<div class="table-wrap mt-2">
  <table>
    <thead><tr><th>Title</th><th>Teacher</th><th>Progress</th></tr></thead>
    <tbody>
      <?php foreach ($wishlists as $w): ?>
        <tr><td class="font-bold"><?= e($w['title']) ?></td><td class="text-muted"><?= e($w['teacher_name']) ?></td><td class="text-muted"><?= money((float) $w['raised_amount']) ?> / <?= money((float) $w['goal_amount']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2 class="text-lg mt-6">Fundraising campaigns</h2>
<div class="table-wrap mt-2">
  <table>
    <thead><tr><th>Title</th><th>Teacher</th><th>Progress</th></tr></thead>
    <tbody>
      <?php foreach ($campaigns as $c): ?>
        <tr><td class="font-bold"><?= e($c['title']) ?></td><td class="text-muted"><?= e($c['teacher_name']) ?></td><td class="text-muted"><?= money((float) $c['current_funds']) ?> / <?= money((float) $c['target_funds']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
