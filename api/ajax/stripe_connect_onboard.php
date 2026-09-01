<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/stripe.php';
header('Content-Type: application/json');

$me = require_role('teacher', 'admin');

try {
    $accountId = $me['stripe_account_id'];
    if (!$accountId) {
        $account = stripe_create_connect_account();
        $accountId = $account['id'];
        db()->prepare('UPDATE users SET stripe_account_id = ? WHERE id = ?')->execute([$accountId, $me['id']]);
    }
    $appUrl = defined('APP_URL') ? APP_URL : '';
    $link = stripe_create_account_link($accountId, "$appUrl/seller-dashboard.php?refresh=1", "$appUrl/seller-dashboard.php?connected=1");
    json_response(['url' => $link['url']]);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 503);
}
