<?php
// Called client-side right after stripe.confirmPayment() resolves, as a synchronous fallback
// to the webhook (useful in local/dev environments where Stripe can't reach a webhook URL).
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/stripe.php';
require_once __DIR__ . '/../../includes/orders.php';
header('Content-Type: application/json');

$me = current_user();
if (!$me) json_response(['error' => 'Not authenticated'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$paymentIntentId = $input['paymentIntentId'] ?? '';

try {
    $intent = stripe_retrieve_payment_intent($paymentIntentId);
    if (($intent['status'] ?? '') !== 'succeeded') json_response(['error' => 'Payment not completed'], 402);
    $orderId = (int) ($intent['metadata']['orderId'] ?? 0);
    if ($orderId) {
        finalize_order_payment($orderId, $intent['id'], $intent['amount'] / 100);
    }
    json_response(['status' => 'recorded']);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 503);
}
