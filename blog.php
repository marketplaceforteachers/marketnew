<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page_title = 'Classroom & Teaching Blog';
$page_description = 'Tips, classroom ideas, and education news for teachers — from the ' . get_setting('branding')['siteName'] . ' team.';
$page_canonical = build_canonical_url();
$page_jsonld = [[
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_origin() . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog'],
    ],
]];
require __DIR__ . '/includes/layout_header.php';

$posts = db()->query(
    "SELECT id, title, slug, excerpt, content, cover_image_url, author_name, published_at
     FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 30"
)->fetchAll();
?>
<div class="section-tint py-8">
  <div class="container">
    <span class="section-eyebrow">From the team</span>
    <h1 class="text-2xl mt-1">Classroom &amp; Teaching Blog</h1>
    <p class="text-sm text-muted mt-2" style="max-width:46rem;">Tips, classroom ideas, and education news for teachers.</p>
  </div>
</div>

<div class="container py-8">
  <?php if (!$posts): ?>
    <p class="text-sm text-muted">No posts yet — check back soon.</p>
  <?php endif; ?>
  <div class="listing-grid">
    <?php foreach ($posts as $p): ?>
      <a href="/blog-post.php?slug=<?= e($p['slug']) ?>" class="card" style="display:flex;flex-direction:column;">
        <div class="listing-img" style="aspect-ratio:16/9;">
          <?php if ($p['cover_image_url']): ?>
            <img src="<?= e($p['cover_image_url']) ?>" alt="<?= e($p['title']) ?>">
          <?php else: ?>
            <span class="listing-img-placeholder"><span class="ph-badge"><?= icon('image') ?></span></span>
          <?php endif; ?>
        </div>
        <div class="listing-body">
          <span class="listing-eyebrow"><span class="listing-eyebrow-dot"></span><?= e(date('M j, Y', strtotime($p['published_at']))) ?></span>
          <p class="listing-title" style="font-size:1rem;"><?= e($p['title']) ?></p>
          <p class="text-xs text-muted"><?= e($p['excerpt'] ?: blog_plain_excerpt($p['content'], 120)) ?></p>
          <p class="text-xs text-muted mt-1">By <?= e($p['author_name']) ?></p>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
