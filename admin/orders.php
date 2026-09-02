<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/orders.php';
$me = require_admin();

$statuses = ['pending', 'paid', 'shipped', 'delivered', 'completed', 'cancelled', 'disputed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('id');
    if (post('action') === 'set_status') {
        $status = post('status');
        if (in_array($status, $statuses, true)) {
            db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
            log_admin_action($me['id'], 'order.status_update', 'orders', $id);
            if ($status === 'shipped') {
                $stmt = db()->prepare('SELECT u.email FROM orders o JOIN users u ON u.id = o.buyer_id WHERE o.id = ?');
                $stmt->execute([$id]);
                if ($row = $stmt->fetch()) {
                    send_transactional_email('shipping_confirmation', $row['email'], ['order_id' => $id, 'tracking_url' => '']);
                }
            }
        }
    } elseif (post('action') === 'refund') {
        try {
            refund_order($id);
            log_admin_action($me['id'], 'order.refund', 'orders', $id);
            flash('success', "Order #$id refunded.");
        } catch (Exception $e) {
            flash('error', $e->getMessage());
        }
    }
    redirect('/admin/orders.php');
}

$orders = db()->query(
    "SELECT o.*, u.name AS buyer_name, u.email AS buyer_email FROM orders o JOIN users u ON u.id = o.buyer_id ORDER BY o.created_at DESC LIMIT 300"
)->fetchAll();

$page_title = 'Order Manager';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Order Manager</h1>
<p class="text-sm text-muted mt-1">Override statuses or issue refunds.</p>
<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>Order</th><th>Buyer</th><th>Ship To</th><th>Total</th><th>Gateway</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td class="font-bold">#<?= $o['id'] ?></td>
          <td class="text-muted"><?= e($o['buyer_name']) ?><br><span class="text-xs"><?= e($o['buyer_email']) ?></span></td>
          <td class="text-muted text-xs">
            <?php if ($o['shipping_address'] === 'Local pickup'): ?>
              Local pickup
            <?php elseif ($o['shipping_address']): ?>
              <?= e($o['shipping_name'] ?? '') ?><?php if ($o['shipping_phone']): ?><br><?= e($o['shipping_phone']) ?><?php endif; ?><br>
              <?= e($o['shipping_address']) ?><?php if ($o['shipping_city']): ?>, <?= e($o['shipping_city']) ?><?php endif; ?> <?= e($o['shipping_state'] ?? '') ?> <?= e($o['shipping_zip'] ?? '') ?>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= money((float) $o['total_amount']) ?></td>
          <td class="text-muted"><?= e($o['payment_gateway']) ?></td>
          <td>
            <form method="post" onchange="this.submit()"><?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="id" value="<?= $o['id'] ?>">
              <select name="status">
                <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?>
              </select>
            </form>
          </td>
          <td style="text-align:right;">
            <?php if ($o['payment_reference']): ?>
              <form method="post" onsubmit="return confirm('Refund order #<?= $o['id'] ?>?')"><?= csrf_field() ?><input type="hidden" name="action" value="refund"><input type="hidden" name="id" value="<?= $o['id'] ?>">
                <button type="submit" class="link text-xs" style="background:none;border:none;color:var(--red-600);cursor:pointer;">Refund</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
