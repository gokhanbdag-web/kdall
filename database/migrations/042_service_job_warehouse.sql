ALTER TABLE service_jobs
 ADD COLUMN warehouse_id BIGINT UNSIGNED NULL AFTER customer_id,
 ADD INDEX idx_service_jobs_warehouse_status(warehouse_id,status);

UPDATE service_jobs sj
JOIN warehouses w ON w.is_default=1
SET sj.warehouse_id=w.id
WHERE sj.warehouse_id IS NULL;

ALTER TABLE service_jobs
 ADD CONSTRAINT fk_service_job_warehouse FOREIGN KEY(warehouse_id) REFERENCES warehouses(id) ON DELETE RESTRICT;
