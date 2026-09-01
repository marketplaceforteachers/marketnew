<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_auth();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int) post('order_id');
    $reason = trim(post('reason'));
    $description = trim(post('description'));
    $evidenceUrl = trim(post('evidence_url'));

    $stmt = db()->prepare('SELECT buyer_id FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || (int) $order['buyer_id'] !== (int) $me['id']) {
        $error = 'Order not found or not yours.';
    } elseif (!$reason) {
        $error = 'Please enter a reason.';
    } else {
        db()->prepare('INSERT INTO disputes (order_id, raised_by, reason, description, evidence_url, status) VALUES (?, ?, ?, ?, ?, \'open\')')
            ->execute([$orderId, $me['id'], $reason, $description, $evidenceUrl ?: null]);
        db()->prepare("UPDATE orders SET status = 'disputed' WHERE id = ?")->execute([$orderId]);
        flash('success', 'Dispute filed.');
        redirect('/disputes.php');
    }
}

$stmt = db()->prepare('SELECT * FROM disputes WHERE raised_by = ? ORDER BY created_at DESC');
$stmt->execute([$me['id']]);
$disputes = $stmt->fetchAll();

$page_title = 'Buyer Protection Center';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-md py-8">
  <h1 class="text-xl flex items-center gap-2"><?= icon('gavel') ?> Buyer Protection &amp; Dispute Center</h1>
  <p class="text-sm text-muted mt-1">File a dispute for a missing item, shipping damage, or an inaccurate listing. Our team responds within 72 hours.</p>

  <form method="post" class="card card-pad mt-4">
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <div class="grid grid-2">
      <div class="field"><label>Order ID</label><input type="number" name="order_id" required></div>
      <div class="field"><label>Reason</label><input type="text" name="reason" required placeholder="e.g. Item never arrived"></div>
    </div>
    <div class="field"><label>Description</label><textarea name="description" rows="3"></textarea></div>
    <div class="field"><label>Evidence URL (optional)</label><input type="url" name="evidence_url" placeholder="Link to photos, tracking info, etc."></div>
    <button class="btn btn-primary">File Dispute</button>
  </form>

  <h2 class="text-lg mt-6">Your disputes</h2>
  <div class="stack mt-3">
    <?php if (!$disputes): ?><p class="text-sm text-muted">No disputes filed.</p><?php endif; ?>
    <?php foreach ($disputes as $d): ?>
      <div class="card card-pad">
        <div class="flex justify-between items-center">
          <p class="font-bold">Order #<?= $d['order_id'] ?> — <?= e($d['reason']) ?></p>
          <span class="status-badge status-pending"><?= e($d['status']) ?></span>
        </div>
        <?php if ($d['description']): ?><p class="text-sm text-muted mt-1"><?= e($d['description']) ?></p><?php endif; ?>
        <?php if ($d['resolution'] !== 'none'): ?><p class="text-xs mt-1" style="color:var(--emerald-600);font-weight:700;">Resolution: <?= e($d['resolution']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
