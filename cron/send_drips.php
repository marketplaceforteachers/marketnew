<?php
/**
 * Sends every email-drip step that's now due. Run this periodically:
 *   - cPanel Cron Jobs: `php /home/USER/public_html/cron/send_drips.php` (hourly is plenty), or
 *   - "curl a URL" style cron: curl -s "https://yoursite.com/cron/send_drips.php?key=YOUR_SECRET"
 * The secret is shown in Admin -> Email Drip Campaigns and is required for the HTTP form so
 * random visitors can't trigger it and burn through your Resend quota.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/drips.php';

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    header('Content-Type: text/plain');
    $providedKey = $_GET['key'] ?? '';
    if (!$providedKey || !hash_equals(get_cron_secret(), $providedKey)) {
        http_response_code(403);
        exit('Forbidden: missing or invalid key.');
    }
}

$sent = process_due_drip_steps();
echo "OK: sent $sent drip email(s) at " . date('Y-m-d H:i:s') . PHP_EOL;
