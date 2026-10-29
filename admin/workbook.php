<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/workbook_default.php';
$me = require_admin();

/** Saved chapters, or the starting chapters if nobody has edited the workbook yet. */
function workbook_chapters(): array
{
    $stmt = db()->prepare("SELECT value_json FROM site_settings WHERE setting_key = 'admin_workbook'");
    $stmt->execute();
    $raw = $stmt->fetchColumn();
    $saved = $raw ? json_decode($raw, true) : null;
    return is_array($saved) && $saved ? $saved : workbook_default_chapters();
}

function workbook_save(array $chapters): void
{
    // Kept private (is_public = 0): this is internal guidance, not site content.
    db()->prepare(
        "INSERT INTO site_settings (setting_key, value_json, is_public) VALUES ('admin_workbook', ?, 0)
         ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)"
    )->execute([json_encode(array_values($chapters), JSON_INVALID_UTF8_SUBSTITUTE)]);
}

$chapters = workbook_chapters();
$ids = array_column($chapters, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $id = post('id');
    $index = array_search($id, $ids, true);

    if ($action === 'save' && $index !== false) {
        $title = trim(post('title'));
        $chapters[$index]['title'] = $title !== '' ? mb_substr($title, 0, 120) : $chapters[$index]['title'];
        $chapters[$index]['summary'] = mb_substr(trim(post('summary')), 0, 240);
        $chapters[$index]['body'] = str_replace("\r\n", "\n", (string) post('body'));
        $chapters[$index]['updated_at'] = date('Y-m-d');
        $chapters[$index]['updated_by'] = $me['name'];
        workbook_save($chapters);
        log_admin_action((int) $me['id'], 'workbook.edit', 'workbook', $id);
        flash('success', 'Chapter saved.');
    } elseif ($action === 'restore' && $index !== false) {
        $original = array_values(array_filter(workbook_default_chapters(), fn($c) => $c['id'] === $id))[0] ?? null;
        if ($original) {
            $chapters[$index] = $original;
            workbook_save($chapters);
            log_admin_action((int) $me['id'], 'workbook.restore', 'workbook', $id);
            flash('success', 'Chapter restored to the original wording.');
        }
    } elseif ($action === 'add') {
        $id = 'custom-' . bin2hex(random_bytes(4));
        $chapters[] = ['id' => $id, 'title' => 'New chapter', 'summary' => '', 'body' => "## A heading\n\nWrite your notes here."];
        workbook_save($chapters);
        log_admin_action((int) $me['id'], 'workbook.add', 'workbook', $id);
        redirect('/admin/workbook.php?chapter=' . urlencode($id) . '&edit=1');
    } elseif ($action === 'delete' && $index !== false && str_starts_with($id, 'custom-')) {
        array_splice($chapters, $index, 1);
        workbook_save($chapters);
        log_admin_action((int) $me['id'], 'workbook.delete', 'workbook', $id);
        flash('success', 'Chapter deleted.');
        redirect('/admin/workbook.php');
    }
    redirect('/admin/workbook.php?chapter=' . urlencode($id));
}

$currentId = $_GET['chapter'] ?? ($chapters[0]['id'] ?? '');
$current = array_values(array_filter($chapters, fn($c) => $c['id'] === $currentId))[0] ?? $chapters[0];
$editing = !empty($_GET['edit']);
$isDefault = !str_starts_with($current['id'], 'custom-');
$printAll = !empty($_GET['print']);

$page_title = 'Workbook';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<div class="flex justify-between items-center no-print" style="flex-wrap:wrap;gap:.75rem;">
  <div>
    <span class="section-eyebrow">Admin workbook</span>
    <h1 class="section-title mt-1">How we run <em class="serif-accent">the marketplace</em></h1>
    <p class="text-sm text-muted mt-1">Step-by-step notes for every part of the back office. Edit anything so it matches how you work.</p>
  </div>
  <div class="flex gap-2">
    <a href="/admin/workbook.php?print=1" class="btn btn-outline"><?= icon('book') ?> Print the whole workbook</a>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add"><button class="btn btn-primary"><?= icon('plus-circle') ?> Add a chapter</button></form>
  </div>
</div>

<?php if ($printAll): ?>
  <div class="workbook-print">
    <p class="no-print mt-4"><button class="btn btn-primary" onclick="window.print()">Print / save as PDF</button> <a class="link text-sm" href="/admin/workbook.php" style="margin-left:.75rem;">Back to the workbook</a></p>
    <?php foreach ($chapters as $i => $c): ?>
      <section class="card card-pad mt-4 workbook-chapter">
        <span class="section-eyebrow">Chapter <?= $i + 1 ?></span>
        <h2 class="section-title mt-1"><?= e($c['title']) ?></h2>
        <?php if (!empty($c['summary'])): ?><p class="text-sm text-muted mt-1"><?= e($c['summary']) ?></p><?php endif; ?>
        <div class="blog-content mt-4"><?= render_blog_markdown($c['body']) ?></div>
      </section>
    <?php endforeach; ?>
  </div>
<?php else: ?>
<div class="workbook-layout mt-6">
  <nav class="workbook-toc card">
    <?php foreach ($chapters as $i => $c): ?>
      <a href="/admin/workbook.php?chapter=<?= e(urlencode($c['id'])) ?>" class="<?= $c['id'] === $current['id'] ? 'active' : '' ?>">
        <span class="workbook-num"><?= $i + 1 ?></span>
        <span><strong><?= e($c['title']) ?></strong><?php if (!empty($c['summary'])): ?><small><?= e($c['summary']) ?></small><?php endif; ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <article class="card card-pad workbook-chapter">
    <?php if ($editing): ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($current['id']) ?>">
        <div class="field"><label>Chapter title</label><input type="text" name="title" maxlength="120" required value="<?= e($current['title']) ?>"></div>
        <div class="field"><label>One-line summary</label><input type="text" name="summary" maxlength="240" value="<?= e($current['summary'] ?? '') ?>"></div>
        <div class="field">
          <label>Chapter text</label>
          <textarea name="body" rows="22" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85rem;line-height:1.6;"><?= e($current['body']) ?></textarea>
          <p class="text-xs text-muted">Start a line with <code>## </code> for a heading or <code>- </code> for a bullet. Use **double asterisks** for bold. Leave a blank line between paragraphs.</p>
        </div>
        <div class="flex gap-2">
          <button class="btn btn-primary">Save chapter</button>
          <a class="btn btn-outline" href="/admin/workbook.php?chapter=<?= e(urlencode($current['id'])) ?>">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <div class="flex justify-between items-center no-print" style="flex-wrap:wrap;gap:.5rem;">
        <span class="section-eyebrow">Chapter <?= array_search($current['id'], array_column($chapters, 'id'), true) + 1 ?> of <?= count($chapters) ?></span>
        <div class="flex gap-2 items-center">
          <a class="btn btn-outline" href="/admin/workbook.php?chapter=<?= e(urlencode($current['id'])) ?>&amp;edit=1">Edit</a>
          <button class="btn btn-outline" onclick="window.print()"><?= icon('download') ?> Print</button>
        </div>
      </div>
      <h2 class="section-title mt-2"><?= e($current['title']) ?></h2>
      <?php if (!empty($current['summary'])): ?><p class="text-sm text-muted mt-1"><?= e($current['summary']) ?></p><?php endif; ?>
      <div class="blog-content mt-4"><?= render_blog_markdown($current['body']) ?></div>
      <div class="flex justify-between items-center mt-6 no-print" style="flex-wrap:wrap;gap:.5rem;border-top:1px solid var(--border);padding-top:1rem;">
        <p class="text-xs text-muted">
          <?php if (!empty($current['updated_at'])): ?>Last edited <?= e($current['updated_at']) ?><?= !empty($current['updated_by']) ? ' by ' . e($current['updated_by']) : '' ?>.<?php else: ?>Original wording.<?php endif; ?>
        </p>
        <?php if ($isDefault && !empty($current['updated_at'])): ?>
          <form method="post" onsubmit="return confirm('Replace your edits with the original wording?');"><?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="id" value="<?= e($current['id']) ?>"><button class="link text-xs" style="background:none;border:none;cursor:pointer;">Restore the original chapter</button></form>
        <?php elseif (!$isDefault): ?>
          <form method="post" onsubmit="return confirm('Delete this chapter?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($current['id']) ?>"><button class="link text-xs" style="background:none;border:none;cursor:pointer;color:var(--red-600);">Delete chapter</button></form>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </article>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
