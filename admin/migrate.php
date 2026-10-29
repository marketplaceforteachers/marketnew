<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

/**
 * One-time/idempotent schema sync: brings an already-live database up to date with
 * db/schema.sql without touching existing data. Safe to run more than once — every
 * step checks information_schema first and skips anything already present.
 */

function column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function add_column_if_missing(string $table, string $column, string $definition): ?string
{
    if (column_exists($table, $column)) {
        return null;
    }
    try {
        db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    } catch (PDOException $e) {
        return "ERROR adding column $table.$column: " . $e->getMessage();
    }
    return "Added column $table.$column";
}

/**
 * Runs one DDL/DML step and turns any failure into a log entry instead of an uncaught
 * exception — a single bad step (e.g. a privilege error) used to kill the whole migration
 * silently (blank 500 page) and leave everything after it, including new tables that later
 * requests depend on, never created.
 */
function run_step(string $label, callable $fn): string
{
    try {
        $fn();
        return $label;
    } catch (PDOException $e) {
        return "ERROR $label: " . $e->getMessage();
    }
}

$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // users — registration/profile fields
    $userColumns = [
        'first_name' => 'VARCHAR(80) NULL AFTER name',
        'avatar_url' => 'VARCHAR(500) NULL',
        'last_name' => 'VARCHAR(80) NULL AFTER first_name',
        'account_type' => 'VARCHAR(30) NULL AFTER role',
        'phone' => 'VARCHAR(30) NULL',
        'email_verified_at' => 'TIMESTAMP NULL',
        'school_email' => 'VARCHAR(255) NULL',
        'district' => 'VARCHAR(200) NULL',
        'address_line1' => 'VARCHAR(200) NULL',
        'city' => 'VARCHAR(120) NULL',
        'zip_code' => 'VARCHAR(12) NULL',
        'store_name' => 'VARCHAR(150) NULL',
        'school_name' => 'VARCHAR(200) NULL',
        'state' => 'VARCHAR(2) NULL',
    ];
    foreach ($userColumns as $col => $def) {
        $r = add_column_if_missing('users', $col, $def);
        if ($r) $log[] = $r;
    }

    // orders — structured shipping fields
    $orderColumns = [
        'shipping_name' => 'VARCHAR(150) NULL',
        'shipping_phone' => 'VARCHAR(30) NULL',
        'shipping_city' => 'VARCHAR(120) NULL',
        'shipping_state' => 'VARCHAR(2) NULL',
        'shipping_zip' => 'VARCHAR(12) NULL',
    ];
    foreach ($orderColumns as $col => $def) {
        $r = add_column_if_missing('orders', $col, $def);
        if ($r) $log[] = $r;
    }

    // email_logs — records why a send failed, not just that it did
    $r = add_column_if_missing('email_logs', 'error_message', 'VARCHAR(500) NULL');
    if ($r) $log[] = $r;

    // new standalone tables
    $tables = [
        'login_attempts' => "CREATE TABLE IF NOT EXISTS login_attempts (
          id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          ip_address      VARCHAR(45)       NOT NULL,
          attempted_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
          KEY idx_login_attempts_ip_time (ip_address, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'password_resets' => "CREATE TABLE IF NOT EXISTS password_resets (
          id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id         BIGINT UNSIGNED   NOT NULL,
          token_hash      CHAR(64)          NOT NULL,
          expires_at      TIMESTAMP         NOT NULL,
          used_at         TIMESTAMP         NULL,
          created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          KEY idx_password_resets_token (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'blog_posts' => "CREATE TABLE IF NOT EXISTS blog_posts (
          id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          title           VARCHAR(255)      NOT NULL,
          slug            VARCHAR(255)      NOT NULL,
          excerpt         VARCHAR(320),
          content         MEDIUMTEXT        NOT NULL,
          cover_image_url VARCHAR(500),
          author_name     VARCHAR(150)      NOT NULL DEFAULT 'MarketplaceForTeachers.com Team',
          status          ENUM('draft','published') NOT NULL DEFAULT 'draft',
          source          ENUM('manual','ai_generated') NOT NULL DEFAULT 'manual',
          source_url      VARCHAR(500),
          published_at    TIMESTAMP         NULL,
          created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uq_blog_posts_slug (slug),
          KEY idx_blog_posts_status_published (status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        'email_verifications' => "CREATE TABLE IF NOT EXISTS email_verifications (
          id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id         BIGINT UNSIGNED   NOT NULL,
          token_hash      CHAR(64)          NOT NULL,
          code_hash       CHAR(64)          NOT NULL,
          expires_at      TIMESTAMP         NOT NULL,
          created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          KEY idx_email_verifications_token (token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tables as $name => $ddl) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$name]);
        if (!$stmt->fetchColumn()) {
            $log[] = run_step("Created table $name", fn() => db()->exec($ddl));
        }
    }

    // email_verifications.code_hash may exist from an older version of this table without it
    if (!column_exists('email_verifications', 'code_hash')) {
        $log[] = run_step(
            'Added column email_verifications.code_hash',
            fn() => db()->exec("ALTER TABLE email_verifications ADD COLUMN code_hash CHAR(64) NOT NULL AFTER token_hash")
        );
    }

    // donations.gateway_tx_id — makes donation confirmation idempotent (without it, a replayed
    // confirmation call for the same real Stripe payment inflates a campaign's funds and re-sends
    // the donor's receipt email every time).
    if (!column_exists('donations', 'gateway_tx_id')) {
        $log[] = run_step('Added column donations.gateway_tx_id (with unique index)', function () {
            db()->exec("ALTER TABLE donations ADD COLUMN gateway_tx_id VARCHAR(255) NULL");
            db()->exec("ALTER TABLE donations ADD UNIQUE KEY uq_donations_gateway_tx (gateway_tx_id)");
        });
    }

    // email templates for password reset / email verification
    $templates = [
        'password_reset' => [
            'subject' => 'Reset your password',
            'html_body' => '<p>Hi {{name}},</p><p>Click the button below to reset your password. This link expires in 1 hour.</p><p style="text-align:center;margin:28px 0;"><a href="{{reset_url}}" style="background:#1d4ed8;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;">Reset Password</a></p><p>If you didn\'t request this, you can safely ignore this email.</p>',
        ],
        'email_verification' => [
            'subject' => 'Verify your email address',
            'html_body' => '<p>Hi {{name}},</p><p>Thanks for joining! Click the button below to verify your email, or enter this code on the verification page:</p><p style="text-align:center;font-size:28px;font-weight:700;letter-spacing:4px;margin:20px 0;">{{code}}</p><p style="text-align:center;margin:28px 0;"><a href="{{verify_url}}" style="background:#1d4ed8;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;">Verify Email</a></p><p>This code and link expire in 1 hour.</p>',
        ],
    ];
    foreach ($templates as $key => $tpl) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM email_templates WHERE template_key = ?');
        $stmt->execute([$key]);
        if (!$stmt->fetchColumn()) {
            $log[] = run_step("Added email template: $key", fn() => db()->prepare(
                'INSERT INTO email_templates (template_key, subject, html_body) VALUES (?, ?, ?)'
            )->execute([$key, $tpl['subject'], $tpl['html_body']]));
        }
    }

    // Site wording refresh: settings saved from Admin -> Branding override the defaults in
    // includes/settings.php, so a site that saved them still shows the old wording. Swap in the
    // new default only where the saved value is still the old default, exactly; anything an
    // admin has customised is left alone.
    $oldCopy = [
        'homepage' => [
            'badgeText' => 'Dedicated Exclusively to USA Teachers & Schools',
            'heroHeadline' => 'Buy, Sell & Exchange Supplies with Fellow Educators',
            'heroSubtext' => 'Stock classroom libraries, grab hands-on STEM kits, or pass along surplus furniture. Zero listing fees, verified educator trust, and contact-free school pickups.',
            'trustHeadline' => 'The #1 Trusted Peer-to-Peer Marketplace for USA Educators',
            'trustBadgeState' => 'NY & 50 STATES',
            'trustDescription' => 'Rated 4.9 / 5.0 by over 12,450 in New York, Oklahoma, Texas, California, and nationwide. Every transaction is backed by our 100% Buyer Protection Guarantee and official tax-exempt school PO billing.',
            'trustBadges' => [
                ['icon' => 'star', 'tone' => 'amber', 'label' => '5-Star Trusted Educator Website'],
                ['icon' => 'shield', 'tone' => 'emerald', 'label' => 'NYC DOE & NYSED Safe Harbor Verified'],
                ['icon' => 'badge-check', 'tone' => 'royal', 'label' => 'BBB Accredited A+'],
            ],
        ],
        'footer' => [
            'description' => 'The premier USA peer-to-peer marketplace empowering educators to circulate classroom supplies, books, furniture, and STEM learning kits affordably.',
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
        ],
        'seasonal_hub' => [
            'freeSurplusBannerText' => 'Free Surplus / $0 Donations Only',
        ],
    ];
    $newDefaults = default_settings();
    foreach ($oldCopy as $settingKey => $fields) {
        $stmt = db()->prepare('SELECT value_json FROM site_settings WHERE setting_key = ?');
        $stmt->execute([$settingKey]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            continue; // never saved, so the new defaults already show
        }
        $stored = json_decode($raw, true) ?: [];
        $changed = [];
        foreach ($fields as $field => $oldValue) {
            if (array_key_exists($field, $stored) && $stored[$field] == $oldValue) {
                $stored[$field] = $newDefaults[$settingKey][$field];
                $changed[] = $field;
            }
        }
        if ($settingKey === 'footer') {
            foreach (['address', 'phone'] as $removed) {
                if (array_key_exists($removed, $stored)) {
                    unset($stored[$removed]);
                    $changed[] = $removed . ' (removed)';
                }
            }
        }
        if ($changed) {
            $log[] = run_step("Updated $settingKey wording: " . implode(', ', $changed), fn() => set_setting($settingKey, $stored));
        }
    }

    if (!$log) {
        $log[] = 'Database already up to date — nothing to change.';
    }
    $hasErrors = (bool) array_filter($log, fn($l) => str_starts_with($l, 'ERROR'));
    flash($hasErrors ? 'error' : 'success', implode(' · ', $log));
    redirect('/admin/migrate.php');
}

$page_title = 'Database Migration';
require __DIR__ . '/../includes/admin_layout_header.php';
?>
<div class="card card-pad">
  <h1 class="text-lg">Database Migration</h1>
  <p class="text-sm text-muted mt-2">Checks the live database against the current schema and adds any missing columns/tables (registration profile fields, password reset, email verification). Safe to run any number of times — existing data is never touched.</p>
  <form method="post" class="mt-4">
    <?= csrf_field() ?>
    <button class="btn btn-primary">Run Migration</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/admin_layout_footer.php'; ?>
