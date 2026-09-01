<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/paypal.php';
require_once __DIR__ . '/../../includes/orders.php';
header('Content-Type: application/json');

$me = current_user();
if (!$me) json_response(['error' => 'Not authenticated'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['orderId'] ?? 0);
$paypalOrderId = $input['paypalOrderId'] ?? '';

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order || (int) $order['buyer_id'] !== (int) $me['id']) json_response(['error' => 'Order not found'], 404);

try {
    $capture = paypal_capture_order($paypalOrderId);
    if (($capture['status'] ?? '') !== 'COMPLETED') json_response(['error' => 'PayPal payment was not completed'], 402);
    $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $paypalOrderId;
    finalize_order_payment($orderId, $captureId, (float) $order['total_amount']);
    json_response(['status' => 'paid']);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 503);
}
