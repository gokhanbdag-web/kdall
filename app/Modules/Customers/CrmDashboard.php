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
  $q=trim((string)($_GET['q']??''));
  $stage=(string)($_GET['stage']??'');
  $status=(string)($_GET['status']??'');
  $customerId=max(0,(int)($_GET['customer_id']??0));
  $from=(string)($_GET['from']??'');$to=(string)($_GET['to']??'');
  $minAmount=trim((string)($_GET['min_amount']??''));
  $maxAmount=trim((string)($_GET['max_amount']??''));
  $assignedUserId=max(0,(int)($_GET['assigned_user_id']??0));
  if (!in_array($stage,['','lead','qualified','proposal','negotiation','won','lost'],true)) $stage='';
  if (!in_array($status,['','open','done'],true)) $status='';
  foreach (['from','to'] as $key) {
   $value=$key==='from'?$from:$to;
   $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);
   if ($value!=='' && (!$parsed || $parsed->format('Y-m-d')!==$value)) {
    if ($key==='from') $from=''; else $to='';
   }
  }
  if ($minAmount!=='' && (!is_numeric($minAmount) || (float)$minAmount<0)) $minAmount='';
  if ($maxAmount!=='' && (!is_numeric($maxAmount) || (float)$maxAmount<0)) $maxAmount='';
  $like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$q).'%';
  $oppWhere=[];$oppParams=[];
  if ($customerId>0) {$oppWhere[]='o.customer_id=?';$oppParams[]=$customerId;}
  if ($q!=='') {$oppWhere[]="(o.title LIKE ? OR c.name LIKE ?)";array_push($oppParams,$like,$like);}
  if ($stage!=='') {$oppWhere[]='o.stage=?';$oppParams[]=$stage;}
  if ($minAmount!=='') {$oppWhere[]='o.amount>=?';$oppParams[]=(float)$minAmount;}
  if ($maxAmount!=='') {$oppWhere[]='o.amount<=?';$oppParams[]=(float)$maxAmount;}
  if ($from!=='') {$oppWhere[]='DATE(o.created_at)>=?';$oppParams[]=$from;}
  if ($to!=='') {$oppWhere[]='DATE(o.created_at)<=?';$oppParams[]=$to;}
  $sql='SELECT o.id,o.title,o.stage,o.amount,c.id AS customer_id,c.name AS customer_name FROM crm_opportunities o JOIN customers c ON c.id=o.customer_id'.($oppWhere?' WHERE '.implode(' AND ',$oppWhere):'').' ORDER BY o.id DESC LIMIT 50';
  $stmt=$pdo->prepare($sql);$stmt->execute($oppParams);$opportunities=$stmt->fetchAll();
  $followWhere=[];$followParams=[];
  if ($customerId>0) {$followWhere[]='f.customer_id=?';$followParams[]=$customerId;}
  if ($q!=='') {$followWhere[]='(f.title LIKE ? OR c.name LIKE ?)';array_push($followParams,$like,$like);}
  if ($status!=='') {$followWhere[]='f.status=?';$followParams[]=$status;}
  if ($assignedUserId>0) {$followWhere[]='f.assigned_user_id=?';$followParams[]=$assignedUserId;}
  if ($from!=='') {$followWhere[]='DATE(f.due_at)>=?';$followParams[]=$from;}
  if ($to!=='') {$followWhere[]='DATE(f.due_at)<=?';$followParams[]=$to;}
  $sql='SELECT f.id,f.title,f.due_at,f.status,c.id AS customer_id,c.name AS customer_name FROM crm_followups f JOIN customers c ON c.id=f.customer_id'.($followWhere?' WHERE '.implode(' AND ',$followWhere):'')." ORDER BY (f.status='open') DESC,f.due_at ASC LIMIT 50";
  $stmt=$pdo->prepare($sql);$stmt->execute($followParams);$followups=$stmt->fetchAll();
  $contactWhere=[];$contactParams=[];
  if ($customerId>0) {$contactWhere[]='p.customer_id=?';$contactParams[]=$customerId;}
  if ($q!=='') {$contactWhere[]='(p.name LIKE ? OR p.email LIKE ? OR c.name LIKE ?)';array_push($contactParams,$like,$like,$like);}
  $sql='SELECT p.id,p.name,p.role,p.email,c.id AS customer_id,c.name AS customer_name FROM crm_contacts p JOIN customers c ON c.id=p.customer_id'.($contactWhere?' WHERE '.implode(' AND ',$contactWhere):'').' ORDER BY p.id DESC LIMIT 50';
  $stmt=$pdo->prepare($sql);$stmt->execute($contactParams);$contacts=$stmt->fetchAll();
  $pageTitle='CRM Merkezi';
  ob_start();require KD_ROOT.'/app/Views/customers/crm_dashboard.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
