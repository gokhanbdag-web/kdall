<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$history=file_get_contents($root.'/app/Modules/Customers/CrmHistory.php');
$workspace=file_get_contents($root.'/app/Modules/Customers/CrmWorkspace.php');
$view=file_get_contents($root.'/app/Views/customers/crm.php');
$migration=file_get_contents($root.'/database/migrations/052_crm_activity_history.sql');
foreach (['customer_id=?','json_encode','crm_activity_history','crm_contacts','crm_opportunities','crm_followups'] as $part) {
 if (!str_contains($history,$part)) {fwrite(STDERR,"CRM history missing: $part\n");exit(1);}
}
foreach (['beginTransaction','rollBack','commit','CrmHistory::snapshot','CrmHistory::record','crm_activity_history'] as $part) {
 if (!str_contains($workspace,$part) && !str_contains($migration,$part)) {fwrite(STDERR,"CRM transaction/history missing: $part\n");exit(1);}
}
if (!str_contains($view,'CRM işlem geçmişi')) {fwrite(STDERR,"CRM history view missing\n");exit(1);}
echo "CRM history smoke tests passed\n";
