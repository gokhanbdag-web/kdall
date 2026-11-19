ALTER TABLE quote_items
 ADD COLUMN unit_cost DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER unit_price;

UPDATE quote_items qi
JOIN products p ON p.id=qi.product_id
SET qi.unit_cost=p.purchase_price
WHERE qi.unit_cost=0;
