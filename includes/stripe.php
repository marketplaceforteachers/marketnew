<?php
require_once __DIR__ . '/settings.php';

class StripeNotConfiguredException extends Exception {}

function stripe_request(string $method, string $path, array $params = []): array
{
    $gateway = get_gateway('stripe');
    if (!$gateway['isEnabled'] || empty($gateway['config']['secretKey'])) {
        throw new StripeNotConfiguredException(
            "Stripe is not configured yet. An admin needs to add live keys under Admin -> Payment Gateways."
        );
    }
    $secretKey = $gateway['config']['secretKey'];

    $ch = curl_init('https://api.stripe.com/v1' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_USERPWD => $secretKey . ':',
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($method === 'POST' && $params) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true) ?? [];
    if ($status >= 400) {
        throw new Exception('Stripe error: ' . ($data['error']['message'] ?? "HTTP $status"));
    }
    return $data;
}

function stripe_publishable_key(): string
{
    return get_gateway('stripe')['config']['publishableKey'] ?? '';
}

function stripe_create_payment_intent(float $amount, array $metadata = []): array
{
    $params = [
        'amount' => (int) round($amount * 100),
        'currency' => 'usd',
        'automatic_payment_methods[enabled]' => 'true',
    ];
    foreach ($metadata as $key => $value) {
        $params["metadata[$key]"] = (string) $value;
    }
    return stripe_request('POST', '/payment_intents', $params);
}

function stripe_retrieve_payment_intent(string $id): array
{
    return stripe_request('GET', '/payment_intents/' . urlencode($id));
}

function stripe_create_refund(string $paymentIntentId, ?float $amount = null): array
{
    $params = ['payment_intent' => $paymentIntentId];
    if ($amount !== null) {
        $params['amount'] = (int) round($amount * 100);
    }
    return stripe_request('POST', '/refunds', $params);
}

function stripe_create_connect_account(): array
{
    return stripe_request('POST', '/accounts', ['type' => 'express']);
}

function stripe_create_account_link(string $accountId, string $refreshUrl, string $returnUrl): array
{
    return stripe_request('POST', '/account_links', [
        'account' => $accountId,
        'refresh_url' => $refreshUrl,
        'return_url' => $returnUrl,
        'type' => 'account_onboarding',
    ]);
}

function stripe_create_transfer(float $amount, string $destinationAccountId): array
{
    return stripe_request('POST', '/transfers', [
        'amount' => (int) round($amount * 100),
        'currency' => 'usd',
        'destination' => $destinationAccountId,
    ]);
}

/** Verifies a Stripe webhook signature by hand (Stripe's documented algorithm) — no SDK required. */
function stripe_verify_webhook_signature(string $payload, string $sigHeader, string $secret): bool
{
    $parts = [];
    foreach (explode(',', $sigHeader) as $piece) {
        [$k, $v] = array_pad(explode('=', $piece, 2), 2, '');
        $parts[$k][] = $v;
    }
    $timestamp = $parts['t'][0] ?? '';
    $signatures = $parts['v1'] ?? [];
    if (!$timestamp || !$signatures) {
        return false;
    }
    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}
