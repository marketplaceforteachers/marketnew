<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role('teacher', 'admin');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim(post('title'));
    if (strlen($title) < 3) {
        $error = 'Please enter a title.';
    } else {
        db()->prepare('INSERT INTO wishlists (teacher_id, title, grade, school, goal_amount) VALUES (?, ?, ?, ?, ?)')
            ->execute([$me['id'], $title, trim(post('grade')), trim(post('school')), (float) post('goal_amount', 0)]);
        $wishlistId = (int) db()->lastInsertId();

        $names = post('item_name', []);
        $prices = post('item_price', []);
        foreach ($names as $i => $name) {
            $name = trim($name);
            if ($name === '') continue;
            db()->prepare('INSERT INTO wishlist_items (wishlist_id, item_name, price) VALUES (?, ?, ?)')
                ->execute([$wishlistId, $name, (float) ($prices[$i] ?? 0)]);
        }
        redirect('/wishlist.php?id=' . $wishlistId);
    }
}

$page_title = 'Create a Wishlist';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-xl">Create a Classroom Wishlist</h1>
  <p class="text-sm text-muted mt-1">Share a public registry donors can fund directly.</p>
  <form method="post" class="card card-pad mt-4">
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <div class="field"><label>Title</label><input type="text" name="title" required></div>
    <div class="grid grid-2">
      <div class="field"><label>Grade</label><input type="text" name="grade"></div>
      <div class="field"><label>School</label><input type="text" name="school"></div>
    </div>
    <div class="field"><label>Goal Amount ($)</label><input type="number" name="goal_amount" min="0" step="0.01"></div>

    <div class="field">
      <label>Items</label>
      <div id="items-rows-list" class="stack">
        <div class="dynamic-row flex gap-2"><input type="text" name="item_name[]" placeholder="Item name"><input type="number" name="item_price[]" placeholder="$" style="width:6rem;"></div>
      </div>
      <template id="items-rows">
        <div class="dynamic-row flex gap-2 mt-2">
          <input type="text" name="item_name[]" placeholder="Item name">
          <input type="number" name="item_price[]" placeholder="$" style="width:6rem;">
          <button type="button" data-remove-row class="btn btn-outline"><?= icon('trash') ?></button>
        </div>
      </template>
      <button type="button" class="link text-xs mt-2" data-add-row="items-rows"><?= icon('plus') ?> Add item</button>
    </div>
    <button class="btn btn-primary w-full mt-2" style="justify-content:center;">Publish Wishlist</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
