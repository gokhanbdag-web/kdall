ALTER TABLE purchase_orders
 ADD COLUMN warehouse_id BIGINT UNSIGNED NULL AFTER supplier_id,
 ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'TRY' AFTER grand_total,
 ADD INDEX idx_purchase_orders_warehouse_status(warehouse_id,status);

UPDATE purchase_orders p
JOIN warehouses w ON w.is_default=1
SET p.warehouse_id=w.id
WHERE p.warehouse_id IS NULL;

ALTER TABLE purchase_orders
 ADD CONSTRAINT fk_purchase_order_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT;
