ALTER TABLE production_orders
 ADD COLUMN warehouse_id BIGINT UNSIGNED NULL AFTER recipe_id,
 ADD INDEX idx_production_orders_warehouse_status(warehouse_id,status);

UPDATE production_orders po
JOIN warehouses w ON w.is_default=1
SET po.warehouse_id=w.id
WHERE po.warehouse_id IS NULL;

ALTER TABLE production_orders
 ADD CONSTRAINT fk_production_order_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT;
