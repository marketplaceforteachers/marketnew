<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/resend.php';

/**
 * $gatewayTxId (the Stripe payment intent id) makes this idempotent — donation_confirm.php is
 * reachable without authentication and can legitimately be called more than once for the same
 * payment (retries, a user refreshing the confirmation page), so without a dedupe key a replay
 * would add the same real payment's amount to current_funds repeatedly and re-send the receipt
 * email each time.
 */
function record_donation(int $campaignId, float $amount, string $donorName, string $donorEmail, string $gatewayTxId): void
{
    $stmt = db()->prepare('SELECT 1 FROM donations WHERE gateway_tx_id = ? LIMIT 1');
    $stmt->execute([$gatewayTxId]);
    if ($stmt->fetchColumn()) {
        return; // already recorded — the UNIQUE KEY on gateway_tx_id backs this up if two requests race
    }

    db()->prepare('INSERT INTO donations (campaign_id, donor_name, donor_email, amount, gateway_tx_id) VALUES (?, ?, ?, ?, ?)')
        ->execute([$campaignId, $donorName, $donorEmail, $amount, $gatewayTxId]);
    db()->prepare('UPDATE fundraising_campaigns SET current_funds = current_funds + ? WHERE id = ?')
        ->execute([$amount, $campaignId]);
    send_transactional_email('donation_receipt', $donorEmail, ['donor_name' => $donorName, 'amount' => number_format($amount, 2)]);
}
