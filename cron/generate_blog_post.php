<?php
/**
 * Drafts one blog post from recent education news via Claude. Always saves as a DRAFT — never
 * publishes automatically; an admin reviews and publishes from Admin -> Blog. Run this on
 * whatever schedule you want posts drafted (daily/weekly):
 *   - cPanel Cron Jobs: `php /home/USER/public_html/cron/generate_blog_post.php`, or
 *   - "curl a URL" style cron: curl -s "https://yoursite.com/cron/generate_blog_post.php?key=YOUR_SECRET"
 * The secret is the same one shown in Admin -> Blog / Admin -> Email Drip Campaigns and is
 * required for the HTTP form so random visitors can't trigger it and burn through your API quota.
 * Does nothing (and costs nothing) unless an Anthropic API key is configured and enabled in
 * Admin -> Blog.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/blog_ai.php';

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: text/plain');
    $providedKey = $_GET['key'] ?? '';
    if (!$providedKey || !hash_equals(get_cron_secret(), $providedKey)) {
        http_response_code(403);
        exit('Forbidden: missing or invalid key.');
    }
}

$result = generate_blog_post_draft();
echo strtoupper($result['status']) . ': ' . $result['message'] . ' (' . date('Y-m-d H:i:s') . ')' . PHP_EOL;
