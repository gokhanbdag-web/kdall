ALTER TABLE crm_contacts ADD INDEX idx_crm_contacts_customer_id (customer_id,id);
ALTER TABLE crm_opportunities ADD INDEX idx_crm_opportunities_customer_id (customer_id,id);
ALTER TABLE crm_opportunities ADD INDEX idx_crm_opportunities_stage_id (stage,id);
ALTER TABLE crm_followups ADD INDEX idx_crm_followups_customer_id (customer_id,id);
ALTER TABLE crm_followups ADD INDEX idx_crm_followups_customer_due (customer_id,due_at,id);
ALTER TABLE crm_followups ADD INDEX idx_crm_followups_assigned_due (assigned_user_id,due_at,id);
