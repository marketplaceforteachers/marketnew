<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) post('id');
    if (post('action') === 'set_role') {
        $role = post('role');
        if (in_array($role, ['teacher', 'buyer', 'admin'], true)) {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
            log_admin_action($me['id'], 'user.update', 'users', $id);
        }
    } elseif (post('action') === 'toggle_verified') {
        db()->prepare('UPDATE users SET is_verified = NOT is_verified WHERE id = ?')->execute([$id]);
        log_admin_action($me['id'], 'user.update', 'users', $id);
    } elseif (post('action') === 'toggle_banned') {
        db()->prepare('UPDATE users SET is_banned = NOT is_banned WHERE id = ?')->execute([$id]);
        $stmt = db()->prepare('SELECT is_banned FROM users WHERE id = ?');
        $stmt->execute([$id]);
        // Banning a seller previously left their existing listings fully active and purchasable —
        // real payment, real payout generated for a banned account. Cascade-deactivate on ban;
        // deliberately does NOT auto-reactivate on unban (an admin un-banning someone shouldn't
        // silently re-list everything they had — items may be stale, sold elsewhere, etc.).
        if ((bool) $stmt->fetchColumn()) {
            db()->prepare("UPDATE listings SET is_active = 0 WHERE seller_id = ? AND is_active = 1")->execute([$id]);
        }
        log_admin_action($me['id'], 'user.update', 'users', $id);
    } elseif (post('action') === 'delete') {
        if ($id === (int) $me['id']) {
            flash('error', "You can't delete your own account.");
        } else {
            try {
                db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                log_admin_action($me['id'], 'user.delete', 'users', $id);
                flash('success', 'User deleted.');
            } catch (PDOException $e) {
                flash('error', 'Could not delete this user — they have existing orders, reviews, or other records tied to their account. Ban them instead to block access.');
            }
        }
    }
    redirect('/admin/users.php?q=' . urlencode(param('q')));
}

$q = param('q');
$stmt = db()->prepare("SELECT * FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT 200");
$stmt->execute(["%$q%", "%$q%"]);
$users = $stmt->fetchAll();

$page_title = 'User Manager';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">User Manager</h1>
<p class="text-sm text-muted mt-1">Change roles, verify teachers, or ban accounts.</p>

<form method="get" class="mt-3"><input type="text" name="q" placeholder="Search by name or email..." value="<?= e($q) ?>" style="max-width:20rem;"></form>

<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>User</th><th>Role</th><th>Verified</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong><br><span class="text-xs text-muted"><?= e($u['email']) ?></span></td>
          <td>
            <form method="post" onchange="this.submit()">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="set_role">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <select name="role">
                <?php foreach (['buyer', 'teacher', 'admin'] as $r): ?>
                  <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_verified"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="status-badge <?= $u['is_verified'] ? 'status-approved' : 'status-inactive' ?>" style="border:none;cursor:pointer;"><?= $u['is_verified'] ? 'Verified' : 'Unverified' ?></button>
            </form>
          </td>
          <td>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_banned"><input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="status-badge <?= $u['is_banned'] ? 'status-disputed' : 'status-inactive' ?>" style="border:none;cursor:pointer;"><?= $u['is_banned'] ? 'Banned' : 'Active' ?></button>
            </form>
          </td>
          <td style="text-align:right;">
            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
              <form method="post" onsubmit="return confirm('Permanently delete this user and everything tied to them (listings, etc.)? This cannot be undone.')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--slate-500);"><?= icon('trash') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
