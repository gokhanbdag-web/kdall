<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Customers;
use PDO;
final class CrmReports
{
 public static function handle(PDO $pdo):void
 {
  kd_require_permission('customers.view');
  $from=(string)($_GET['from']??date('Y-m-01'));
  $to=(string)($_GET['to']??date('Y-m-d'));
  foreach (['from','to'] as $field) {
   $value=$field==='from'?$from:$to;
   $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
   if (!$parsed || $parsed->format('Y-m-d')!==$value) {
    if ($field==='from') $from=date('Y-m-01'); else $to=date('Y-m-d');
   }
  }
  if ($from>$to) [$from,$to]=[$to,$from];
  $range=[$from.' 00:00:00',$to.' 23:59:59'];
  $stmt=$pdo->prepare("SELECT stage,COUNT(*) AS total,COALESCE(SUM(amount),0) AS amount FROM crm_opportunities WHERE created_at BETWEEN ? AND ? GROUP BY stage ORDER BY FIELD(stage,'lead','qualified','proposal','negotiation','won','lost')");
  $stmt->execute($range);$pipeline=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $wins=0;$losses=0;$wonValue=0.0;
  foreach ($pipeline as $item) {
   if ($item['stage']==='won') {$wins=(int)$item['total'];$wonValue=(float)$item['amount'];}
   if ($item['stage']==='lost') $losses=(int)$item['total'];
  }
  $closed=$wins+$losses;$winRate=$closed>0?round($wins*100/$closed,1):0.0;
  $stmt=$pdo->prepare("SELECT status,COUNT(*) AS total FROM crm_followups WHERE due_at BETWEEN ? AND ? GROUP BY status");
  $stmt->execute($range);$followupStatus=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $stmt=$pdo->prepare("SELECT COUNT(*) FROM crm_followups WHERE status='open' AND due_at BETWEEN ? AND ? AND due_at<NOW()");
  $stmt->execute($range);$overdue=(int)$stmt->fetchColumn();
  $stmt=$pdo->prepare("SELECT DATE(created_at) AS report_date,COUNT(*) AS total,COALESCE(SUM(amount),0) AS amount FROM crm_opportunities WHERE created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY report_date");
  $stmt->execute($range);$daily=$stmt->fetchAll(PDO::FETCH_ASSOC);
  $pageTitle='CRM Raporları';
  ob_start();require KD_ROOT.'/app/Views/customers/crm_reports.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
