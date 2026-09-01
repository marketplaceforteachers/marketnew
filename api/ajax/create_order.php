<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

$me = current_user();
if (!$me) {
    json_response(['error' => 'Not authenticated'], 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$shippingAddress = trim($input['shippingAddress'] ?? '');
$paymentGateway = $input['paymentGateway'] ?? '';

if (!$items || !in_array($paymentGateway, ['stripe', 'paypal', 'school_po'], true)) {
    json_response(['error' => 'Invalid order payload'], 400);
}

$ids = array_map(fn($i) => (int) $i['listingId'], $items);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = db()->prepare("SELECT id, seller_id, price, shipping_fee, is_active FROM listings WHERE id IN ($placeholders)");
$stmt->execute($ids);
$listingsById = [];
foreach ($stmt->fetchAll() as $row) {
    $listingsById[(int) $row['id']] = $row;
}

$subtotal = 0;
$shipping = 0;
foreach ($items as $item) {
    $listingId = (int) $item['listingId'];
    $qty = max(1, (int) $item['quantity']);
    $listing = $listingsById[$listingId] ?? null;
    if (!$listing || !$listing['is_active']) {
        json_response(['error' => "Listing $listingId is no longer available"], 400);
    }
    $subtotal += (float) $listing['price'] * $qty;
    $shipping += (float) $listing['shipping_fee'] * $qty;
}
$total = $subtotal + $shipping;

db()->prepare(
    "INSERT INTO orders (buyer_id, total_amount, shipping_amount, tax_amount, status, shipping_address, payment_gateway)
     VALUES (?, ?, ?, 0, 'pending', ?, ?)"
)->execute([$me['id'], $total, $shipping, $shippingAddress, $paymentGateway]);
$orderId = (int) db()->lastInsertId();

foreach ($items as $item) {
    $listingId = (int) $item['listingId'];
    $qty = max(1, (int) $item['quantity']);
    $listing = $listingsById[$listingId];
    db()->prepare('INSERT INTO order_items (order_id, listing_id, seller_id, quantity, price) VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, $listingId, $listing['seller_id'], $qty, $listing['price']]);
}

json_response(['id' => $orderId, 'totalAmount' => $total, 'shippingAmount' => $shipping]);
