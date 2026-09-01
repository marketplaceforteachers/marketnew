<?php
require_once __DIR__ . '/settings.php';

class PayPalNotConfiguredException extends Exception {}

function paypal_base_url(string $environment): string
{
    return $environment === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
}

function paypal_get_access_token(): array
{
    $gateway = get_gateway('paypal');
    $config = $gateway['config'];
    if (!$gateway['isEnabled'] || empty($config['clientId']) || empty($config['clientSecret'])) {
        throw new PayPalNotConfiguredException(
            "PayPal is not configured yet. An admin needs to add live keys under Admin -> Payment Gateways."
        );
    }

    $ch = curl_init(paypal_base_url($config['environment']) . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $config['clientId'] . ':' . $config['clientSecret'],
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 400) {
        throw new Exception('PayPal authentication failed');
    }
    $data = json_decode($response, true);
    return ['token' => $data['access_token'], 'environment' => $config['environment']];
}

function paypal_request(string $method, string $path, array $body = []): array
{
    $auth = paypal_get_access_token();
    $ch = curl_init(paypal_base_url($auth['environment']) . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $auth['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true) ?? [];
    if ($status >= 400) {
        throw new Exception('PayPal error: ' . ($data['message'] ?? "HTTP $status"));
    }
    return $data;
}

function paypal_client_id(): string
{
    return get_gateway('paypal')['config']['clientId'] ?? '';
}

function paypal_create_order(float $amount, string $currency = 'USD'): array
{
    return paypal_request('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => ['currency_code' => $currency, 'value' => number_format($amount, 2, '.', '')],
        ]],
    ]);
}

function paypal_capture_order(string $paypalOrderId): array
{
    return paypal_request('POST', "/v2/checkout/orders/$paypalOrderId/capture");
}

function paypal_refund_capture(string $captureId, ?float $amount = null): array
{
    $body = [];
    if ($amount !== null) {
        $body['amount'] = ['currency_code' => 'USD', 'value' => number_format($amount, 2, '.', '')];
    }
    return paypal_request('POST', "/v2/payments/captures/$captureId/refund", $body);
}
