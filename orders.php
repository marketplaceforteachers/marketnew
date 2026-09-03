<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'review') {
    verify_csrf();
    $orderId = (int) post('order_id');
    $sellerId = (int) post('seller_id');
    $rating = max(1, min(5, (int) post('rating')));
    $comment = trim(post('comment'));

    $stmt = db()->prepare('SELECT buyer_id, status FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    // $sellerId comes from the client — without checking it's actually a seller in THIS order,
    // anyone with one paid order (even a free/$0 one) could post a review against any seller on
    // the site, no real transaction with them required.
    $stmt = db()->prepare('SELECT 1 FROM order_items WHERE order_id = ? AND seller_id = ? LIMIT 1');
    $stmt->execute([$orderId, $sellerId]);
    $sellerIsInOrder = (bool) $stmt->fetchColumn();
    if ($order && $sellerIsInOrder && (int) $order['buyer_id'] === (int) $me['id'] && in_array($order['status'], ['paid', 'shipped', 'delivered', 'completed'], true)) {
        db()->prepare('INSERT INTO reviews (order_id, reviewer_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?)')
            ->execute([$orderId, $me['id'], $sellerId, $rating, $comment]);
        flash('success', 'Review submitted — thank you!');
    }
    redirect('/orders.php');
}

$stmt = db()->prepare('SELECT * FROM orders WHERE buyer_id = ? ORDER BY created_at DESC');
$stmt->execute([$me['id']]);
$orders = $stmt->fetchAll();

foreach ($orders as &$order) {
    $stmt = db()->prepare(
        'SELECT DISTINCT oi.seller_id, u.name AS seller_name FROM order_items oi JOIN users u ON u.id = oi.seller_id WHERE oi.order_id = ?'
    );
    $stmt->execute([$order['id']]);
    $order['sellers'] = $stmt->fetchAll();

    $stmt = db()->prepare('SELECT id FROM reviews WHERE order_id = ? AND reviewer_id = ?');
    $stmt->execute([$order['id'], $me['id']]);
    $order['already_reviewed'] = (bool) $stmt->fetch();
}
unset($order);

$reviewable = ['paid', 'shipped', 'delivered', 'completed'];
$page_title = 'Your Orders';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-md py-8">
  <h1 class="text-xl flex items-center gap-2"><?= icon('package') ?> Your Orders</h1>
  <?php if (!$orders): ?><p class="text-sm text-muted mt-4">No orders yet.</p><?php endif; ?>
  <div class="stack mt-4">
    <?php foreach ($orders as $o): ?>
      <div class="card card-pad">
        <div class="flex justify-between items-center">
          <div>
            <p class="font-bold">Order #<?= $o['id'] ?></p>
            <p class="text-xs text-muted">
              <?= date('M j, Y', strtotime($o['created_at'])) ?> &middot; <?= e($o['payment_gateway']) ?>
              <?php if ($o['sellers']): ?> &middot; sold by <?= e(implode(', ', array_column($o['sellers'], 'seller_name'))) ?><?php endif; ?>
            </p>
          </div>
          <div style="text-align:right;">
            <p class="font-extrabold"><?= money((float) $o['total_amount']) ?></p>
            <span class="status-badge status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span>
          </div>
        </div>
        <?php if ($o['shipping_address'] && $o['shipping_address'] !== 'Local pickup'): ?>
          <p class="text-xs text-muted mt-2"><?= icon('map-pin') ?>
            <?= e($o['shipping_name'] ?? '') ?><?= $o['shipping_name'] ? ' — ' : '' ?><?= e($o['shipping_address']) ?><?php if ($o['shipping_city']): ?>, <?= e($o['shipping_city']) ?><?php endif; ?><?php if ($o['shipping_state']): ?>, <?= e($o['shipping_state']) ?><?php endif; ?> <?= e($o['shipping_zip'] ?? '') ?>
          </p>
        <?php elseif ($o['shipping_address'] === 'Local pickup'): ?>
          <p class="text-xs text-muted mt-2"><?= icon('map-pin') ?> Local pickup</p>
        <?php endif; ?>

        <?php if (in_array($o['status'], $reviewable, true) && !$o['already_reviewed'] && $o['sellers']): ?>
          <form method="post" class="mt-3" style="border-top:1px solid var(--slate-100);padding-top:.75rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="review">
            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
            <?php if (count($o['sellers']) > 1): ?>
              <select name="seller_id" class="mt-1">
                <?php foreach ($o['sellers'] as $s): ?><option value="<?= $s['seller_id'] ?>"><?= e($s['seller_name']) ?></option><?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="hidden" name="seller_id" value="<?= $o['sellers'][0]['seller_id'] ?>">
            <?php endif; ?>
            <div class="flex gap-1 mt-2">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <label><input type="radio" name="rating" value="<?= $i ?>" <?= $i == 5 ? 'checked' : '' ?> style="display:none;" class="rating-radio"><span class="star-label" data-star="<?= $i ?>" style="cursor:pointer;color:var(--slate-300);"><?= icon('star') ?></span></label>
              <?php endfor; ?>
            </div>
            <textarea name="comment" rows="2" placeholder="How was this transaction?" class="mt-2"></textarea>
            <button class="btn btn-primary mt-2">Submit Review</button>
          </form>
        <?php elseif ($o['already_reviewed']): ?>
          <p class="text-xs mt-2" style="color:var(--emerald-600);font-weight:700;">Review submitted — thank you!</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
document.querySelectorAll('.star-label').forEach((star) => {
  star.addEventListener('click', () => {
    const form = star.closest('form');
    const val = star.dataset.star;
    form.querySelector(`input[value="${val}"]`).checked = true;
    form.querySelectorAll('.star-label').forEach((s) => {
      s.style.color = s.dataset.star <= val ? 'var(--amber-400)' : 'var(--slate-300)';
    });
  });
});
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
