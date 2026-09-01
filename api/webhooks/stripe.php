<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/orders.php';

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$gateway = get_gateway('stripe');
$webhookSecret = $gateway['config']['webhookSecret'] ?? '';

if (!$webhookSecret || !stripe_verify_webhook_signature($payload, $sigHeader, $webhookSecret)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

$event = json_decode($payload, true);
if (($event['type'] ?? '') === 'payment_intent.succeeded') {
    $intent = $event['data']['object'];
    $orderId = (int) ($intent['metadata']['orderId'] ?? 0);
    if ($orderId) {
        finalize_order_payment($orderId, $intent['id'], $intent['amount'] / 100);
    }
}

header('Content-Type: application/json');
echo json_encode(['received' => true]);
