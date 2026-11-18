ALTER TABLE orders ADD COLUMN discount_total DECIMAL(15,2) NOT NULL DEFAULT 0;
ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(60) NULL;
ALTER TABLE invoices ADD COLUMN discount_total DECIMAL(15,2) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS campaigns (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(150) NOT NULL,
 code VARCHAR(60) NOT NULL UNIQUE,
 description VARCHAR(255) NULL,
 discount_type VARCHAR(20) NOT NULL,
 discount_value DECIMAL(15,2) NOT NULL DEFAULT 0,
 minimum_order DECIMAL(15,2) NOT NULL DEFAULT 0,
 maximum_discount DECIMAL(15,2) NULL,
 usage_limit INT UNSIGNED NOT NULL DEFAULT 0,
 per_customer_limit INT UNSIGNED NOT NULL DEFAULT 0,
 usage_count INT UNSIGNED NOT NULL DEFAULT 0,
 starts_at DATETIME NOT NULL,
 ends_at DATETIME NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_campaign_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_campaign_status_dates(status,starts_at,ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS coupon_redemptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 campaign_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 customer_id BIGINT UNSIGNED NULL,
 discount_amount DECIMAL(15,2) NOT NULL,
 redeemed_at DATETIME NOT NULL,
 CONSTRAINT fk_coupon_redemption_campaign FOREIGN KEY(campaign_id) REFERENCES campaigns(id),
 CONSTRAINT fk_coupon_redemption_order FOREIGN KEY(order_id) REFERENCES orders(id),
 CONSTRAINT fk_coupon_redemption_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE SET NULL,
 UNIQUE KEY uq_coupon_redemption_order(order_id),
 INDEX idx_coupon_redemption_customer(campaign_id,customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gift_cards (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(60) NOT NULL UNIQUE,
 initial_balance DECIMAL(15,2) NOT NULL,
 balance DECIMAL(15,2) NOT NULL,
 currency CHAR(3) NOT NULL DEFAULT 'TRY',
 expires_at DATE NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 notes VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_gift_card_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_gift_card_status(status,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_accounts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL UNIQUE,
 points_balance BIGINT NOT NULL DEFAULT 0,
 updated_at DATETIME NULL,
 CONSTRAINT fk_loyalty_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS loyalty_transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 customer_id BIGINT UNSIGNED NOT NULL,
 order_id BIGINT UNSIGNED NULL,
 direction VARCHAR(10) NOT NULL,
 points BIGINT NOT NULL,
 description VARCHAR(255) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_loyalty_tx_customer FOREIGN KEY(customer_id) REFERENCES customers(id) ON DELETE CASCADE,
 CONSTRAINT fk_loyalty_tx_order FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE SET NULL,
 CONSTRAINT fk_loyalty_tx_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 UNIQUE KEY uq_loyalty_order(order_id),
 INDEX idx_loyalty_customer(customer_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('marketing.view','Kampanya ve sadakat kayıtlarını görüntüle','marketing',NOW()),
('marketing.manage','Kampanya ve sadakat kayıtlarını yönet','marketing',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('marketing.view','marketing.manage') WHERE r.slug='manager';
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key='marketing.view' WHERE r.slug='staff';
