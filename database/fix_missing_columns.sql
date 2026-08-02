-- ============================================================
--  Adhaar â€“ The SoulServe  |  MIGRATION SCRIPT v2.0
--  Run this on any EXISTING install to upgrade to v2 schema.
--  Safe to run multiple times (uses IF NOT EXISTS / IGNORE).
-- ============================================================

USE adhaar_db;

-- â”€â”€ 1. register: add seller role & profile_photo â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
ALTER TABLE register
  MODIFY COLUMN role ENUM('donor','volunteer','seller') NOT NULL DEFAULT 'donor';

ALTER TABLE register
  ADD COLUMN profile_photo VARCHAR(300) DEFAULT NULL AFTER verified;

-- â”€â”€ 2. food_donations: expand status, add missing cols â”€â”€â”€â”€â”€â”€â”€
ALTER TABLE food_donations
  MODIFY COLUMN status ENUM(
    'pending','accepted','rejected',
    'scheduled','out_for_pickup','picked_up','delivered'
  ) NOT NULL DEFAULT 'pending';

ALTER TABLE food_donations
  ADD COLUMN priority ENUM('low','medium','high')
    NOT NULL DEFAULT 'medium' AFTER safe_hours;

ALTER TABLE food_donations
  ADD COLUMN notes TEXT NULL AFTER volunteer_email;

-- â”€â”€ 3. cloth_donations: expand status & condition â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
ALTER TABLE cloth_donations
  MODIFY COLUMN status ENUM(
    'pending','accepted','rejected',
    'scheduled','out_for_pickup','picked_up','delivered'
  ) NOT NULL DEFAULT 'pending';

ALTER TABLE cloth_donations
  MODIFY COLUMN condition_type
    ENUM('new','good','fair','worn') NOT NULL DEFAULT 'good';

ALTER TABLE cloth_donations
  ADD COLUMN notes TEXT NULL AFTER volunteer_email;

-- â”€â”€ 4. seller_stores â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS seller_stores (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_email     VARCHAR(180) NOT NULL UNIQUE,
  store_name       VARCHAR(180) NOT NULL,
  store_tagline    VARCHAR(300),
  store_category   ENUM('handicraft','textile','food_product','jewelry','art',
                        'pottery','organic','other') NOT NULL DEFAULT 'other',
  store_description TEXT,
  store_logo       VARCHAR(300),
  store_banner     VARCHAR(300),
  whatsapp         VARCHAR(20),
  upi_id           VARCHAR(100),
  bank_name        VARCHAR(120),
  bank_account     VARCHAR(30),
  bank_ifsc        VARCHAR(20),
  bank_holder_name VARCHAR(120),
  village          VARCHAR(120),
  district         VARCHAR(120),
  state            VARCHAR(80),
  pincode          VARCHAR(10),
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  is_verified      TINYINT(1) NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_seller (seller_email)
) ENGINE=InnoDB;

-- â”€â”€ 5. products â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS products (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_email   VARCHAR(180) NOT NULL,
  store_id       INT UNSIGNED,
  name           VARCHAR(220) NOT NULL,
  description    TEXT,
  category       ENUM('handicraft','textile','food_product','jewelry','art',
                      'pottery','organic','other') NOT NULL DEFAULT 'other',
  price          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  mrp            DECIMAL(10,2) DEFAULT NULL,
  stock          INT NOT NULL DEFAULT 0,
  image1         VARCHAR(300),
  image2         VARCHAR(300),
  image3         VARCHAR(300),
  weight_grams   INT DEFAULT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  total_sold     INT NOT NULL DEFAULT 0,
  avg_rating     DECIMAL(3,2) DEFAULT 0.00,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_seller   (seller_email),
  INDEX idx_category (category),
  INDEX idx_active   (is_active),
  FOREIGN KEY (store_id) REFERENCES seller_stores(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- â”€â”€ 6. cart â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS cart (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_email   VARCHAR(180) NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  added_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_cart (user_email, product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€ 7. orders â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_number     VARCHAR(30) NOT NULL UNIQUE,
  buyer_email      VARCHAR(180) NOT NULL,
  seller_email     VARCHAR(180) NOT NULL,
  total_amount     DECIMAL(10,2) NOT NULL,
  shipping_name    VARCHAR(150) NOT NULL,
  shipping_phone   VARCHAR(20)  NOT NULL,
  shipping_address TEXT         NOT NULL,
  shipping_city    VARCHAR(100) NOT NULL,
  shipping_state   VARCHAR(80)  NOT NULL,
  shipping_pincode VARCHAR(10)  NOT NULL,
  payment_method   ENUM('cod','upi','card') NOT NULL DEFAULT 'cod',
  payment_status   ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  order_status     ENUM('placed','confirmed','processing','shipped',
                        'out_for_delivery','delivered','cancelled',
                        'return_requested','returned') NOT NULL DEFAULT 'placed',
  tracking_id      VARCHAR(100) DEFAULT NULL,
  estimated_delivery DATE DEFAULT NULL,
  notes            TEXT,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_buyer  (buyer_email),
  INDEX idx_seller (seller_email),
  INDEX idx_status (order_status)
) ENGINE=InnoDB;

-- â”€â”€ 8. order_items â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  product_name VARCHAR(220) NOT NULL,
  price        DECIMAL(10,2) NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  image        VARCHAR(300),
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- â”€â”€ 9. product_reviews â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS product_reviews (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id     INT UNSIGNED NOT NULL,
  order_id       INT UNSIGNED NOT NULL,
  reviewer_email VARCHAR(180) NOT NULL,
  rating         TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  review_text    TEXT,
  is_verified    TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_review (product_id, order_id, reviewer_email),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- â”€â”€ 10. return_requests â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS return_requests (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id       INT UNSIGNED NOT NULL,
  product_id     INT UNSIGNED NOT NULL,
  buyer_email    VARCHAR(180) NOT NULL,
  seller_email   VARCHAR(180) NOT NULL,
  reason         ENUM('damaged','wrong_item','not_as_described',
                      'changed_mind','other') NOT NULL DEFAULT 'other',
  description    TEXT,
  images         VARCHAR(500),
  status         ENUM('requested','approved','rejected','pickup_scheduled',
                      'item_received','refund_initiated','refund_completed')
                 NOT NULL DEFAULT 'requested',
  admin_notes    TEXT,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- â”€â”€ 11. volunteer_tasks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS volunteer_tasks (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  volunteer_email VARCHAR(180) NOT NULL,
  donation_type   ENUM('food','cloth') NOT NULL,
  donation_id     INT UNSIGNED NOT NULL,
  assigned_by     VARCHAR(180) COMMENT 'admin email',
  task_status     ENUM('pending_acceptance','accepted','rejected',
                       'in_progress','completed') NOT NULL DEFAULT 'pending_acceptance',
  notes           TEXT,
  assigned_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at    DATETIME DEFAULT NULL,
  INDEX idx_volunteer (volunteer_email),
  INDEX idx_status    (task_status)
) ENGINE=InnoDB;

-- â”€â”€ 12. password_resets (safe no-op if exists) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(180) NOT NULL,
  token      VARCHAR(255) NOT NULL UNIQUE,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_token (token)
) ENGINE=InnoDB;

-- â”€â”€ Verify â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
SELECT 'Migration v2.0 complete.' AS status;
SHOW TABLES;

-- â”€â”€ 13. login_attempts (rate limiting) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS login_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(180) NOT NULL,
  ip         VARCHAR(45)  NOT NULL DEFAULT '',
  attempted_at DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_time  (attempted_at)
) ENGINE=InnoDB;

-- â”€â”€ 14. settlements (seller payout tracking) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS settlements (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_email   VARCHAR(180) NOT NULL,
  amount         DECIMAL(10,2) NOT NULL,
  method         ENUM('upi','bank','cash') NOT NULL DEFAULT 'upi',
  reference      VARCHAR(120) DEFAULT NULL  COMMENT 'UTR / txn ref',
  period_from    DATE NOT NULL,
  period_to      DATE NOT NULL,
  orders_count   INT NOT NULL DEFAULT 0,
  status         ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  notes          TEXT,
  settled_by     VARCHAR(180) NOT NULL COMMENT 'admin email',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at        DATETIME DEFAULT NULL,
  INDEX idx_seller (seller_email),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- â”€â”€ 15. ai_logs (AI decision audit trail) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS ai_logs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action_type  VARCHAR(60)  NOT NULL COMMENT 'auto_assign|validity_check|demand_forecast|rate_limit',
  input_data   TEXT,
  output_data  TEXT,
  confidence   DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 percent',
  triggered_by VARCHAR(180) DEFAULT NULL COMMENT 'admin/volunteer/system email',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_type (action_type),
  INDEX idx_time (created_at)
) ENGINE=InnoDB;

SELECT 'Migration v3.0 (AI + Settlements) complete.' AS status;

-- â”€â”€ 16. product_search_history (AI search tracking) â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS product_search_history (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_email   VARCHAR(180) NOT NULL,
  query        VARCHAR(300) NOT NULL,
  category     VARCHAR(80)  DEFAULT NULL,
  result_count INT          DEFAULT 0,
  searched_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_email),
  INDEX idx_time (searched_at)
) ENGINE=InnoDB;

-- â”€â”€ 17. product_view_history (AI browse tracking) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS product_view_history (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_email   VARCHAR(180) NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  view_count   INT          NOT NULL DEFAULT 1,
  last_viewed  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_view (user_email, product_id),
  INDEX idx_user    (user_email),
  INDEX idx_product (product_id),
  INDEX idx_time    (last_viewed)
) ENGINE=InnoDB;

-- â”€â”€ 18. Add pincode to register (for distance-based volunteer matching) â”€
ALTER TABLE register
  ADD COLUMN pincode VARCHAR(10) DEFAULT NULL AFTER address;

-- â”€â”€ 19. Add donor_pincode to donations (from pickup address) â”€
ALTER TABLE food_donations
  ADD COLUMN donor_pincode VARCHAR(10) DEFAULT NULL AFTER contact;

ALTER TABLE cloth_donations
  ADD COLUMN donor_pincode VARCHAR(10) DEFAULT NULL AFTER contact;

SELECT 'Migration v4.0 (Recommendations + Distance) complete.' AS status;


-- â”€â”€ 20. events_news (Admin-managed news & events) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
CREATE TABLE IF NOT EXISTS events_news (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(300)  NOT NULL,
  content      TEXT          NOT NULL,
  category     ENUM('event','news','drive','milestone') NOT NULL DEFAULT 'news',
  emoji        VARCHAR(10)   DEFAULT 'ðŸ“°',
  image        VARCHAR(300)  DEFAULT NULL,
  is_published TINYINT(1)    NOT NULL DEFAULT 1,
  event_date   DATE          DEFAULT NULL,
  created_by   VARCHAR(180)  NOT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_published (is_published),
  INDEX idx_category  (category)
) ENGINE=InnoDB;

-- â”€â”€ 21. delivery_proof column on donation tables â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
ALTER TABLE food_donations
  ADD COLUMN delivery_proof VARCHAR(300) DEFAULT NULL AFTER notes;

ALTER TABLE cloth_donations
  ADD COLUMN delivery_proof VARCHAR(300) DEFAULT NULL AFTER notes;

SELECT 'Migration v5.0 (Events/News + Delivery Proof) complete.' AS status;

