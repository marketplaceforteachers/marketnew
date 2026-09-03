<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/paypal.php';
require_once __DIR__ . '/../../includes/orders.php';
header('Content-Type: application/json');

$me = current_user();
if (!$me) json_response(['error' => 'Not authenticated'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$paypalOrderId = $input['paypalOrderId'] ?? '';
if (!$paypalOrderId) json_response(['error' => 'Missing paypalOrderId'], 400);

try {
    $capture = paypal_capture_order($paypalOrderId);
    if (($capture['status'] ?? '') !== 'COMPLETED') json_response(['error' => 'PayPal payment was not completed'], 402);

    // Which order this paid, and for how much, come ONLY from PayPal's own response — never from
    // client input. custom_id was set server-side when the PayPal order was created
    // (paypal_order.php), so it can't be swapped for a different order the way a client-supplied
    // orderId could; the captured amount is what PayPal actually processed, not whatever the
    // client claims the order costs. This closes a real bypass: without this, a $1 real PayPal
    // payment could be replayed against a $999 order and mark it paid off a single small charge.
    $extracted = paypal_extract_capture($capture);
    $orderId = (int) ($extracted['customId'] ?? 0);
    if (!$orderId) json_response(['error' => 'PayPal order has no linked internal order'], 500);

    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order || (int) $order['buyer_id'] !== (int) $me['id']) json_response(['error' => 'Order not found'], 404);

    $capturedAmount = $extracted['amount'];
    if ($capturedAmount === null || abs($capturedAmount - (float) $order['total_amount']) > 0.01) {
        json_response(['error' => 'Captured amount does not match order total'], 409);
    }

    finalize_order_payment($orderId, $extracted['captureId'] ?? $paypalOrderId, $capturedAmount);
    json_response(['status' => 'paid']);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 503);
}
