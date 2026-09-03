<?php
require_once __DIR__ . '/includes/bootstrap.php';

$slug = param('slug');
$stmt = db()->prepare("SELECT * FROM blog_posts WHERE slug = ? AND status = 'published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $page_title = 'Not Found';
    $page_noindex = true;
    require __DIR__ . '/includes/layout_header.php';
    echo '<div class="container py-10 text-center"><p>Post not found.</p></div>';
    require __DIR__ . '/includes/layout_footer.php';
    exit;
}

$branding = get_setting('branding');
$page_title = $post['title'];
$page_description = $post['excerpt'] ?: blog_plain_excerpt($post['content']);
$page_image = $post['cover_image_url'] ?: null;
$page_canonical = build_canonical_url();

$siteHost = site_origin();
$page_jsonld = [
    array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $page_description,
        'image' => $post['cover_image_url'] ?: null,
        'datePublished' => date('c', strtotime($post['published_at'])),
        'dateModified' => date('c', strtotime($post['updated_at'])),
        'author' => ['@type' => 'Organization', 'name' => $post['author_name']],
        'publisher' => array_filter([
            '@type' => 'Organization',
            'name' => $branding['siteName'],
            'logo' => $branding['logoUrl'] ? ['@type' => 'ImageObject', 'url' => $branding['logoUrl']] : null,
        ]),
        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $page_canonical],
    ]),
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $siteHost . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $siteHost . '/blog.php'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title']],
        ],
    ],
];
require __DIR__ . '/includes/layout_header.php';

$related = db()->query(
    "SELECT title, slug, cover_image_url, published_at FROM blog_posts
     WHERE status = 'published' AND id != " . (int) $post['id'] . " ORDER BY published_at DESC LIMIT 3"
)->fetchAll();
?>
<article class="container-sm py-8">
  <p class="text-xs text-muted"><a href="/blog.php" class="link">&larr; Blog</a></p>
  <h1 class="text-2xl mt-3"><?= e($post['title']) ?></h1>
  <p class="text-sm text-muted mt-2">By <?= e($post['author_name']) ?> &middot; <?= e(date('F j, Y', strtotime($post['published_at']))) ?></p>

  <?php if ($post['cover_image_url']): ?>
    <div class="listing-img mt-5" style="aspect-ratio:16/9;border-radius:.8rem;">
      <img src="<?= e($post['cover_image_url']) ?>" alt="<?= e($post['title']) ?>">
    </div>
  <?php endif; ?>

  <div class="blog-content mt-6" style="font-size:.95rem;line-height:1.7;">
    <?= render_blog_markdown($post['content']) ?>
  </div>

  <?php if ($post['source'] === 'ai_generated' && $post['source_url']): ?>
    <p class="text-xs text-muted mt-6" style="border-top:1px solid var(--border);padding-top:1rem;">
      Drafted with reference to <a href="<?= e($post['source_url']) ?>" class="link" rel="noopener noreferrer" target="_blank">this reporting</a>, reviewed and published by the <?= e($branding['siteName']) ?> team.
    </p>
  <?php endif; ?>
</article>

<?php if ($related): ?>
<div class="container-sm py-8" style="border-top:1px solid var(--border);">
  <h2 class="text-lg">More from the blog</h2>
  <div class="stack mt-3">
    <?php foreach ($related as $r): ?>
      <a href="/blog-post.php?slug=<?= e($r['slug']) ?>" class="link text-sm" style="display:block;"><?= e($r['title']) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/layout_footer.php'; ?>
