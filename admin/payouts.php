<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/stripe.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('id');
    if (post('action') === 'send') {
        $stmt = db()->prepare('SELECT p.*, u.stripe_account_id FROM seller_payouts p JOIN users u ON u.id = p.seller_id WHERE p.id = ?');
        $stmt->execute([$id]);
        $payout = $stmt->fetch();
        if (!$payout) {
            flash('error', 'Payout not found');
        } elseif ($payout['status'] !== 'pending') {
            // Guards against a double-click or two concurrent admin requests triggering a second
            // real Stripe transfer for a payout that's already been sent.
            flash('error', 'This payout has already been ' . $payout['status'] . '.');
        } elseif (!$payout['stripe_account_id']) {
            flash('error', 'Seller has not connected a Stripe account yet.');
        } else {
            try {
                $transfer = stripe_create_transfer((float) $payout['payout_amount'], $payout['stripe_account_id']);
                db()->prepare("UPDATE seller_payouts SET status = 'paid', stripe_transfer_id = ? WHERE id = ?")
                    ->execute([$transfer['id'], $id]);
                log_admin_action($me['id'], 'payout.send', 'seller_payouts', $id);
                flash('success', 'Payout sent.');
            } catch (Exception $e) {
                flash('error', $e->getMessage());
            }
        }
    }
    redirect('/admin/payouts.php');
}

$payouts = db()->query(
    "SELECT p.*, u.name AS seller_name, u.stripe_account_id FROM seller_payouts p JOIN users u ON u.id = p.seller_id ORDER BY p.created_at DESC LIMIT 300"
)->fetchAll();

$page_title = 'Seller Payouts';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Seller Payouts</h1>
<p class="text-sm text-muted mt-1">Payout rows are created automatically when an order is paid. "Send" triggers a real Stripe transfer.</p>
<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>Seller</th><th>Order</th><th>Payout</th><th>Fee</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($payouts as $p): ?>
        <tr>
          <td class="font-bold"><?= e($p['seller_name']) ?></td>
          <td class="text-muted">#<?= $p['order_id'] ?></td>
          <td class="text-muted"><?= money((float) $p['payout_amount']) ?></td>
          <td class="text-muted"><?= money((float) $p['fee_amount']) ?></td>
          <td><span class="status-badge status-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
          <td style="text-align:right;">
            <?php if ($p['status'] === 'pending'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="link text-xs" style="background:none;border:none;cursor:pointer;" <?= $p['stripe_account_id'] ? '' : 'disabled title="Seller has not connected Stripe"' ?>><?= icon('send') ?> Send</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
