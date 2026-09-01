<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$logs = db()->query(
    "SELECT a.*, u.name AS admin_name FROM admin_audit_logs a JOIN users u ON u.id = a.admin_id ORDER BY a.created_at DESC LIMIT 300"
)->fetchAll();

$page_title = 'Admin Audit Log';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Admin Audit Log</h1>
<p class="text-sm text-muted mt-1">Every mutating action taken from the admin console.</p>
<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>Admin</th><th>Action</th><th>Target</th><th>When</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= e($l['admin_name']) ?></td>
          <td style="font-family:monospace;color:var(--royal-700);"><?= e($l['action']) ?></td>
          <td class="text-muted"><?= e($l['target_type']) ?><?= $l['target_id'] ? ' #' . e($l['target_id']) : '' ?></td>
          <td class="text-muted"><?= e($l['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
