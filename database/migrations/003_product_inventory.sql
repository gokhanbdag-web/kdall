CREATE TABLE IF NOT EXISTS product_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 slug VARCHAR(140) NOT NULL UNIQUE,
 description VARCHAR(255) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO product_categories (name,slug,status,created_at) VALUES ('Genel','genel','active',NOW());

ALTER TABLE products
 ADD COLUMN category_id BIGINT UNSIGNED NULL,
 ADD COLUMN barcode VARCHAR(80) NULL,
 ADD COLUMN unit VARCHAR(30) NOT NULL DEFAULT 'Adet',
 ADD COLUMN min_stock DECIMAL(15,3) NOT NULL DEFAULT 0,
 ADD COLUMN track_stock TINYINT(1) NOT NULL DEFAULT 1,
 ADD INDEX idx_products_category(category_id),
 ADD INDEX idx_products_barcode(barcode),
 ADD CONSTRAINT fk_products_category FOREIGN KEY(category_id) REFERENCES product_categories(id) ON DELETE SET NULL;

UPDATE products SET category_id=(SELECT id FROM product_categories WHERE slug='genel' LIMIT 1) WHERE category_id IS NULL;

CREATE TABLE IF NOT EXISTS product_stock_movements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 user_id BIGINT UNSIGNED NULL,
 movement_type VARCHAR(30) NOT NULL,
 quantity DECIMAL(15,3) NOT NULL,
 before_quantity DECIMAL(15,3) NOT NULL,
 after_quantity DECIMAL(15,3) NOT NULL,
 note VARCHAR(255) NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_stock_movements_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE,
 CONSTRAINT fk_stock_movements_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_stock_movements_product(product_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
