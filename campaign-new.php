<?php
require_once __DIR__ . '/includes/bootstrap.php';
$me = require_role('teacher', 'admin');
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $title = trim(post('title'));
    $targetFunds = (float) post('target_funds');
    if (strlen($title) < 3 || $targetFunds <= 0) {
        $error = 'Please enter a title and a funding goal greater than $0.';
    } else {
        db()->prepare('INSERT INTO fundraising_campaigns (teacher_id, title, story, target_funds) VALUES (?, ?, ?, ?)')
            ->execute([$me['id'], $title, trim(post('story')), $targetFunds]);
        redirect('/campaign.php?id=' . db()->lastInsertId());
    }
}

$page_title = 'Start a Campaign';
require __DIR__ . '/includes/layout_header.php';
?>
<div class="container-sm py-8">
  <h1 class="text-xl">Start a Fundraising Campaign</h1>
  <p class="text-sm text-muted mt-1">Rally donors behind a classroom project or book drive.</p>
  <form method="post" class="card card-pad mt-4">
    <?= csrf_field() ?>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <div class="field"><label>Title</label><input type="text" name="title" required></div>
    <div class="field"><label>Your story</label><textarea name="story" rows="5"></textarea></div>
    <div class="field"><label>Funding goal ($)</label><input type="number" name="target_funds" min="1" step="0.01" required></div>
    <button class="btn btn-amber w-full mt-2" style="justify-content:center;">Publish Campaign</button>
  </form>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
