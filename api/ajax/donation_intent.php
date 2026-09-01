<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/stripe.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$campaignId = (int) ($input['campaignId'] ?? 0);
$amount = (float) ($input['amount'] ?? 0);
$donorName = trim($input['donorName'] ?? '');
$donorEmail = trim($input['donorEmail'] ?? '');

if ($campaignId <= 0 || $amount <= 0 || !$donorName || !$donorEmail) {
    json_response(['error' => 'Missing donation details'], 400);
}

try {
    $intent = stripe_create_payment_intent($amount, [
        'type' => 'donation', 'campaignId' => $campaignId, 'donorName' => $donorName, 'donorEmail' => $donorEmail,
    ]);
    json_response(['clientSecret' => $intent['client_secret']]);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 503);
}
