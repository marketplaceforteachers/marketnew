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
    db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    return "Added column $table.$column";
}

$log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // users — registration/profile fields
    $userColumns = [
        'first_name' => 'VARCHAR(80) NULL AFTER name',
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
            db()->exec($ddl);
            $log[] = "Created table $name";
        }
    }

    // email_verifications.code_hash may exist from an older version of this table without it
    if (!column_exists('email_verifications', 'code_hash')) {
        db()->exec("ALTER TABLE email_verifications ADD COLUMN code_hash CHAR(64) NOT NULL AFTER token_hash");
        $log[] = 'Added column email_verifications.code_hash';
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
            db()->prepare('INSERT INTO email_templates (template_key, subject, html_body) VALUES (?, ?, ?)')
                ->execute([$key, $tpl['subject'], $tpl['html_body']]);
            $log[] = "Added email template: $key";
        }
    }

    if (!$log) {
        $log[] = 'Database already up to date — nothing to change.';
    }
    flash('success', implode(' · ', $log));
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
