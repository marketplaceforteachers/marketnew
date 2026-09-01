<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/resend.php';

function record_donation(int $campaignId, float $amount, string $donorName, string $donorEmail): void
{
    db()->prepare('INSERT INTO donations (campaign_id, donor_name, donor_email, amount) VALUES (?, ?, ?, ?)')
        ->execute([$campaignId, $donorName, $donorEmail, $amount]);
    db()->prepare('UPDATE fundraising_campaigns SET current_funds = current_funds + ? WHERE id = ?')
        ->execute([$amount, $campaignId]);
    send_transactional_email('donation_receipt', $donorEmail, ['donor_name' => $donorName, 'amount' => number_format($amount, 2)]);
}
