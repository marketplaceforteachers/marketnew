<?php
require_once __DIR__ . '/db.php';

const SETTING_KEYS = ['branding', 'header', 'homepage', 'fees', 'seasonal_hub', 'footer', 'cron', 'mail_delivery'];

function default_settings(): array
{
    return [
        'branding' => [
            'siteName' => 'MarketplaceForTeachers.com',
            'tagline' => 'Verified Educator Supply Exchange',
            'accentColor' => '#2563eb',
            'logoUrl' => null,
        ],
        'header' => [
            'primaryNav' => [
                ['label' => 'Browse All', 'href' => '/browse.php'],
                ['label' => 'How It Works', 'href' => '/how-it-works.php'],
                ['label' => 'Fundraising & Wishlists', 'href' => '/wishlists.php'],
                ['label' => 'Sell for Free', 'href' => '/post-listing.php'],
            ],
        ],
        'homepage' => [
            'badgeText' => 'Dedicated Exclusively to USA Teachers & Schools',
            'heroHeadline' => 'Buy, Sell & Exchange Supplies with Fellow Educators',
            'heroSubtext' => 'Stock classroom libraries, grab hands-on STEM kits, or pass along surplus furniture. Zero listing fees, verified educator trust, and contact-free school pickups.',
            'heroSlides' => [
                ['imageUrl' => 'https://picsum.photos/id/1005/1600/900', 'alt' => 'Teacher working with classroom supplies'],
                ['imageUrl' => 'https://picsum.photos/id/1074/1600/900', 'alt' => 'Books and classroom reading materials'],
                ['imageUrl' => 'https://picsum.photos/id/1062/1600/900', 'alt' => 'Students collaborating in a classroom'],
                ['imageUrl' => 'https://picsum.photos/id/1059/1600/900', 'alt' => 'Educator organizing classroom materials'],
            ],
            'trustHeadline' => 'The #1 Trusted Peer-to-Peer Marketplace for USA Educators',
            'trustBadgeState' => 'NY & 50 STATES',
            'trustDescription' => 'Rated 4.9 / 5.0 by over 12,450 in New York, Oklahoma, Texas, California, and nationwide. Every transaction is backed by our 100% Buyer Protection Guarantee and official tax-exempt school PO billing.',
            'trustRating' => 4.9,
            'trustReviewCount' => 4,
            'trustSatisfactionPct' => 99.8,
            'trustDisputeRate' => '< 0.1% (Zero Fraud)',
            'trustDistricts' => '850+ Districts',
            'trustBadges' => [
                ['icon' => 'star', 'tone' => 'amber', 'label' => '5-Star Trusted Educator Website'],
                ['icon' => 'shield', 'tone' => 'emerald', 'label' => 'NYC DOE & NYSED Safe Harbor Verified'],
                ['icon' => 'badge-check', 'tone' => 'royal', 'label' => 'BBB Accredited A+'],
            ],
        ],
        'fees' => [
            'platformFeePercent' => 5,
        ],
        'seasonal_hub' => [
            'freeSurplusBannerEnabled' => true,
            'freeSurplusBannerText' => 'Free Surplus / $0 Donations Only',
            'items' => [
                ['key' => 'all', 'label' => 'All Items', 'icon' => 'sparkles', 'enabled' => true],
                ['key' => 'back-to-school', 'label' => 'Back to School', 'icon' => 'backpack', 'enabled' => true],
                ['key' => 'fall-crafts', 'label' => 'Fall & Crafts', 'icon' => 'leaf', 'enabled' => true],
                ['key' => 'winter-library', 'label' => 'Winter Library', 'icon' => 'snowflake', 'enabled' => true],
                ['key' => 'sel', 'label' => '100 Days & SEL', 'icon' => 'heart', 'enabled' => true],
                ['key' => 'stem', 'label' => 'Spring STEM', 'icon' => 'flask', 'enabled' => true],
            ],
        ],
        'footer' => [
            'description' => 'The premier USA peer-to-peer marketplace empowering educators to circulate classroom supplies, books, furniture, and STEM learning kits affordably.',
            'address' => '9905 S Pennsylvania Ave Ste A, Oklahoma City, OK 73159, USA',
            'phone' => '(405) 555-8322',
            'supportEmail' => 'support@marketplaceforteachers.com',
            'features' => [
                ['icon' => 'shield', 'color' => 'var(--royal-600)', 'title' => 'Verified Teachers Only', 'desc' => 'Every educator credential is authenticated to ensure trusted, safe classroom exchanges.'],
                ['icon' => 'book', 'color' => 'var(--emerald-600)', 'title' => 'Zero Upfront Listing Fees', 'desc' => 'List unlimited books, furniture, and STEM sets for free. Only a low platform fee when items sell.'],
                ['icon' => 'map-pin', 'color' => 'var(--amber-600)', 'title' => 'Safe School Office Pickups', 'desc' => 'Save on freight costs with secure contact-free swaps at local district offices or campuses.'],
                ['icon' => 'lock', 'color' => 'var(--violet-600)', 'title' => 'Tax-Exempt Ready Invoices', 'desc' => 'Official receipts and school district purchase order (PO) workflows, ready to print.'],
            ],
            'trustLinks' => [
                ['label' => 'Trust & Safety Center', 'href' => '/how-it-works.php'],
                ['label' => 'Dispute Resolution Portal', 'href' => '/disputes.php'],
                ['label' => 'Frequently Asked Questions', 'href' => '/how-it-works.php'],
                ['label' => 'Terms of Service', 'href' => '/terms.php'],
                ['label' => 'Privacy & FERPA Protection', 'href' => '/privacy.php'],
            ],
            'socialLinks' => [
                ['label' => 'Facebook', 'href' => '#'],
                ['label' => 'Instagram', 'href' => '#'],
                ['label' => 'X / Twitter', 'href' => '#'],
                ['label' => 'Pinterest', 'href' => '#'],
            ],
        ],
        'cron' => [
            'secret' => '',
        ],
        'mail_delivery' => [
            'method' => 'resend',
        ],
    ];
}

function get_all_settings(): array
{
    $defaults = default_settings();
    $stmt = db()->query('SELECT setting_key, value_json FROM site_settings');
    $stored = [];
    foreach ($stmt->fetchAll() as $row) {
        $stored[$row['setting_key']] = json_decode($row['value_json'], true);
    }
    $merged = [];
    foreach (SETTING_KEYS as $key) {
        $merged[$key] = array_merge($defaults[$key], $stored[$key] ?? []);
    }
    return $merged;
}

function get_setting(string $key): array
{
    $all = get_all_settings();
    return $all[$key] ?? [];
}

function set_setting(string $key, array $value): void
{
    $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
    $stmt = db()->prepare(
        'INSERT INTO site_settings (setting_key, value_json, is_public) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE value_json = VALUES(value_json)'
    );
    $stmt->execute([$key, $json]);
}

// --- Payment gateways -------------------------------------------------

const GATEWAY_IDS = ['stripe', 'paypal', 'school_po'];

function default_gateway_config(string $gateway): array
{
    return match ($gateway) {
        'stripe' => ['publishableKey' => '', 'secretKey' => '', 'webhookSecret' => ''],
        'paypal' => ['clientId' => '', 'clientSecret' => '', 'environment' => 'sandbox'],
        'school_po' => [
            'instructions' => "Mail a signed purchase order to our office; we'll invoice your district directly.",
            'payableTo' => 'MarketplaceForTeachers.com',
        ],
        default => [],
    };
}

function get_gateway(string $gateway): array
{
    $stmt = db()->prepare('SELECT is_enabled, config_json FROM payment_gateway_configs WHERE gateway = ?');
    $stmt->execute([$gateway]);
    $row = $stmt->fetch();
    $defaults = default_gateway_config($gateway);
    if (!$row) {
        return ['isEnabled' => false, 'config' => $defaults];
    }
    return [
        'isEnabled' => (bool) $row['is_enabled'],
        'config' => array_merge($defaults, json_decode($row['config_json'], true) ?? []),
    ];
}

function get_all_gateways(): array
{
    $result = [];
    foreach (GATEWAY_IDS as $gateway) {
        $result[$gateway] = get_gateway($gateway);
    }
    return $result;
}

function set_gateway(string $gateway, bool $isEnabled, array $config): void
{
    $json = json_encode($config, JSON_INVALID_UTF8_SUBSTITUTE);
    $stmt = db()->prepare(
        'INSERT INTO payment_gateway_configs (gateway, is_enabled, config_json) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), config_json = VALUES(config_json)'
    );
    $stmt->execute([$gateway, $isEnabled ? 1 : 0, $json]);
}

function mask_secret(string $value): string
{
    if ($value === '') {
        return '';
    }
    if (strlen($value) <= 4) {
        return '****';
    }
    return str_repeat('•', strlen($value) - 4) . substr($value, -4);
}

// --- Non-payment integrations (Resend) --------------------------------

function default_resend_config(): array
{
    return [
        'apiKey' => '',
        'fromEmail' => 'notifications@marketplaceforteachers.com',
        'fromName' => 'Marketplace For Teachers',
    ];
}

function get_resend_config(): array
{
    $stmt = db()->prepare("SELECT config_json FROM integration_configs WHERE integration_key = 'resend'");
    $stmt->execute();
    $row = $stmt->fetch();
    $defaults = default_resend_config();
    if (!$row) {
        return $defaults;
    }
    return array_merge($defaults, json_decode($row['config_json'], true) ?? []);
}

function set_resend_config(array $config): void
{
    $json = json_encode($config, JSON_INVALID_UTF8_SUBSTITUTE);
    $stmt = db()->prepare(
        "INSERT INTO integration_configs (integration_key, config_json) VALUES ('resend', ?)
         ON DUPLICATE KEY UPDATE config_json = VALUES(config_json)"
    );
    $stmt->execute([$json]);
}

// --- Non-payment integrations (SMTP) -----------------------------------

function default_smtp_config(): array
{
    return [
        'host' => '',
        'port' => 587,
        'encryption' => 'tls', // 'tls' (STARTTLS), 'ssl' (implicit TLS), or 'none'
        'username' => '',
        'password' => '',
        'fromEmail' => '',
        'fromName' => '',
    ];
}

function get_smtp_config(): array
{
    $stmt = db()->prepare("SELECT config_json FROM integration_configs WHERE integration_key = 'smtp'");
    $stmt->execute();
    $row = $stmt->fetch();
    $defaults = default_smtp_config();
    if (!$row) {
        return $defaults;
    }
    return array_merge($defaults, json_decode($row['config_json'], true) ?? []);
}

function set_smtp_config(array $config): void
{
    $json = json_encode($config, JSON_INVALID_UTF8_SUBSTITUTE);
    $stmt = db()->prepare(
        "INSERT INTO integration_configs (integration_key, config_json) VALUES ('smtp', ?)
         ON DUPLICATE KEY UPDATE config_json = VALUES(config_json)"
    );
    $stmt->execute([$json]);
}

// --- Admin audit log ----------------------------------------------------

function log_admin_action(int $adminId, string $action, string $targetType, $targetId = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO admin_audit_logs (admin_id, action, target_type, target_id, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$adminId, $action, $targetType, $targetId !== null ? (string) $targetId : null, $_SERVER['REMOTE_ADDR'] ?? null]);
}
