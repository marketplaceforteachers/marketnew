<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (post('action') === 'create') {
        $name = trim(post('name'));
        if ($name) {
            $slug = slugify($name);
            db()->prepare('INSERT INTO categories (name, slug, description, icon) VALUES (?, ?, ?, ?)')
                ->execute([$name, $slug, '', 'Boxes']);
            log_admin_action($me['id'], 'category.create', 'categories', $slug);
            flash('success', 'Category added.');
        }
    } elseif (post('action') === 'delete') {
        $id = (int) post('id');
        db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        log_admin_action($me['id'], 'category.delete', 'categories', $id);
        flash('success', 'Category deleted.');
    }
    redirect('/admin/categories.php');
}

$categories = db()->query(
    "SELECT c.*, (SELECT COUNT(*) FROM listings l WHERE l.category_id = c.id) AS listing_count
     FROM categories c ORDER BY c.name"
)->fetchAll();

$page_title = 'Category Manager';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Category Manager</h1>
<p class="text-sm text-muted mt-1">These power the public category chips and browse filters.</p>

<form method="post" class="flex gap-2 mt-4">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <input type="text" name="name" placeholder="New category name" required style="max-width:20rem;">
  <button class="btn btn-primary"><?= icon('plus') ?> Add</button>
</form>

<div class="table-wrap mt-4">
  <table>
    <thead><tr><th>Name</th><th>Slug</th><th>Listings</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td class="font-bold"><?= e($c['name']) ?></td>
          <td class="text-muted"><?= e($c['slug']) ?></td>
          <td class="text-muted"><?= $c['listing_count'] ?></td>
          <td style="text-align:right;">
            <form method="post" onsubmit="return confirm('Delete this category?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--slate-500);"><?= icon('trash') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
