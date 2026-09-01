<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = db()->query(
        "SELECT o.id AS order_id, o.total_amount, o.status, o.payment_gateway, o.created_at, u.name AS buyer_name
         FROM orders o JOIN users u ON u.id = o.buyer_id ORDER BY o.created_at DESC"
    )->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=orders-report.csv');
    $out = fopen('php://output', 'w');
    if ($rows) fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) fputcsv($out, $row);
    fclose($out);
    exit;
}

$sellers = db()->query(
    "SELECT u.id, u.name, u.email, COALESCE(SUM(p.payout_amount),0) AS total_payouts,
            COALESCE(SUM(p.fee_amount),0) AS total_fees, COUNT(p.id) AS payout_count
     FROM users u LEFT JOIN seller_payouts p ON p.seller_id = u.id
     WHERE u.role = 'teacher' GROUP BY u.id, u.name, u.email ORDER BY total_payouts DESC"
)->fetchAll();

$page_title = 'Financial & Tax Reporting';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<div class="flex justify-between items-center" style="flex-wrap:wrap;gap:1rem;">
  <div>
    <h1 class="text-xl">Financial &amp; Tax Reporting</h1>
    <p class="text-sm text-muted mt-1">Per-seller earnings rollup and an orders CSV export.</p>
  </div>
  <a href="/admin/financial.php?export=csv" class="btn btn-primary"><?= icon('download') ?> Export Orders CSV</a>
</div>
<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>Seller</th><th>Payouts</th><th>Platform Fees</th><th>Payout Count</th></tr></thead>
    <tbody>
      <?php foreach ($sellers as $s): ?>
        <tr>
          <td><strong><?= e($s['name']) ?></strong><br><span class="text-xs text-muted"><?= e($s['email']) ?></span></td>
          <td class="text-muted"><?= money((float) $s['total_payouts']) ?></td>
          <td class="text-muted"><?= money((float) $s['total_fees']) ?></td>
          <td class="text-muted"><?= $s['payout_count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
