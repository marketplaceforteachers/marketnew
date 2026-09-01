<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
header('Content-Type: application/json');

$settings = get_all_settings();
$gateways = get_all_gateways();

echo json_encode(array_merge($settings, [
    'payments' => [
        'stripe' => ['enabled' => $gateways['stripe']['isEnabled'], 'publishableKey' => $gateways['stripe']['config']['publishableKey']],
        'paypal' => ['enabled' => $gateways['paypal']['isEnabled'], 'clientId' => $gateways['paypal']['config']['clientId']],
        'school_po' => ['enabled' => $gateways['school_po']['isEnabled'], 'instructions' => $gateways['school_po']['config']['instructions']],
    ],
]));
