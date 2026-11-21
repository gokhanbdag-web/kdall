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
  $perPage=25;
  $pagination=[];
  $paginate=static function(PDO $pdo,string $fromSql,array $where,array $params,string $pageKey) use ($perPage,&$pagination): string {
   $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
   $count=$pdo->prepare('SELECT COUNT(*) '.$fromSql.$whereSql);
   $count->execute($params);
   $total=(int)$count->fetchColumn();
   $pages=max(1,(int)ceil($total/$perPage));
   $page=min($pages,max(1,(int)($_GET[$pageKey]??1)));
   $pagination[$pageKey]=['page'=>$page,'pages'=>$pages,'total'=>$total];
   return ' LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage);
  };
  $oppWhere=[];$oppParams=[];
  if ($customerId>0) {$oppWhere[]='o.customer_id=?';$oppParams[]=$customerId;}
  if ($q!=='') {$oppWhere[]="(o.title LIKE ? OR c.name LIKE ?)";array_push($oppParams,$like,$like);}
  if ($stage!=='') {$oppWhere[]='o.stage=?';$oppParams[]=$stage;}
  if ($minAmount!=='') {$oppWhere[]='o.amount>=?';$oppParams[]=(float)$minAmount;}
  if ($maxAmount!=='') {$oppWhere[]='o.amount<=?';$oppParams[]=(float)$maxAmount;}
  if ($from!=='') {$oppWhere[]='DATE(o.created_at)>=?';$oppParams[]=$from;}
  if ($to!=='') {$oppWhere[]='DATE(o.created_at)<=?';$oppParams[]=$to;}
  $oppFrom='FROM crm_opportunities o JOIN customers c ON c.id=o.customer_id';
  $oppLimit=$paginate($pdo,$oppFrom,$oppWhere,$oppParams,'opp_page');
  $sql='SELECT o.id,o.title,o.stage,o.amount,o.probability,o.expected_close_date,c.id AS customer_id,c.name AS customer_name '.$oppFrom.($oppWhere?' WHERE '.implode(' AND ',$oppWhere):'').' ORDER BY o.id DESC'.$oppLimit;
  $stmt=$pdo->prepare($sql);$stmt->execute($oppParams);$opportunities=$stmt->fetchAll();
  $followWhere=[];$followParams=[];
  if ($customerId>0) {$followWhere[]='f.customer_id=?';$followParams[]=$customerId;}
  if ($q!=='') {$followWhere[]='(f.title LIKE ? OR c.name LIKE ?)';array_push($followParams,$like,$like);}
  if ($status!=='') {$followWhere[]='f.status=?';$followParams[]=$status;}
  if ($assignedUserId>0) {$followWhere[]='f.assigned_user_id=?';$followParams[]=$assignedUserId;}
  if ($from!=='') {$followWhere[]='DATE(f.due_at)>=?';$followParams[]=$from;}
  if ($to!=='') {$followWhere[]='DATE(f.due_at)<=?';$followParams[]=$to;}
  $followFrom='FROM crm_followups f JOIN customers c ON c.id=f.customer_id';
  $followLimit=$paginate($pdo,$followFrom,$followWhere,$followParams,'follow_page');
  $sql='SELECT f.id,f.title,f.due_at,f.status,c.id AS customer_id,c.name AS customer_name '.$followFrom.($followWhere?' WHERE '.implode(' AND ',$followWhere):'')." ORDER BY (f.status='open') DESC,f.due_at ASC,f.id DESC".$followLimit;
  $stmt=$pdo->prepare($sql);$stmt->execute($followParams);$followups=$stmt->fetchAll();
  $contactWhere=[];$contactParams=[];
  if ($customerId>0) {$contactWhere[]='p.customer_id=?';$contactParams[]=$customerId;}
  if ($q!=='') {$contactWhere[]='(p.name LIKE ? OR p.email LIKE ? OR c.name LIKE ?)';array_push($contactParams,$like,$like,$like);}
  $contactFrom='FROM crm_contacts p JOIN customers c ON c.id=p.customer_id';
  $contactLimit=$paginate($pdo,$contactFrom,$contactWhere,$contactParams,'contact_page');
  $sql='SELECT p.id,p.name,p.role,p.email,c.id AS customer_id,c.name AS customer_name '.$contactFrom.($contactWhere?' WHERE '.implode(' AND ',$contactWhere):'').' ORDER BY p.id DESC'.$contactLimit;
  $stmt=$pdo->prepare($sql);$stmt->execute($contactParams);$contacts=$stmt->fetchAll();
  $forecastRows=$pdo->query("SELECT stage,amount,probability,expected_close_date FROM crm_opportunities WHERE stage NOT IN ('lost','won')")->fetchAll(PDO::FETCH_ASSOC);
  $forecast=['total'=>0.0,'weighted'=>0.0,'dated'=>0,'undated'=>0];
  foreach ($forecastRows as $item) {
   $forecast['total']+=(float)$item['amount'];
   $forecast['weighted']+=CrmForecast::weighted((float)$item['amount'],(string)$item['stage'],$item['probability']===null?null:(int)$item['probability']);
   if ($item['expected_close_date']===null) $forecast['undated']++; else $forecast['dated']++;
  }
  $pageTitle='CRM Merkezi';
  ob_start();require KD_ROOT.'/app/Views/customers/crm_dashboard.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
