<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$gmv = db()->query("SELECT COALESCE(SUM(total_amount),0) AS gmv, COUNT(*) AS orderCount FROM orders WHERE status IN ('paid','shipped','delivered','completed')")->fetch();
$fee = db()->query('SELECT COALESCE(SUM(fee_amount),0) AS platformRevenue FROM seller_payouts')->fetch();
$teachers = db()->query("SELECT COUNT(*) AS n FROM users WHERE role = 'teacher'")->fetch();
$activeListings = db()->query('SELECT COUNT(*) AS n FROM listings WHERE is_active = 1')->fetch();
$totalUsers = db()->query('SELECT COUNT(*) AS n FROM users')->fetch();
$pendingOrders = db()->query("SELECT COUNT(*) AS n FROM orders WHERE status = 'pending'")->fetch();
$openDisputes = db()->query("SELECT COUNT(*) AS n FROM disputes WHERE status != 'resolved'")->fetch();

$stats = [
    ['icon' => 'wallet', 'label' => 'Gross Merchandise Value', 'value' => money((float) $gmv['gmv'])],
    ['icon' => 'file-bar', 'label' => 'Net Platform Revenue', 'value' => money((float) $fee['platformRevenue'])],
    ['icon' => 'package', 'label' => 'Paid Orders', 'value' => $gmv['orderCount']],
    ['icon' => 'user-group', 'label' => 'Active Teachers', 'value' => $teachers['n']],
    ['icon' => 'package', 'label' => 'Active Listings', 'value' => $activeListings['n']],
    ['icon' => 'user-group', 'label' => 'Total Users', 'value' => $totalUsers['n']],
    ['icon' => 'history', 'label' => 'Pending Orders', 'value' => $pendingOrders['n']],
    ['icon' => 'alert-triangle', 'label' => 'Open Disputes', 'value' => $openDisputes['n']],
];

$page_title = 'Executive Dashboard';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Executive Dashboard</h1>
<p class="text-sm text-muted mt-1">Live totals computed straight from the database.</p>
<div class="grid grid-4 mt-4">
  <?php foreach ($stats as $s): ?>
    <div class="card card-pad">
      <span style="display:inline-flex;width:2.2rem;height:2.2rem;border-radius:.6rem;background:var(--royal-100);color:var(--royal-700);align-items:center;justify-content:center;"><?= icon($s['icon']) ?></span>
      <p class="text-2xl font-extrabold mt-3"><?= e((string) $s['value']) ?></p>
      <p class="text-xs font-bold text-muted" style="text-transform:uppercase;"><?= e($s['label']) ?></p>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
