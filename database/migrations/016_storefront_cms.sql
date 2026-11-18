CREATE TABLE IF NOT EXISTS site_pages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 content LONGTEXT NULL,
 meta_title VARCHAR(190) NULL,
 meta_description VARCHAR(255) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_site_page_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_site_pages_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_menus (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 location VARCHAR(20) NOT NULL DEFAULT 'header',
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 created_at DATETIME NOT NULL,
 INDEX idx_site_menus_location(location,status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_menu_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 menu_id BIGINT UNSIGNED NOT NULL,
 parent_id BIGINT UNSIGNED NULL,
 label VARCHAR(120) NOT NULL,
 url VARCHAR(500) NOT NULL,
 target VARCHAR(20) NOT NULL DEFAULT '_self',
 sort_order INT NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 CONSTRAINT fk_site_menu_item_menu FOREIGN KEY(menu_id) REFERENCES site_menus(id) ON DELETE CASCADE,
 CONSTRAINT fk_site_menu_item_parent FOREIGN KEY(parent_id) REFERENCES site_menu_items(id) ON DELETE SET NULL,
 INDEX idx_site_menu_items_order(menu_id,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_banners (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 subtitle VARCHAR(255) NULL,
 image_url VARCHAR(500) NULL,
 link_url VARCHAR(500) NULL,
 position VARCHAR(30) NOT NULL DEFAULT 'home_hero',
 sort_order INT NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 starts_at DATETIME NULL,
 ends_at DATETIME NULL,
 created_at DATETIME NOT NULL,
 INDEX idx_site_banners_position(position,status,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 title VARCHAR(190) NOT NULL,
 slug VARCHAR(190) NOT NULL UNIQUE,
 excerpt VARCHAR(500) NULL,
 content LONGTEXT NULL,
 meta_title VARCHAR(190) NULL,
 meta_description VARCHAR(255) NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'draft',
 published_at DATETIME NULL,
 author_id BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_blog_author FOREIGN KEY(author_id) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_blog_posts_status(status,published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE orders ADD COLUMN order_source VARCHAR(30) NOT NULL DEFAULT 'admin';
ALTER TABLE products ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('site.view','Site içeriklerini görüntüle','cms',NOW()),
('site.manage','Site içeriklerini yönet','cms',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin');
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r JOIN permissions p ON p.permission_key IN ('site.view','site.manage') WHERE r.slug='manager';
