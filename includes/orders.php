<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/paypal.php';
require_once __DIR__ . '/resend.php';
require_once __DIR__ . '/drips.php';

/** Marks an order paid, records the payment, splits payouts across sellers, and emails receipts. */
function finalize_order_payment(int $orderId, string $gatewayTxId, float $amount): void
{
    $stmt = db()->prepare('SELECT status, buyer_id FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return;
    }
    if (in_array($order['status'], ['paid', 'completed'], true)) {
        return; // idempotent
    }

    db()->prepare('UPDATE orders SET status = \'paid\', payment_reference = ? WHERE id = ?')
        ->execute([$gatewayTxId, $orderId]);

    enroll_in_drips('order_paid', (int) $order['buyer_id']);

    db()->prepare('INSERT INTO payments (order_id, gateway_tx_id, amount, status) VALUES (?, ?, ?, \'succeeded\')')
        ->execute([$orderId, $gatewayTxId, $amount]);

    $fees = get_setting('fees');
    $feePercent = (float) ($fees['platformFeePercent'] ?? 5);

    $stmt = db()->prepare('SELECT seller_id, quantity, price FROM order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $bySeller = [];
    foreach ($stmt->fetchAll() as $item) {
        $lineTotal = (float) $item['price'] * (int) $item['quantity'];
        $sellerId = (int) $item['seller_id'];
        $bySeller[$sellerId] = ($bySeller[$sellerId] ?? 0) + $lineTotal;
    }

    foreach ($bySeller as $sellerId => $grossAmount) {
        $feeAmount = round($grossAmount * ($feePercent / 100), 2);
        $payoutAmount = round($grossAmount - $feeAmount, 2);
        db()->prepare(
            'INSERT INTO seller_payouts (seller_id, order_id, payout_amount, fee_amount, status) VALUES (?, ?, ?, ?, \'pending\')'
        )->execute([$sellerId, $orderId, $payoutAmount, $feeAmount]);
    }

    $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
    $stmt->execute([$order['buyer_id']]);
    if ($buyer = $stmt->fetch()) {
        send_transactional_email('order_confirmation', $buyer['email'], [
            'order_id' => $orderId,
            'total' => number_format($amount, 2),
        ]);
    }

    foreach (array_keys($bySeller) as $sellerId) {
        $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ?');
        $stmt->execute([$sellerId]);
        if ($seller = $stmt->fetch()) {
            send_transactional_email('item_sold', $seller['email'], ['order_id' => $orderId]);
        }
    }
}

/** Refunds an order through whichever gateway it was paid with (or just cancels for School PO). */
function refund_order(int $orderId, ?float $amount = null): void
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new Exception('Order not found');
    }
    if (!$order['payment_reference']) {
        throw new Exception('Order has no recorded payment to refund');
    }

    if ($order['payment_gateway'] === 'stripe') {
        stripe_create_refund($order['payment_reference'], $amount);
    } elseif ($order['payment_gateway'] === 'paypal') {
        paypal_refund_capture($order['payment_reference'], $amount);
    }
    // school_po: no gateway call — refund is a manual credit memo to the district.

    db()->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$orderId]);
    db()->prepare("UPDATE seller_payouts SET status = 'failed' WHERE order_id = ? AND status = 'pending'")->execute([$orderId]);
}
