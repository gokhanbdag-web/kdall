ALTER TABLE service_job_parts
 ADD COLUMN unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER unit_price;

UPDATE service_job_parts sjp
JOIN products p ON p.id=sjp.product_id
SET sjp.unit_cost=p.purchase_price
WHERE sjp.unit_cost=0;
