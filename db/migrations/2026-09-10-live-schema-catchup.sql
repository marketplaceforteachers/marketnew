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
