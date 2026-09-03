-- MarketplaceForTeachers.com (PHP edition) — core relational schema (MySQL 5.7+/8.0+/MariaDB 10.2+)
-- Applied automatically by install.php, or run manually against your database.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. users
CREATE TABLE IF NOT EXISTS users (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120)      NOT NULL,
  first_name      VARCHAR(80),
  last_name       VARCHAR(80),
  email           VARCHAR(255)      NOT NULL,
  password_hash   VARCHAR(255)      NOT NULL,
  role            ENUM('teacher','buyer','admin') NOT NULL DEFAULT 'buyer',
  account_type    VARCHAR(30),
  phone           VARCHAR(30),
  is_verified     TINYINT(1)        NOT NULL DEFAULT 0,
  email_verified_at TIMESTAMP       NULL,
  school_name     VARCHAR(200),
  school_email    VARCHAR(255),
  district        VARCHAR(200),
  address_line1   VARCHAR(200),
  city            VARCHAR(120),
  state           VARCHAR(2),
  zip_code        VARCHAR(12),
  store_name      VARCHAR(150),
  avatar_url      VARCHAR(500),
  bio             VARCHAR(500),
  stripe_account_id VARCHAR(255),
  is_banned       TINYINT(1)        NOT NULL DEFAULT 0,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 1b. login_attempts (brute-force throttling) & password_resets
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

CREATE TABLE IF NOT EXISTS email_verifications (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED   NOT NULL,
  token_hash      CHAR(64)          NOT NULL,
  expires_at      TIMESTAMP         NOT NULL,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_email_verifications_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. teacher_verifications
CREATE TABLE IF NOT EXISTS teacher_verifications (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         BIGINT UNSIGNED   NOT NULL,
  school_email    VARCHAR(255),
  document_url    VARCHAR(500),
  status          ENUM('pending','under_review','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewer_notes  TEXT,
  submitted_at    TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_tv_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. categories
CREATE TABLE IF NOT EXISTS categories (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(100)      NOT NULL,
  slug            VARCHAR(120)      NOT NULL,
  description     VARCHAR(500),
  icon            VARCHAR(60),
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. listings
CREATE TABLE IF NOT EXISTS listings (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id       BIGINT UNSIGNED   NOT NULL,
  category_id     BIGINT UNSIGNED   NOT NULL,
  title           VARCHAR(200)      NOT NULL,
  slug            VARCHAR(220)      NOT NULL,
  description     TEXT              NOT NULL,
  price           DECIMAL(10,2)     NOT NULL DEFAULT 0,
  grade_level     VARCHAR(50)       NOT NULL,
  condition_type  ENUM('new','like_new','good','fair','digital_download') NOT NULL,
  shipping_type   ENUM('carrier','local_pickup','both') NOT NULL DEFAULT 'both',
  shipping_fee    DECIMAL(10,2)     NOT NULL DEFAULT 0,
  is_active       TINYINT(1)        NOT NULL DEFAULT 1,
  view_count      INT UNSIGNED      NOT NULL DEFAULT 0,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_listings_slug (slug),
  CONSTRAINT fk_listings_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_listings_category FOREIGN KEY (category_id) REFERENCES categories(id),
  KEY idx_listings_active_created (is_active, created_at),
  KEY idx_listings_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. listing_images
CREATE TABLE IF NOT EXISTS listing_images (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id      BIGINT UNSIGNED   NOT NULL,
  image_url       VARCHAR(500)      NOT NULL,
  is_primary      TINYINT(1)        NOT NULL DEFAULT 0,
  CONSTRAINT fk_li_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
  KEY idx_li_listing (listing_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. orders
CREATE TABLE IF NOT EXISTS orders (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  buyer_id          BIGINT UNSIGNED   NOT NULL,
  total_amount      DECIMAL(10,2)     NOT NULL,
  shipping_amount   DECIMAL(10,2)     NOT NULL DEFAULT 0,
  tax_amount        DECIMAL(10,2)     NOT NULL DEFAULT 0,
  status            ENUM('pending','paid','shipped','delivered','completed','cancelled','disputed') NOT NULL DEFAULT 'pending',
  shipping_name     VARCHAR(150),
  shipping_phone    VARCHAR(30),
  shipping_address  TEXT,
  shipping_city     VARCHAR(120),
  shipping_state    VARCHAR(2),
  shipping_zip      VARCHAR(12),
  payment_gateway   ENUM('stripe','paypal','school_po') NOT NULL DEFAULT 'stripe',
  payment_reference VARCHAR(255),
  created_at        TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
  KEY idx_orders_buyer (buyer_id),
  KEY idx_orders_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. order_items
CREATE TABLE IF NOT EXISTS order_items (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED   NOT NULL,
  listing_id      BIGINT UNSIGNED   NOT NULL,
  seller_id       BIGINT UNSIGNED   NOT NULL,
  quantity        INT UNSIGNED      NOT NULL DEFAULT 1,
  price           DECIMAL(10,2)     NOT NULL,
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_listing FOREIGN KEY (listing_id) REFERENCES listings(id),
  CONSTRAINT fk_oi_seller FOREIGN KEY (seller_id) REFERENCES users(id),
  KEY idx_oi_order (order_id),
  KEY idx_oi_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. payments
CREATE TABLE IF NOT EXISTS payments (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED   NOT NULL,
  gateway_tx_id   VARCHAR(255),
  amount          DECIMAL(10,2)     NOT NULL,
  status          ENUM('pending','succeeded','failed','refunded') NOT NULL DEFAULT 'pending',
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  KEY idx_payments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. seller_payouts
CREATE TABLE IF NOT EXISTS seller_payouts (
  id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id           BIGINT UNSIGNED   NOT NULL,
  order_id            BIGINT UNSIGNED   NOT NULL,
  payout_amount       DECIMAL(10,2)     NOT NULL,
  fee_amount          DECIMAL(10,2)     NOT NULL DEFAULT 0,
  status              ENUM('pending','in_transit','paid','failed') NOT NULL DEFAULT 'pending',
  stripe_transfer_id  VARCHAR(255),
  created_at          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payouts_seller FOREIGN KEY (seller_id) REFERENCES users(id),
  CONSTRAINT fk_payouts_order FOREIGN KEY (order_id) REFERENCES orders(id),
  KEY idx_payouts_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. disputes
CREATE TABLE IF NOT EXISTS disputes (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED   NOT NULL,
  raised_by       BIGINT UNSIGNED   NOT NULL,
  reason          VARCHAR(200)      NOT NULL,
  description     TEXT,
  evidence_url    VARCHAR(500),
  status          ENUM('open','seller_response','under_arbitration','resolved') NOT NULL DEFAULT 'open',
  resolution      ENUM('full_refund','partial_refund','fund_release','none') NOT NULL DEFAULT 'none',
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_disputes_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_disputes_user FOREIGN KEY (raised_by) REFERENCES users(id),
  KEY idx_disputes_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. wishlists & wishlist_items
CREATE TABLE IF NOT EXISTS wishlists (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id      BIGINT UNSIGNED   NOT NULL,
  title           VARCHAR(200)      NOT NULL,
  grade           VARCHAR(50),
  school          VARCHAR(200),
  goal_amount     DECIMAL(10,2)     NOT NULL DEFAULT 0,
  raised_amount   DECIMAL(10,2)     NOT NULL DEFAULT 0,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wishlists_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_wishlists_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS wishlist_items (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  wishlist_id     BIGINT UNSIGNED   NOT NULL,
  listing_id      BIGINT UNSIGNED,
  item_name       VARCHAR(200)      NOT NULL,
  price           DECIMAL(10,2)     NOT NULL DEFAULT 0,
  is_funded       TINYINT(1)        NOT NULL DEFAULT 0,
  CONSTRAINT fk_wi_wishlist FOREIGN KEY (wishlist_id) REFERENCES wishlists(id) ON DELETE CASCADE,
  CONSTRAINT fk_wi_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  KEY idx_wi_wishlist (wishlist_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. fundraising_campaigns & donations
CREATE TABLE IF NOT EXISTS fundraising_campaigns (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  teacher_id      BIGINT UNSIGNED   NOT NULL,
  title           VARCHAR(200)      NOT NULL,
  story           TEXT,
  target_funds    DECIMAL(10,2)     NOT NULL,
  current_funds   DECIMAL(10,2)     NOT NULL DEFAULT 0,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_campaigns_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_campaigns_teacher (teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS donations (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id     BIGINT UNSIGNED   NOT NULL,
  donor_name      VARCHAR(120),
  donor_email     VARCHAR(255),
  amount          DECIMAL(10,2)     NOT NULL,
  receipt_url     VARCHAR(500),
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_donations_campaign FOREIGN KEY (campaign_id) REFERENCES fundraising_campaigns(id) ON DELETE CASCADE,
  KEY idx_donations_campaign (campaign_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. message_threads & messages
CREATE TABLE IF NOT EXISTS message_threads (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  listing_id      BIGINT UNSIGNED,
  buyer_id        BIGINT UNSIGNED   NOT NULL,
  seller_id       BIGINT UNSIGNED   NOT NULL,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mt_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  CONSTRAINT fk_mt_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
  CONSTRAINT fk_mt_seller FOREIGN KEY (seller_id) REFERENCES users(id),
  KEY idx_mt_buyer (buyer_id),
  KEY idx_mt_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  thread_id       BIGINT UNSIGNED   NOT NULL,
  sender_id       BIGINT UNSIGNED   NOT NULL,
  recipient_id    BIGINT UNSIGNED   NOT NULL,
  listing_id      BIGINT UNSIGNED,
  body            TEXT              NOT NULL,
  is_read         TINYINT(1)        NOT NULL DEFAULT 0,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_msg_thread FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id),
  CONSTRAINT fk_msg_recipient FOREIGN KEY (recipient_id) REFERENCES users(id),
  CONSTRAINT fk_msg_listing FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE SET NULL,
  KEY idx_msg_thread (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. reviews
CREATE TABLE IF NOT EXISTS reviews (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id        BIGINT UNSIGNED   NOT NULL,
  reviewer_id     BIGINT UNSIGNED   NOT NULL,
  seller_id       BIGINT UNSIGNED   NOT NULL,
  rating          TINYINT UNSIGNED  NOT NULL,
  comment         TEXT,
  seller_reply    TEXT,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id),
  CONSTRAINT fk_reviews_seller FOREIGN KEY (seller_id) REFERENCES users(id),
  KEY idx_reviews_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. email_templates & email_logs
CREATE TABLE IF NOT EXISTS email_templates (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key    VARCHAR(100)      NOT NULL,
  subject         VARCHAR(255)      NOT NULL,
  html_body       LONGTEXT          NOT NULL,
  updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_templates_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key    VARCHAR(100)      NOT NULL,
  recipient       VARCHAR(255)      NOT NULL,
  status          ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  sent_at         TIMESTAMP         NULL,
  KEY idx_email_logs_template (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15b. email drip campaigns (automated multi-step sequences triggered by user events)
CREATE TABLE IF NOT EXISTS email_drips (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(150)      NOT NULL,
  trigger_event   VARCHAR(60)       NOT NULL,
  is_enabled      TINYINT(1)        NOT NULL DEFAULT 1,
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_drips_name (name),
  KEY idx_drips_trigger (trigger_event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_drip_steps (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  drip_id         BIGINT UNSIGNED   NOT NULL,
  step_order      INT UNSIGNED      NOT NULL DEFAULT 0,
  delay_hours     INT UNSIGNED      NOT NULL DEFAULT 0,
  template_key    VARCHAR(100)      NOT NULL,
  CONSTRAINT fk_drip_steps_drip FOREIGN KEY (drip_id) REFERENCES email_drips(id) ON DELETE CASCADE,
  UNIQUE KEY uq_drip_step (drip_id, step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_drip_enrollments (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  drip_id         BIGINT UNSIGNED   NOT NULL,
  user_id         BIGINT UNSIGNED   NOT NULL,
  status          ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
  enrolled_at     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_drip_enroll_drip FOREIGN KEY (drip_id) REFERENCES email_drips(id) ON DELETE CASCADE,
  CONSTRAINT fk_drip_enroll_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_drip_enroll_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_drip_sends (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  enrollment_id   BIGINT UNSIGNED   NOT NULL,
  step_id         BIGINT UNSIGNED   NOT NULL,
  sent_at         TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_drip_sends_enrollment FOREIGN KEY (enrollment_id) REFERENCES email_drip_enrollments(id) ON DELETE CASCADE,
  CONSTRAINT fk_drip_sends_step FOREIGN KEY (step_id) REFERENCES email_drip_steps(id) ON DELETE CASCADE,
  UNIQUE KEY uq_drip_send (enrollment_id, step_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. admin_audit_logs
CREATE TABLE IF NOT EXISTS admin_audit_logs (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id        BIGINT UNSIGNED   NOT NULL,
  action          VARCHAR(120)      NOT NULL,
  target_type     VARCHAR(60)       NOT NULL,
  target_id       VARCHAR(120),
  ip_address      VARCHAR(45),
  created_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id),
  KEY idx_audit_admin (admin_id),
  KEY idx_audit_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. site_settings — admin-editable site content/config, read by every page
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key     VARCHAR(60)       NOT NULL PRIMARY KEY,
  value_json      JSON              NOT NULL,
  is_public       TINYINT(1)        NOT NULL DEFAULT 1,
  updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. payment_gateway_configs — admin-entered gateway credentials, server-only
CREATE TABLE IF NOT EXISTS payment_gateway_configs (
  gateway         ENUM('stripe','paypal','school_po') NOT NULL PRIMARY KEY,
  is_enabled      TINYINT(1)        NOT NULL DEFAULT 0,
  config_json     JSON              NOT NULL,
  updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. integration_configs — admin-entered API credentials for non-payment integrations (e.g. Resend)
CREATE TABLE IF NOT EXISTS integration_configs (
  integration_key VARCHAR(60)      NOT NULL PRIMARY KEY,
  config_json     JSON              NOT NULL,
  updated_at      TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
