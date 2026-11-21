<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Customers;
use PDO;
use KodVadAll\Core\NotificationService;

final class CrmReminderService
{
 public static function run(PDO $pdo): int
 {
  $sent=0;
  // Notify for overdue follow-ups and for those due within the next 24 hours.
  // A separate claim key per date and window prevents duplicates across cron runs.
  $stmt=$pdo->query("SELECT f.id,f.title,f.due_at,f.assigned_user_id,f.customer_id,c.name AS customer_name FROM crm_followups f JOIN customers c ON c.id=f.customer_id WHERE f.status='open' AND f.due_at<=DATE_ADD(NOW(),INTERVAL 1 DAY) ORDER BY f.due_at ASC LIMIT 500");
  $claim=$pdo->prepare("INSERT IGNORE INTO automation_logs (rule_key,reference_id,run_date,status,message,created_at) VALUES (?,?,CURDATE(),'done',?,NOW())");
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
   $overdue=strtotime((string)$item['due_at'])<time();
   $rule=$overdue?'crm_followup_overdue':'crm_followup_upcoming';
   $claim->execute([$rule,(string)$item['id'],'CRM takip hatırlatması']);
   if ($claim->rowCount()===0) continue;
   $title=$overdue?'Geciken CRM takibi':'Yaklaşan CRM takibi';
   $message=$item['customer_name'].' · '.$item['title'].' · '.date('d.m.Y H:i',strtotime((string)$item['due_at']));
   $url='index.php?page=customers&action=crm&id='.(int)$item['customer_id'];
   if (!empty($item['assigned_user_id'])) {
    NotificationService::notifyUser($pdo,(int)$item['assigned_user_id'],$title,$message,$url,'warning');
   } else {
    NotificationService::notifyRoles($pdo,['owner','admin','manager'],$title,$message,$url,'warning');
   }
   $sent++;
  }
  return $sent;
 }
}
