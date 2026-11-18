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
('335','Personele Borçlar','liability',1,NOW());

