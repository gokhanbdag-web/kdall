ALTER TABLE orders
 ADD COLUMN warehouse_id BIGINT UNSIGNED NULL AFTER customer_id,
 ADD INDEX idx_orders_warehouse_status(warehouse_id,status);

UPDATE orders o
JOIN warehouses w ON w.is_default=1
SET o.warehouse_id=w.id
WHERE o.warehouse_id IS NULL;

ALTER TABLE orders
 ADD CONSTRAINT fk_orders_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT;
