<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/donations.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$paymentIntentId = $input['paymentIntentId'] ?? '';

try {
    $intent = stripe_retrieve_payment_intent($paymentIntentId);
    if (($intent['status'] ?? '') !== 'succeeded') json_response(['error' => 'Payment not completed'], 402);
    $meta = $intent['metadata'];
    record_donation((int) $meta['campaignId'], $intent['amount'] / 100, $meta['donorName'], $meta['donorEmail']);
    json_response(['status' => 'recorded']);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 503);
}
