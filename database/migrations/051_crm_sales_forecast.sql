ALTER TABLE crm_opportunities ADD COLUMN probability TINYINT UNSIGNED NULL AFTER stage;
ALTER TABLE crm_opportunities ADD COLUMN expected_close_date DATE NULL AFTER probability;
ALTER TABLE crm_opportunities ADD INDEX idx_crm_forecast_close (expected_close_date,stage);
