-- One-off catch-up for the live marketpl_marketnew database, which had drifted from
-- db/schema.sql: it predated several tables/columns and was missing them entirely,
-- causing every signup and login attempt to fail with an uncaught PDOException (HTTP 500).
-- Applied directly via phpMyAdmin on 2026-09-10. Kept here for the record — the
-- admin/migrate.php tool now covers the same ground and is safe to re-run any time.

CREATE TABLE IF NOT EXISTS login_attempts (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address      VARCHAR(45)       NOT NULL,
  attempted_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED   NOT NULL,
  token_hash      CHAR(64)          NOT NULL,
  expires_at      TIMESTAMP         NOT NULL,
  used_at         TIMESTAMP         NULL,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_password_resets_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_posts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_verifications (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED   NOT NULL,
  token_hash      CHAR(64)          NOT NULL,
  code_hash       CHAR(64)          NOT NULL,
  expires_at      TIMESTAMP         NOT NULL,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_email_verifications_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users ADD COLUMN IF NOT EXISTS first_name VARCHAR(80) NULL AFTER name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_name VARCHAR(80) NULL AFTER first_name;
ALTER TABLE users ADD COLUMN IF NOT EXISTS account_type VARCHAR(30) NULL AFTER role;
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS school_name VARCHAR(200) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS school_email VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS district VARCHAR(200) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS address_line1 VARCHAR(200) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(2) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS zip_code VARCHAR(12) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS store_name VARCHAR(150) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio VARCHAR(500) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS stripe_account_id VARCHAR(255) NULL;

ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_name VARCHAR(150) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_phone VARCHAR(30) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_city VARCHAR(120) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_state VARCHAR(2) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_zip VARCHAR(12) NULL;

-- 2026-09-13 follow-up: the initial catch-up above missed two email templates that
-- admin/migrate.php also adds, since it was applied via raw SQL rather than the tool
-- itself. Without 'email_verification', every signup's welcome email sent fine but the
-- verification email silently failed (send_transactional_email finds no template row and
-- returns a 'failed' status without ever attempting delivery) — so users never got a way
-- to verify. Also switches delivery to PHP mail() since no Resend API key is configured yet.
INSERT INTO email_templates (template_key, subject, html_body)
SELECT 'password_reset', 'Reset your password',
'<p>Hi {{name}},</p><p>Click the button below to reset your password. This link expires in 1 hour.</p><p style="text-align:center;margin:28px 0;"><a href="{{reset_url}}" style="background:#1d4ed8;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;">Reset Password</a></p><p>If you didn''t request this, you can safely ignore this email.</p>'
WHERE NOT EXISTS (SELECT 1 FROM email_templates WHERE template_key = 'password_reset');

INSERT INTO email_templates (template_key, subject, html_body)
SELECT 'email_verification', 'Verify your email address',
'<p>Hi {{name}},</p><p>Thanks for joining! Click the button below to verify your email, or enter this code on the verification page:</p><p style="text-align:center;font-size:28px;font-weight:700;letter-spacing:4px;margin:20px 0;">{{code}}</p><p style="text-align:center;margin:28px 0;"><a href="{{verify_url}}" style="background:#1d4ed8;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;">Verify Email</a></p><p>This code and link expire in 1 hour.</p>'
WHERE NOT EXISTS (SELECT 1 FROM email_templates WHERE template_key = 'email_verification');

UPDATE site_settings SET value_json = '{"method":"php_mail"}' WHERE setting_key = 'mail_delivery';
