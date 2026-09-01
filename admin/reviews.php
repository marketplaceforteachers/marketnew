<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('id');
    db()->prepare('DELETE FROM reviews WHERE id = ?')->execute([$id]);
    log_admin_action($me['id'], 'review.delete', 'reviews', $id);
    redirect('/admin/reviews.php');
}

$reviews = db()->query(
    "SELECT r.*, reviewer.name AS reviewer_name, seller.name AS seller_name
     FROM reviews r JOIN users reviewer ON reviewer.id = r.reviewer_id JOIN users seller ON seller.id = r.seller_id
     ORDER BY r.created_at DESC LIMIT 300"
)->fetchAll();

$page_title = 'Reviews Moderation';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Reviews Moderation</h1>
<p class="text-sm text-muted mt-1">Remove reviews that violate policy.</p>
<div class="stack mt-4">
  <?php foreach ($reviews as $r): ?>
    <div class="card card-pad flex justify-between items-start">
      <div>
        <div class="flex items-center gap-1">
          <?php for ($i = 1; $i <= 5; $i++): ?><span style="color:<?= $i <= $r['rating'] ? 'var(--amber-400)' : 'var(--slate-200)' ?>;"><?= icon('star') ?></span><?php endfor; ?>
          <span class="text-xs text-muted" style="margin-left:.5rem;"><?= e($r['reviewer_name']) ?> &rarr; <?= e($r['seller_name']) ?></span>
        </div>
        <p class="text-sm mt-1"><?= e($r['comment']) ?></p>
        <?php if ($r['seller_reply']): ?><p class="text-xs text-muted mt-1"><em>Seller reply: <?= e($r['seller_reply']) ?></em></p><?php endif; ?>
      </div>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
        <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--slate-500);"><?= icon('trash') ?></button>
      </form>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
