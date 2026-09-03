<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/blog_ai.php';
require_once __DIR__ . '/../includes/uploads.php';
$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'save_ai_settings') {
        $current = get_anthropic_config();
        $submittedKey = post('apiKey');
        set_anthropic_config([
            'apiKey' => str_contains($submittedKey, '•') ? $current['apiKey'] : trim($submittedKey),
            'isEnabled' => post('isEnabled') === '1',
            'model' => trim(post('model')) ?: 'claude-sonnet-5',
            'topics' => trim(post('topics')) ?: 'K-12 classroom teaching',
        ]);
        log_admin_action($me['id'], 'integration.update', 'integration_configs', 'anthropic');
        flash('success', 'AI auto-writer settings saved.');
        redirect('/admin/blog.php');
    }

    if ($action === 'generate_now') {
        $result = generate_blog_post_draft();
        flash($result['status'] === 'created' ? 'success' : 'error', $result['message']);
        redirect('/admin/blog.php');
    }

    if ($action === 'upload_content_image') {
        $id = (int) post('id');
        $result = handle_image_upload($_FILES['content_image'] ?? [], 'blog');
        $backTo = $id ? "/admin/blog.php?edit=$id" : '/admin/blog.php?new=1';
        if ($result['ok']) {
            flash('success', 'Uploaded — copy the snippet below into your content.');
            redirect($backTo . '&uploaded=' . urlencode($result['url']));
        }
        flash('error', $result['error']);
        redirect($backTo);
    }

    if ($action === 'save_post') {
        $id = (int) post('id');
        $title = trim(post('title'));
        $slug = trim(post('slug'));
        $excerpt = trim(post('excerpt'));
        $content = post('content');
        $coverImageUrl = trim(post('cover_image_url')) ?: null;
        if (!empty($_FILES['cover_image_file']['name'])) {
            $result = handle_image_upload($_FILES['cover_image_file'], 'blog');
            if ($result['ok']) {
                $coverImageUrl = $result['url'];
            } else {
                flash('error', 'Cover image: ' . $result['error']);
                redirect($id ? "/admin/blog.php?edit=$id" : '/admin/blog.php?new=1');
            }
        }
        $authorName = trim(post('author_name')) ?: 'MarketplaceForTeachers.com Team';
        $status = in_array(post('status'), ['draft', 'published'], true) ? post('status') : 'draft';

        if (strlen($title) < 3 || strlen($content) < 20) {
            flash('error', 'Title and content are required.');
            redirect($id ? "/admin/blog.php?edit=$id" : '/admin/blog.php?new=1');
        }
        if ($slug === '') {
            $slug = slugify($title);
        } else {
            $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower($slug));
        }

        if ($id) {
            $stmt = db()->prepare('SELECT status, published_at FROM blog_posts WHERE id = ?');
            $stmt->execute([$id]);
            $existing = $stmt->fetch();
            $publishedAt = $existing['published_at'];
            if ($status === 'published' && !$publishedAt) {
                $publishedAt = date('Y-m-d H:i:s');
            }
            db()->prepare(
                'UPDATE blog_posts SET title=?, slug=?, excerpt=?, content=?, cover_image_url=?, author_name=?, status=?, published_at=? WHERE id=?'
            )->execute([$title, $slug, $excerpt, $content, $coverImageUrl, $authorName, $status, $publishedAt, $id]);
            log_admin_action($me['id'], 'blog_post.update', 'blog_posts', $id);
        } else {
            $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
            db()->prepare(
                'INSERT INTO blog_posts (title, slug, excerpt, content, cover_image_url, author_name, status, source, published_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'manual\', ?)'
            )->execute([$title, $slug, $excerpt, $content, $coverImageUrl, $authorName, $status, $publishedAt]);
            $id = (int) db()->lastInsertId();
            log_admin_action($me['id'], 'blog_post.create', 'blog_posts', $id);
        }
        flash('success', 'Post saved.');
        redirect('/admin/blog.php');
    }

    if ($action === 'toggle_status') {
        $id = (int) post('id');
        $stmt = db()->prepare('SELECT status, published_at FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if ($post) {
            $newStatus = $post['status'] === 'published' ? 'draft' : 'published';
            $publishedAt = $post['published_at'] ?: ($newStatus === 'published' ? date('Y-m-d H:i:s') : null);
            db()->prepare('UPDATE blog_posts SET status = ?, published_at = ? WHERE id = ?')->execute([$newStatus, $publishedAt, $id]);
            log_admin_action($me['id'], 'blog_post.' . $newStatus, 'blog_posts', $id);
        }
        redirect('/admin/blog.php');
    }

    if ($action === 'delete_post') {
        $id = (int) post('id');
        db()->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([$id]);
        log_admin_action($me['id'], 'blog_post.delete', 'blog_posts', $id);
        redirect('/admin/blog.php');
    }
}

$editingId = (int) ($_GET['edit'] ?? 0);
$isNew = isset($_GET['new']);
$editingPost = null;
if ($editingId) {
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([$editingId]);
    $editingPost = $stmt->fetch();
}

$posts = db()->query(
    "SELECT id, title, slug, status, source, published_at, created_at FROM blog_posts ORDER BY created_at DESC LIMIT 200"
)->fetchAll();
$draftCount = count(array_filter($posts, fn($p) => $p['status'] === 'draft'));
$anthropic = get_anthropic_config();

$page_title = 'Blog';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<h1 class="text-xl">Blog</h1>
<p class="text-sm text-muted mt-1">Write posts yourself, or let the AI auto-writer draft one from recent education news — either way, nothing goes live until you publish it here.</p>

<?php if ($isNew || $editingPost): ?>
  <div class="card card-pad mt-4">
    <h2 class="text-lg"><?= $editingPost ? 'Edit Post' : 'New Post' ?></h2>

    <form method="post" enctype="multipart/form-data" class="flex gap-2 mt-3" style="align-items:center;flex-wrap:wrap;background:var(--surface-2);padding:.75rem;border-radius:var(--radius);">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload_content_image">
      <input type="hidden" name="id" value="<?= (int) ($editingPost['id'] ?? 0) ?>">
      <span class="text-xs font-bold text-muted">Upload an image to use in the content below:</span>
      <input type="file" name="content_image" accept="image/jpeg,image/png,image/webp" required style="flex:1;min-width:12rem;">
      <button type="submit" class="btn btn-outline" style="padding:.4rem .8rem;font-size:.8rem;">Upload</button>
    </form>

    <?php if (!empty($_GET['uploaded'])): ?>
      <div class="card card-pad mt-2" style="background:var(--emerald-50);border-color:var(--emerald-100);">
        <p class="text-sm font-bold" style="color:var(--emerald-700);">Image uploaded — copy this into your content where you want it to appear:</p>
        <input type="text" readonly onclick="this.select()" value="![](<?= e($_GET['uploaded']) ?>)" class="mt-2" style="font-family:monospace;">
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mt-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_post">
      <input type="hidden" name="id" value="<?= (int) ($editingPost['id'] ?? 0) ?>">
      <div class="field"><label>Title</label><input type="text" name="title" required value="<?= e($editingPost['title'] ?? '') ?>"></div>
      <div class="field"><label>URL slug <span class="text-muted" style="text-transform:none;font-weight:400;">(leave blank to auto-generate)</span></label><input type="text" name="slug" value="<?= e($editingPost['slug'] ?? '') ?>"></div>
      <div class="field"><label>Excerpt <span class="text-muted" style="text-transform:none;font-weight:400;">(used in previews and search results)</span></label><textarea name="excerpt" rows="2"><?= e($editingPost['excerpt'] ?? '') ?></textarea></div>
      <div class="field">
        <label>Content</label>
        <p class="text-xs text-muted" style="text-transform:none;font-weight:400;margin-bottom:.35rem;">Blank lines start a new paragraph. "## Heading", "- bullet", **bold**, *italic*, [link text](https://...), ![alt text](https://...) images are supported — no other HTML.</p>
        <textarea name="content" rows="16" required><?= e($editingPost['content'] ?? '') ?></textarea>
      </div>
      <div class="grid grid-2">
        <div class="field"><label>Cover image URL <span class="text-muted" style="text-transform:none;font-weight:400;">(optional)</span></label><input type="text" name="cover_image_url" value="<?= e($editingPost['cover_image_url'] ?? '') ?>"></div>
        <div class="field"><label>Author name</label><input type="text" name="author_name" value="<?= e($editingPost['author_name'] ?? 'MarketplaceForTeachers.com Team') ?>"></div>
      </div>
      <div class="field"><label>...or upload a cover image <span class="text-muted" style="text-transform:none;font-weight:400;">(replaces the URL above if both are set)</span></label><input type="file" name="cover_image_file" accept="image/jpeg,image/png,image/webp"></div>
      <div class="field">
        <label>Status</label>
        <select name="status">
          <option value="draft" <?= ($editingPost['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
          <option value="published" <?= ($editingPost['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
        </select>
      </div>
      <div class="flex gap-2 mt-2">
        <button class="btn btn-primary">Save</button>
        <a href="/admin/blog.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
<?php else: ?>

  <div class="card card-pad mt-4">
    <h2 class="text-lg flex items-center gap-2"><?= icon('sparkles') ?> AI Auto-Writer</h2>
    <p class="text-sm text-muted mt-1">Searches recent education news (free, no key needed) and drafts a post with Claude. Every draft lands below with <strong>Draft</strong> status — nothing is ever posted automatically without you clicking Publish.</p>

    <form method="post" class="mt-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_ai_settings">
      <p class="text-xs" style="color:<?= $anthropic['apiKey'] ? 'var(--emerald-600)' : 'var(--slate-400)' ?>;font-weight:700;"><?= $anthropic['apiKey'] ? 'API key on file' : 'Not configured yet' ?></p>
      <div class="grid grid-2">
        <div class="field"><label>Anthropic API key</label><input type="text" name="apiKey" placeholder="sk-ant-..." value="<?= e($anthropic['apiKey'] ? mask_secret($anthropic['apiKey']) : '') ?>"></div>
        <div class="field"><label>Model</label><input type="text" name="model" value="<?= e($anthropic['model']) ?>"></div>
      </div>
      <div class="field"><label>Topics <span class="text-muted" style="text-transform:none;font-weight:400;">(comma-separated — one is picked at random each time)</span></label><input type="text" name="topics" value="<?= e($anthropic['topics']) ?>"></div>
      <label class="checkbox-field mt-2"><input type="checkbox" name="isEnabled" value="1" <?= $anthropic['isEnabled'] ? 'checked' : '' ?>> Enabled</label>
      <div class="flex gap-2 mt-3">
        <button class="btn btn-primary">Save Settings</button>
      </div>
    </form>

    <form method="post" class="mt-3" style="border-top:1px solid var(--border);padding-top:1rem;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="generate_now">
      <button class="btn btn-amber"><?= icon('zap') ?> Generate a Draft Now</button>
      <p class="text-xs text-muted mt-2">For a scheduled daily/weekly run instead of clicking this manually, point a cron job at <code>cron/generate_blog_post.php?key=<?= e(get_cron_secret()) ?></code> (same pattern as your email drip cron) — see the README for exact cPanel steps.</p>
    </form>
  </div>

  <div class="flex justify-between items-center mt-6">
    <h2 class="text-lg">All Posts <?php if ($draftCount): ?><span class="status-badge status-pending" style="margin-left:.4rem;"><?= $draftCount ?> draft<?= $draftCount === 1 ? '' : 's' ?> to review</span><?php endif; ?></h2>
    <a href="/admin/blog.php?new=1" class="btn btn-primary"><?= icon('plus') ?> New Post</a>
  </div>
  <div class="table-wrap mt-3">
    <table>
      <thead><tr><th>Title</th><th>Source</th><th>Status</th><th>Date</th><th></th><th></th></tr></thead>
      <tbody>
        <?php if (!$posts): ?><tr><td colspan="6" class="text-muted text-center">No posts yet.</td></tr><?php endif; ?>
        <?php foreach ($posts as $p): ?>
          <tr>
            <td class="font-bold"><?= e($p['title']) ?></td>
            <td class="text-muted"><?= $p['source'] === 'ai_generated' ? 'AI' : 'Manual' ?></td>
            <td>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="status-badge <?= $p['status'] === 'published' ? 'status-active' : 'status-pending' ?>" style="border:none;cursor:pointer;"><?= $p['status'] === 'published' ? 'Published' : 'Draft' ?></button>
              </form>
            </td>
            <td class="text-muted text-xs"><?= e(date('M j, Y', strtotime($p['published_at'] ?? $p['created_at']))) ?></td>
            <td><a href="/admin/blog.php?edit=<?= $p['id'] ?>" class="link text-xs">Edit</a></td>
            <td style="text-align:right;">
              <form method="post" onsubmit="return confirm('Delete this post?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_post"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--slate-500);"><?= icon('trash') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
