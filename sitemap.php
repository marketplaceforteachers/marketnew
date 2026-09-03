<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = site_origin();

// Matches each page's own <link rel="canonical"> — the homepage's is '/', not '/index.php' (see
// index.php), so the sitemap and each page's own canonical tag agree on the one indexable URL.
$staticPages = [
    '/' => '1.0',
    '/browse.php' => '0.9',
    '/blog.php' => '0.7',
    '/how-it-works.php' => '0.5',
    '/wishlists.php' => '0.5',
    '/campaigns.php' => '0.5',
    '/terms.php' => '0.3',
    '/privacy.php' => '0.3',
];

$categories = db()->query('SELECT slug FROM categories ORDER BY name')->fetchAll();
$listings = db()->query("SELECT slug, created_at FROM listings WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5000")->fetchAll();
$blogPosts = db()->query("SELECT slug, published_at, updated_at FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC LIMIT 2000")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <?php foreach ($staticPages as $path => $priority): ?>
  <url><loc><?= e($base . $path) ?></loc><changefreq>daily</changefreq><priority><?= $priority ?></priority></url>
  <?php endforeach; ?>
  <?php foreach ($categories as $c): ?>
  <url><loc><?= e($base . '/browse.php?category=' . urlencode($c['slug'])) ?></loc><changefreq>daily</changefreq><priority>0.8</priority></url>
  <?php endforeach; ?>
  <?php foreach ($listings as $l): ?>
  <url>
    <loc><?= e($base . '/listing.php?slug=' . urlencode($l['slug'])) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($l['created_at'])) ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.6</priority>
  </url>
  <?php endforeach; ?>
  <?php foreach ($blogPosts as $b): ?>
  <url>
    <loc><?= e($base . '/blog-post.php?slug=' . urlencode($b['slug'])) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($b['updated_at'] ?: $b['published_at'])) ?></lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>
  <?php endforeach; ?>
</urlset>
