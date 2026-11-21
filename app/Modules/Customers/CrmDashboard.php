<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Customers;
use PDO;
final class CrmDashboard {
 public static function handle(PDO $pdo): void {
  kd_require_permission('customers.view');
  $stats=[];
  foreach (['customers'=>'customers','contacts'=>'crm_contacts','opportunities'=>'crm_opportunities','followups'=>'crm_followups'] as $key=>$table) {
   $stats[$key]=(int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
  }
  $stats['open_followups']=(int)$pdo->query("SELECT COUNT(*) FROM crm_followups WHERE status='open'")->fetchColumn();
  $stats['overdue']=(int)$pdo->query("SELECT COUNT(*) FROM crm_followups WHERE status='open' AND due_at<NOW()")->fetchColumn();
  $opportunities=$pdo->query("SELECT o.id,o.title,o.stage,o.amount,c.id AS customer_id,c.name AS customer_name FROM crm_opportunities o JOIN customers c ON c.id=o.customer_id ORDER BY o.id DESC LIMIT 50")->fetchAll();
  $followups=$pdo->query("SELECT f.id,f.title,f.due_at,f.status,c.id AS customer_id,c.name AS customer_name FROM crm_followups f JOIN customers c ON c.id=f.customer_id ORDER BY (f.status='open') DESC,f.due_at ASC LIMIT 50")->fetchAll();
  $contacts=$pdo->query("SELECT p.id,p.name,p.role,p.email,c.id AS customer_id,c.name AS customer_name FROM crm_contacts p JOIN customers c ON c.id=p.customer_id ORDER BY p.id DESC LIMIT 50")->fetchAll();
  $pageTitle='CRM Merkezi';
  ob_start();require KD_ROOT.'/app/Views/customers/crm_dashboard.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
