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
foreach (['delete_opportunity','delete_followup','confirm_delete','customer_id=?','kd_audit'] as $required) {
 if (!str_contains($controller,$required) || (!str_contains($view,$required) && in_array($required,['delete_opportunity','delete_followup','confirm_delete'],true))) {fwrite(STDERR,"CRM deletion missing: $required\n");exit(1);}
}
$dashboard=file_get_contents($root.'/app/Modules/Customers/CrmDashboard.php');
$dashboardView=file_get_contents($root.'/app/Views/customers/crm_dashboard.php');
$customerController=file_get_contents($root.'/app/Modules/Customers/CustomerController.php');
foreach (['crm_dashboard','CrmDashboard::handle'] as $required) {
 if (!str_contains($customerController,$required)) {fwrite(STDERR,"CRM dashboard route missing: $required\n");exit(1);}
}
foreach (['crm_opportunities','crm_followups','crm_contacts','customers.view'] as $required) {
 if (!str_contains($dashboard,$required)) {fwrite(STDERR,"CRM dashboard query missing: $required\n");exit(1);}
}
foreach (['$opportunities','$followups','$contacts','CRM Merkezi'] as $required) {
 if (!str_contains($dashboardView,$required)) {fwrite(STDERR,"CRM dashboard UI missing: $required\n");exit(1);}
}
echo "CRM workspace smoke tests passed\n";
