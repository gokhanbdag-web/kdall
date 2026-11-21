ALTER TABLE crm_opportunities ADD COLUMN quote_id BIGINT UNSIGNED NULL;
ALTER TABLE crm_followups ADD COLUMN task_id BIGINT UNSIGNED NULL;
ALTER TABLE crm_followups ADD COLUMN opportunity_id BIGINT UNSIGNED NULL;
ALTER TABLE crm_opportunities ADD INDEX idx_crm_opportunity_quote (quote_id);
ALTER TABLE crm_followups ADD INDEX idx_crm_followup_task (task_id);
ALTER TABLE crm_followups ADD INDEX idx_crm_followup_opportunity (opportunity_id);
ALTER TABLE crm_opportunities ADD CONSTRAINT fk_crm_opportunity_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL;
ALTER TABLE crm_followups ADD CONSTRAINT fk_crm_followup_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL;
ALTER TABLE crm_followups ADD CONSTRAINT fk_crm_followup_opportunity FOREIGN KEY (opportunity_id) REFERENCES crm_opportunities(id) ON DELETE SET NULL;
