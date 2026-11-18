CREATE TABLE IF NOT EXISTS pos_shifts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 opening_cash DECIMAL(15,2) NOT NULL DEFAULT 0,
 closing_cash DECIMAL(15,2) NULL,
 expected_cash DECIMAL(15,2) NULL,
 cash_difference DECIMAL(15,2) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'open',
 opened_at DATETIME NOT NULL,
 closed_at DATETIME NULL,
 CONSTRAINT fk_pos_shift_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE RESTRICT,
 INDEX idx_pos_shifts_user_status(user_id,status,opened_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pos_cash_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 shift_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 direction VARCHAR(10) NOT NULL,
 amount DECIMAL(15,2) NOT NULL,
 reason VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_pos_cash_movement_shift FOREIGN KEY(shift_id) REFERENCES pos_shifts(id) ON DELETE CASCADE,
 CONSTRAINT fk_pos_cash_movement_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_pos_cash_movement_shift(shift_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders ADD COLUMN pos_shift_id BIGINT UNSIGNED NULL;
ALTER TABLE orders ADD INDEX idx_orders_pos_shift(pos_shift_id);

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('pos.view','POS ekranını görüntüle','pos',NOW()),
('pos.use','POS üzerinden satış ve kasa işlemi yap','pos',NOW()),
('pos.manage','POS kasa ve vardiya yönetimi','pos',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager','staff');
