<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('id');
    if (post('action') === 'toggle_active') {
        db()->prepare('UPDATE listings SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
        log_admin_action($me['id'], 'listing.toggle', 'listings', $id);
    } elseif (post('action') === 'delete') {
        db()->prepare('DELETE FROM listings WHERE id = ?')->execute([$id]);
        log_admin_action($me['id'], 'listing.delete', 'listings', $id);
    }
    redirect('/admin/listings.php');
}

$listings = db()->query(
    "SELECT l.id, l.title, l.price, l.is_active, l.created_at, u.name AS seller_name, c.name AS category_name
     FROM listings l JOIN users u ON u.id = l.seller_id JOIN categories c ON c.id = l.category_id
     ORDER BY l.created_at DESC LIMIT 300"
)->fetchAll();

$page_title = 'Listing Manager';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Listing Manager</h1>
<p class="text-sm text-muted mt-1">Moderate listings — deactivate or remove policy-violating posts.</p>
<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>Listing</th><th>Seller</th><th>Category</th><th>Price</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($listings as $l): ?>
        <tr>
          <td class="font-bold"><?= e($l['title']) ?></td>
          <td class="text-muted"><?= e($l['seller_name']) ?></td>
          <td class="text-muted"><?= e($l['category_name']) ?></td>
          <td class="text-muted"><?= money((float) $l['price']) ?></td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" class="status-badge <?= $l['is_active'] ? 'status-active' : 'status-inactive' ?>" style="border:none;cursor:pointer;"><?= $l['is_active'] ? 'Active' : 'Inactive' ?></button>
            </form>
          </td>
          <td style="text-align:right;">
            <form method="post" onsubmit="return confirm('Delete this listing?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $l['id'] ?>">
              <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--slate-500);"><?= icon('trash') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
