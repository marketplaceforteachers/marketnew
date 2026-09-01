<?php
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$staticPages = ['/index.php', '/browse.php', '/how-it-works.php', '/wishlists.php', '/campaigns.php', '/terms.php', '/privacy.php'];

$categories = db()->query('SELECT slug FROM categories ORDER BY name')->fetchAll();
$listings = db()->query("SELECT slug, created_at FROM listings WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5000")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <?php foreach ($staticPages as $path): ?>
  <url><loc><?= e($base . $path) ?></loc><changefreq>daily</changefreq></url>
  <?php endforeach; ?>
  <?php foreach ($categories as $c): ?>
  <url><loc><?= e($base . '/browse.php?category=' . urlencode($c['slug'])) ?></loc><changefreq>daily</changefreq></url>
  <?php endforeach; ?>
  <?php foreach ($listings as $l): ?>
  <url>
    <loc><?= e($base . '/listing.php?slug=' . urlencode($l['slug'])) ?></loc>
    <lastmod><?= date('Y-m-d', strtotime($l['created_at'])) ?></lastmod>
    <changefreq>weekly</changefreq>
  </url>
  <?php endforeach; ?>
</urlset>
