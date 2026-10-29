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
                ['label' => 'Blog', 'href' => '/blog.php'],
                ['label' => 'How It Works', 'href' => '/how-it-works.php'],
                ['label' => 'Fundraising & Wishlists', 'href' => '/wishlists.php'],
                ['label' => 'Sell for Free', 'href' => '/post-listing.php'],
            ],
        ],
        'homepage' => [
            'badgeText' => 'Made by teachers, for teachers',
            'heroHeadline' => 'Classroom supplies, passed from one *teacher to the next*',
            'heroSubtext' => 'Find books, STEM kits and furniture other teachers no longer need, or list what\'s sitting in your closet. Listing is free, and you can meet up at school or ship it.',
            'heroSlides' => [
                ['imageUrl' => 'https://images.unsplash.com/photo-1509062522246-3755977927d7?w=1600&h=900&fit=crop&auto=format&q=80', 'alt' => 'Teacher presenting a lesson to an engaged classroom'],
                ['imageUrl' => 'https://images.unsplash.com/photo-1588075592405-d3d4f0846961?w=1600&h=900&fit=crop&auto=format&q=80', 'alt' => 'Teacher reading a story to young students seated on the floor'],
                ['imageUrl' => 'https://images.unsplash.com/photo-1571260899304-425eee4c7efc?w=1600&h=900&fit=crop&auto=format&q=80', 'alt' => 'High school students studying together in class'],
                ['imageUrl' => 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=1600&h=900&fit=crop&auto=format&q=80', 'alt' => 'Empty classroom with rows of desks and a chalkboard'],
            ],
            'trustHeadline' => 'A simpler way to stock your classroom',
            'trustBadgeState' => '',
            'trustDescription' => 'Teachers spend a lot of their own money on their rooms. Here you can buy secondhand from someone who has taught the same grade, give away what you don\'t need, and pay with a card, PayPal or a school purchase order.',
            'trustRating' => 4.9,
            'trustReviewCount' => 4,
            'trustSatisfactionPct' => 99.8,
            'trustDisputeRate' => '< 0.1% (Zero Fraud)',
            'trustDistricts' => '850+ Districts',
            'trustBadges' => [
                ['icon' => 'badge-check', 'tone' => 'emerald', 'label' => 'Verified Educator badges'],
                ['icon' => 'school', 'tone' => 'royal', 'label' => 'School POs accepted'],
                ['icon' => 'gift', 'tone' => 'amber', 'label' => 'Free giveaways welcome'],
            ],
        ],
        'fees' => [
            'platformFeePercent' => 5,
        ],
        'seasonal_hub' => [
            'freeSurplusBannerEnabled' => true,
            'freeSurplusBannerText' => 'Free stuff only',
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
            'description' => 'A place for teachers to buy, sell and give away classroom supplies. Started by educators who were tired of paying full price for things another classroom was throwing out.',
            'supportEmail' => 'support@marketplaceforteachers.com',
            'features' => [
                ['icon' => 'shield', 'color' => 'var(--royal-600)', 'title' => 'Know who you\'re buying from', 'desc' => 'Teachers who confirm their school email or ID get a Verified Educator badge on every listing.'],
                ['icon' => 'book', 'color' => 'var(--emerald-600)', 'title' => 'Free to list', 'desc' => 'Post as many items as you like. We only take a small fee when something sells.'],
                ['icon' => 'map-pin', 'color' => 'var(--amber-600)', 'title' => 'Pick up at school', 'desc' => 'Skip shipping costs and meet at a school office or campus near you.'],
                ['icon' => 'lock', 'color' => 'var(--violet-600)', 'title' => 'Paperwork your school accepts', 'desc' => 'Pay with a school purchase order and get a printable receipt for your records.'],
            ],
            'trustLinks' => [
                ['label' => 'How it works', 'href' => '/how-it-works.php'],
                ['label' => 'Report a problem with an order', 'href' => '/disputes.php'],
                                ['label' => 'Terms', 'href' => '/terms.php'],
                ['label' => 'Privacy', 'href' => '/privacy.php'],
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

// --- Non-payment integrations (Anthropic — powers the blog auto-writer) ---

function default_anthropic_config(): array
{
    return [
        'apiKey' => '',
        'isEnabled' => false,
        'model' => 'claude-sonnet-5',
        'topics' => 'K-12 classroom teaching, education policy, teacher supplies and budgets',
    ];
}

function get_anthropic_config(): array
{
    $stmt = db()->prepare("SELECT config_json FROM integration_configs WHERE integration_key = 'anthropic'");
    $stmt->execute();
    $row = $stmt->fetch();
    $defaults = default_anthropic_config();
    if (!$row) {
        return $defaults;
    }
    return array_merge($defaults, json_decode($row['config_json'], true) ?? []);
}

function set_anthropic_config(array $config): void
{
    $json = json_encode($config, JSON_INVALID_UTF8_SUBSTITUTE);
    $stmt = db()->prepare(
        "INSERT INTO integration_configs (integration_key, config_json) VALUES ('anthropic', ?)
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
