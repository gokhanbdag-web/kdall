CREATE TABLE IF NOT EXISTS production_recipes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(190) NOT NULL,
 output_product_id BIGINT UNSIGNED NOT NULL,
 batch_size DECIMAL(15,3) NOT NULL DEFAULT 1,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 notes VARCHAR(500) NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NULL,
 CONSTRAINT fk_production_recipe_output FOREIGN KEY(output_product_id) REFERENCES products(id) ON DELETE RESTRICT,
 CONSTRAINT fk_production_recipe_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_production_recipes_status(status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_recipe_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 recipe_id BIGINT UNSIGNED NOT NULL,
 component_product_id BIGINT UNSIGNED NOT NULL,
 quantity_per_batch DECIMAL(15,3) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_recipe_item_recipe FOREIGN KEY(recipe_id) REFERENCES production_recipes(id) ON DELETE CASCADE,
 CONSTRAINT fk_recipe_item_product FOREIGN KEY(component_product_id) REFERENCES products(id) ON DELETE RESTRICT,
 UNIQUE KEY uq_recipe_component(recipe_id,component_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_orders (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_number VARCHAR(40) NOT NULL UNIQUE,
 recipe_id BIGINT UNSIGNED NOT NULL,
 output_quantity DECIMAL(15,3) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'planned',
 planned_at DATE NULL,
 started_at DATETIME NULL,
 completed_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL,
 notes VARCHAR(500) NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_production_order_recipe FOREIGN KEY(recipe_id) REFERENCES production_recipes(id) ON DELETE RESTRICT,
 CONSTRAINT fk_production_order_user FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_production_orders_status(status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_order_materials (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 production_order_id BIGINT UNSIGNED NOT NULL,
 product_id BIGINT UNSIGNED NOT NULL,
 product_name VARCHAR(190) NOT NULL,
 quantity_required DECIMAL(15,3) NOT NULL,
 quantity_consumed DECIMAL(15,3) NOT NULL,
 created_at DATETIME NOT NULL,
 CONSTRAINT fk_production_material_order FOREIGN KEY(production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_production_material_product FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE RESTRICT,
 INDEX idx_production_material_order(production_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('production.view','Üretim emirlerini görüntüle','production',NOW()),
('production.manage','Üretim reçetesi ve emirlerini yönet','production',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('owner','admin','manager');
