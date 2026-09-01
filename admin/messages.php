<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

$threads = db()->query(
    "SELECT t.id, t.listing_id, l.title AS listing_title,
            buyerUser.name AS buyer_name, sellerUser.name AS seller_name,
            (SELECT body FROM messages m WHERE m.thread_id = t.id ORDER BY m.created_at DESC LIMIT 1) AS last_message
     FROM message_threads t
     JOIN users buyerUser ON buyerUser.id = t.buyer_id
     JOIN users sellerUser ON sellerUser.id = t.seller_id
     LEFT JOIN listings l ON l.id = t.listing_id
     ORDER BY t.created_at DESC LIMIT 300"
)->fetchAll();

$page_title = 'Messaging Oversight';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Messaging Oversight</h1>
<p class="text-sm text-muted mt-1">Read-only view of every buyer &harr; seller conversation.</p>
<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>Buyer</th><th>Seller</th><th>Listing</th><th>Last message</th></tr></thead>
    <tbody>
      <?php foreach ($threads as $t): ?>
        <tr>
          <td class="font-bold"><?= e($t['buyer_name']) ?></td>
          <td class="text-muted"><?= e($t['seller_name']) ?></td>
          <td class="text-muted"><?= e($t['listing_title'] ?? '—') ?></td>
          <td class="text-muted"><?= e($t['last_message'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
