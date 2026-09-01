<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role('teacher', 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'toggle_active') {
    verify_csrf();
    $id = (int) post('id');
    $stmt = db()->prepare('SELECT seller_id FROM listings WHERE id = ?');
    $stmt->execute([$id]);
    if (($row = $stmt->fetch()) && (int) $row['seller_id'] === (int) $me['id']) {
        db()->prepare('UPDATE listings SET is_active = NOT is_active WHERE id = ?')->execute([$id]);
    }
    redirect('/seller-dashboard.php');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_bio') {
    verify_csrf();
    $bio = trim(post('bio'));
    db()->prepare('UPDATE users SET bio = ? WHERE id = ?')->execute([$bio !== '' ? $bio : null, $me['id']]);
    flash('success', 'Store profile updated.');
    redirect('/seller-dashboard.php');
}

$stmt = db()->prepare('SELECT * FROM listings WHERE seller_id = ? ORDER BY created_at DESC');
$stmt->execute([$me['id']]);
$listings = $stmt->fetchAll();

$totalViews = array_sum(array_column($listings, 'view_count'));
$activeCount = count(array_filter($listings, fn($l) => $l['is_active']));

$page_title = 'Seller Dashboard';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-md py-8">
  <div class="flex justify-between items-center">
    <h1 class="text-xl flex items-center gap-2"><?= icon('layout-grid') ?> Seller Dashboard</h1>
    <div class="flex gap-2">
      <a href="/seller.php?id=<?= (int) $me['id'] ?>" class="btn btn-outline"><?= icon('external-link') ?> View My Store Page</a>
      <a href="/post-listing.php" class="btn btn-red"><?= icon('plus') ?> Post Listing</a>
    </div>
  </div>

  <div class="card card-pad mt-4">
    <h2 class="text-lg flex items-center gap-2"><?= icon('user-group') ?> Store Profile</h2>
    <p class="text-sm text-muted mt-1">This bio shows on your public store page for buyers to see.</p>
    <form method="post" class="mt-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="update_bio">
      <textarea name="bio" rows="3" placeholder="Tell buyers a bit about you and what you sell..."><?= e($me['bio'] ?? '') ?></textarea>
      <button class="btn btn-primary mt-2">Save Bio</button>
    </form>
  </div>

  <div class="grid grid-3 mt-4">
    <div class="card card-pad">
      <p class="text-2xl font-extrabold"><?= count($listings) ?></p>
      <p class="text-xs font-bold text-muted" style="text-transform:uppercase;">Total Listings</p>
    </div>
    <div class="card card-pad">
      <p class="text-2xl font-extrabold"><?= $activeCount ?></p>
      <p class="text-xs font-bold text-muted" style="text-transform:uppercase;">Active Listings</p>
    </div>
    <div class="card card-pad">
      <p class="text-2xl font-extrabold"><?= $totalViews ?></p>
      <p class="text-xs font-bold text-muted" style="text-transform:uppercase;">Total Views</p>
    </div>
  </div>

  <div class="card card-pad mt-4">
    <h2 class="text-lg flex items-center gap-2"><?= icon('wallet') ?> Payouts</h2>
    <p class="text-sm text-muted mt-1">Connect a Stripe account so we can pay you out after each sale.</p>
    <?php if ($me['stripe_account_id']): ?>
      <p class="text-xs mt-2" style="color:var(--emerald-600);font-weight:700;">Stripe account connected.</p>
    <?php endif; ?>
    <p id="connect-error" class="flash flash-error hidden mt-2"></p>
    <button id="connect-btn" class="btn btn-primary mt-2">Connect Payouts</button>
  </div>

  <h2 class="text-lg mt-6">Your listings</h2>
  <div class="table-wrap mt-2">
    <table>
      <thead><tr><th>Listing</th><th>Price</th><th>Views</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (!$listings): ?><tr><td colspan="4" class="text-muted">No listings yet.</td></tr><?php endif; ?>
        <?php foreach ($listings as $l): ?>
          <tr>
            <td><a href="/listing.php?slug=<?= e($l['slug']) ?>" class="link"><?= e($l['title']) ?></a></td>
            <td class="text-muted"><?= $l['price'] == 0 ? 'Free' : money((float) $l['price']) ?></td>
            <td class="text-muted"><?= (int) $l['view_count'] ?></td>
            <td>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="id" value="<?= $l['id'] ?>">
                <button type="submit" class="status-badge <?= $l['is_active'] ? 'status-active' : 'status-inactive' ?>" style="border:none;cursor:pointer;"><?= $l['is_active'] ? 'Active' : 'Inactive' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
document.getElementById('connect-btn').addEventListener('click', async () => {
  const res = await fetch('/api/ajax/stripe_connect_onboard.php', { method: 'POST' });
  const data = await res.json();
  if (!res.ok) { const err = document.getElementById('connect-error'); err.textContent = data.error; err.classList.remove('hidden'); return; }
  window.location.href = data.url;
});
</script>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
