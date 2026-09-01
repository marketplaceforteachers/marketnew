<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$disputeStats = db()->query("SELECT COUNT(*) AS total, SUM(CASE WHEN resolution IN ('full_refund','partial_refund') THEN 1 ELSE 0 END) AS refunded FROM disputes")->fetch();
$orderStats = db()->query("SELECT COUNT(*) AS n FROM orders WHERE status NOT IN ('pending','cancelled')")->fetch();
$disputeRate = $orderStats['n'] > 0 ? ($disputeStats['total'] / $orderStats['n']) * 100 : 0;

$domainClusters = db()->query(
    "SELECT SUBSTRING_INDEX(email, '@', -1) AS domain, COUNT(*) AS account_count
     FROM users GROUP BY domain HAVING account_count > 2 ORDER BY account_count DESC LIMIT 20"
)->fetchAll();

$cancellations = db()->query(
    "SELECT o.id, o.total_amount, u.email AS buyer_email FROM orders o JOIN users u ON u.id = o.buyer_id
     WHERE o.status = 'cancelled' ORDER BY o.created_at DESC LIMIT 20"
)->fetchAll();

$page_title = 'Safety & Risk Monitor';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Safety &amp; Risk Monitor</h1>
<p class="text-sm text-muted mt-1">Dispute-rate trend and simple fraud heuristics.</p>

<div class="grid grid-3 mt-4">
  <div class="card card-pad"><p class="text-2xl font-extrabold"><?= number_format($disputeRate, 2) ?>%</p><p class="text-xs text-muted" style="text-transform:uppercase;">Dispute Rate</p></div>
  <div class="card card-pad"><p class="text-2xl font-extrabold"><?= (int) $disputeStats['total'] ?></p><p class="text-xs text-muted" style="text-transform:uppercase;">Total Disputes</p></div>
  <div class="card card-pad"><p class="text-2xl font-extrabold"><?= (int) $disputeStats['refunded'] ?></p><p class="text-xs text-muted" style="text-transform:uppercase;">Refunded Disputes</p></div>
</div>

<h2 class="text-lg mt-6">Suspicious multi-account clusters (by email domain)</h2>
<div class="table-wrap mt-2">
  <table><thead><tr><th>Domain</th><th>Accounts</th></tr></thead><tbody>
    <?php if (!$domainClusters): ?><tr><td colspan="2" class="text-muted">None detected.</td></tr><?php endif; ?>
    <?php foreach ($domainClusters as $c): ?><tr><td><?= e($c['domain']) ?></td><td class="text-muted"><?= $c['account_count'] ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div>

<h2 class="text-lg mt-6">Recent cancellations</h2>
<div class="table-wrap mt-2">
  <table><thead><tr><th>Order</th><th>Buyer</th><th>Amount</th></tr></thead><tbody>
    <?php foreach ($cancellations as $c): ?><tr><td>#<?= $c['id'] ?></td><td class="text-muted"><?= e($c['buyer_email']) ?></td><td class="text-muted"><?= money((float) $c['total_amount']) ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
