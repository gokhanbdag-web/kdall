-- Cost snapshots keep sales and production margins stable when product purchase prices change.
ALTER TABLE order_items
 ADD COLUMN unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER unit_price;

ALTER TABLE invoice_items
 ADD COLUMN unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER unit_price;

ALTER TABLE production_orders
 ADD COLUMN labor_cost DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER notes,
 ADD COLUMN overhead_cost DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER labor_cost,
 ADD COLUMN material_cost DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER overhead_cost,
 ADD COLUMN total_cost DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER material_cost,
 ADD COLUMN unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER total_cost;

ALTER TABLE production_order_materials
 ADD COLUMN unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER quantity_consumed,
 ADD COLUMN line_cost DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER unit_cost;

INSERT IGNORE INTO accounting_accounts(code,name,account_type,is_active,created_at) VALUES
('730','Genel Üretim Giderleri','expense',1,NOW());

INSERT IGNORE INTO permissions (permission_key,name,module,created_at) VALUES
('production.cost.view','Üretim maliyetlerini görüntüle','production',NOW());
INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('owner','admin','manager') AND p.permission_key='production.cost.view';
