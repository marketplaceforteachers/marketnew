<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('id');
    $resolution = post('resolution');
    $partialAmount = post('partialAmount') !== '' ? (float) post('partialAmount') : null;

    $stmt = db()->prepare('SELECT * FROM disputes WHERE id = ?');
    $stmt->execute([$id]);
    $dispute = $stmt->fetch();

    if ($dispute) {
        try {
            if ($resolution === 'full_refund') {
                refund_order((int) $dispute['order_id']);
            } elseif ($resolution === 'partial_refund') {
                refund_order((int) $dispute['order_id'], $partialAmount);
            } elseif ($resolution === 'fund_release') {
                db()->prepare("UPDATE orders SET status = 'completed' WHERE id = ?")->execute([$dispute['order_id']]);
                db()->prepare("UPDATE seller_payouts SET status = 'in_transit' WHERE order_id = ? AND status = 'pending'")->execute([$dispute['order_id']]);
            }
            db()->prepare("UPDATE disputes SET status = 'resolved', resolution = ? WHERE id = ?")->execute([$resolution, $id]);
            log_admin_action($me['id'], "dispute.resolve.$resolution", 'disputes', $id);

            $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
            $stmt->execute([$dispute['raised_by']]);
            if ($raiser = $stmt->fetch()) {
                send_transactional_email('dispute_resolution', $raiser['email'], ['dispute_id' => $id, 'resolution' => $resolution]);
            }
            flash('success', 'Dispute resolved.');
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('/admin/disputes.php');
}

$disputes = db()->query(
    "SELECT d.*, u.name AS raised_by_name, o.total_amount AS order_total
     FROM disputes d JOIN users u ON u.id = d.raised_by JOIN orders o ON o.id = d.order_id
     ORDER BY d.created_at DESC"
)->fetchAll();

$page_title = 'Dispute Arbitration';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Dispute Arbitration Console</h1>
<p class="text-sm text-muted mt-1">Resolving triggers a real refund through the order's payment gateway, or releases held funds to the seller.</p>
<div class="stack mt-4">
  <?php if (!$disputes): ?><p class="text-sm text-muted">No disputes filed.</p><?php endif; ?>
  <?php foreach ($disputes as $d): ?>
    <div class="card card-pad">
      <div class="flex justify-between items-center" style="flex-wrap:wrap;gap:.5rem;">
        <div>
          <p class="font-bold">Order #<?= $d['order_id'] ?> &middot; <?= e($d['reason']) ?></p>
          <p class="text-xs text-muted">Filed by <?= e($d['raised_by_name']) ?> &middot; <?= money((float) $d['order_total']) ?> order</p>
        </div>
        <span class="status-badge <?= $d['status'] === 'resolved' ? 'status-approved' : 'status-pending' ?>"><?= $d['status'] === 'resolved' ? 'Resolved — ' . e($d['resolution']) : e($d['status']) ?></span>
      </div>
      <?php if ($d['description']): ?><p class="text-sm mt-2"><?= e($d['description']) ?></p><?php endif; ?>
      <?php if ($d['evidence_url']): ?><a href="<?= e($d['evidence_url']) ?>" target="_blank" class="link text-xs">View evidence</a><?php endif; ?>

      <?php if ($d['status'] !== 'resolved'): ?>
        <div class="flex gap-2 mt-3" style="flex-wrap:wrap;">
          <form method="post" onsubmit="return confirm('Issue a full refund?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="resolution" value="full_refund">
            <button class="btn" style="background:var(--red-600);color:#fff;">Full Refund</button>
          </form>
          <form method="post" onsubmit="return this.querySelector('[name=partialAmount]').value">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="resolution" value="partial_refund">
            <input type="number" name="partialAmount" step="0.01" placeholder="Amount" style="width:6rem;display:inline-block;">
            <button class="btn btn-amber">Partial Refund</button>
          </form>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="resolution" value="fund_release">
            <button class="btn" style="background:var(--emerald-600);color:#fff;">Release Funds to Seller</button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
