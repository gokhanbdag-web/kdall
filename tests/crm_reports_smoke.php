<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$report=file_get_contents($root.'/app/Modules/Customers/CrmReports.php');
$view=file_get_contents($root.'/app/Views/customers/crm_reports.php');
$controller=file_get_contents($root.'/app/Modules/Customers/CustomerController.php');
foreach (['kd_require_permission','BETWEEN ? AND ?','GROUP BY stage','GROUP BY status','GROUP BY DATE(created_at)','winRate','overdue'] as $required) {
 if (!str_contains($report,$required)) {fwrite(STDERR,"CRM report missing: $required\n");exit(1);}
}
foreach (['name="from"','name="to"','Satış hunisi','Takip performansı','Günlük fırsat hareketi'] as $required) {
 if (!str_contains($view,$required)) {fwrite(STDERR,"CRM report view missing: $required\n");exit(1);}
}
if (!str_contains($controller,"'crm_reports'")) {fwrite(STDERR,"CRM reports route missing\n");exit(1);}
echo "CRM reports smoke tests passed\n";
