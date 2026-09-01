<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/stripe.php';
header('Content-Type: application/json');

$me = current_user();
if (!$me) json_response(['error' => 'Not authenticated'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['orderId'] ?? 0);

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order || (int) $order['buyer_id'] !== (int) $me['id']) json_response(['error' => 'Order not found'], 404);
if ($order['status'] !== 'pending') json_response(['error' => 'Order is not awaiting payment'], 409);

try {
    $intent = stripe_create_payment_intent((float) $order['total_amount'], ['orderId' => $orderId]);
    json_response(['clientSecret' => $intent['client_secret']]);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 503);
}
