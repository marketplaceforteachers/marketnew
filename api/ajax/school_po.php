<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

$me = current_user();
if (!$me) json_response(['error' => 'Not authenticated'], 401);

$input = json_decode(file_get_contents('php://input'), true);
$orderId = (int) ($input['orderId'] ?? 0);
$poNumber = trim($input['poNumber'] ?? '');
$district = trim($input['district'] ?? '');

$gateway = get_gateway('school_po');
if (!$gateway['isEnabled']) json_response(['error' => 'School PO billing is not enabled on this site.'], 503);

$stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order || (int) $order['buyer_id'] !== (int) $me['id']) json_response(['error' => 'Order not found'], 404);
if ($order['status'] !== 'pending') json_response(['error' => 'Order is not awaiting payment'], 409);

db()->prepare('UPDATE orders SET payment_reference = ? WHERE id = ?')
    ->execute(["PO:$poNumber ($district)", $orderId]);

json_response(['status' => 'invoice_pending']);
