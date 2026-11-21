<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);
$files=[
 'database/migrations/048_crm_workspace.sql',
 'database/migrations/049_crm_relationships.sql',
 'app/Modules/Customers/CrmWorkspace.php',
 'app/Views/customers/crm.php'
];
foreach ($files as $file) {
 if (!is_file($root.'/'.$file)) {fwrite(STDERR,"Missing CRM file: $file\n");exit(1);}
}
if (!class_exists(\KodVadAll\Modules\Customers\CrmWorkspace::class)) {fwrite(STDERR,"CRM autoload failed\n");exit(1);}
$controller=file_get_contents($root.'/app/Modules/Customers/CustomerController.php');
if (!str_contains($controller,'CrmWorkspace::handle($pdo, $admin, $id)')) {fwrite(STDERR,"CRM route missing\n");exit(1);}
$workspace=file_get_contents($root.'/app/Modules/Customers/CrmWorkspace.php');
foreach (['kd_csrf_check()', "kd_require_permission('customers.manage')", "customer_id=?", 'link_quote','link_task','stage','reopen'] as $required) {
 if (!str_contains($workspace,$required)) {fwrite(STDERR,"CRM check failed: $required\n");exit(1);}
}
$view=file_get_contents($root.'/app/Views/customers/crm.php');
foreach (['edit_contact','edit_opportunity','edit_followup','link_quote','link_task','Geciken açık takip'] as $required) {
 if (!str_contains($view,$required)) {fwrite(STDERR,"CRM view missing: $required\n");exit(1);}
}
$controller=file_get_contents($root.'/app/Modules/Customers/CrmWorkspace.php');
foreach (['link_followup_opportunity','SELECT id FROM crm_followups WHERE id=? AND customer_id=?','SELECT id FROM crm_opportunities WHERE id=? AND customer_id=?','UPDATE crm_followups SET opportunity_id=?'] as $required) {
 if (!str_contains($controller,$required)) {fwrite(STDERR,"CRM association missing: $required\n");exit(1);}
}
foreach (['link_followup_opportunity','Bağlantı yok','opportunity_id'] as $required) {
 if (!str_contains($view,$required)) {fwrite(STDERR,"CRM association UI missing: $required\n");exit(1);}
}
echo "CRM workspace smoke tests passed\n";
