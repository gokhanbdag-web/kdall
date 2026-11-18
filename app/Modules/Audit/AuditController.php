<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Audit;
use PDO;
final class AuditController
{
 public static function handle(PDO $db):void
 {
  kd_require_permission('audit.view');$q=trim((string)($_GET['q']??''));$action=trim((string)($_GET['action']??''));$entity=trim((string)($_GET['entity']??''));$userId=(int)($_GET['user_id']??0);$from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));$where=[];$params=[];
  if($q!==''){$where[]='(a.action LIKE ? OR a.ip_address LIKE ? OR a.entity_type LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}
  if($action!==''){$where[]='a.action LIKE ?';$params[]='%'.$action.'%';}
  if($entity!==''){$where[]='a.entity_type=?';$params[]=$entity;}
  if($userId>0){$where[]='a.user_id=?';$params[]=$userId;}
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)){$where[]='a.created_at>=?';$params[]=$from.' 00:00:00';}
  if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)){$where[]='a.created_at<=?';$params[]=$to.' 23:59:59';}
  $clause=$where?' WHERE '.implode(' AND ',$where):'';$count=$db->prepare('SELECT COUNT(*) FROM audit_logs a'.$clause);$count->execute($params);$total=(int)$count->fetchColumn();$page=max(1,(int)($_GET['p']??1));$perPage=50;$pages=max(1,(int)ceil($total/$perPage));$page=min($page,$pages);$stmt=$db->prepare('SELECT a.*,u.name user_name,u.email user_email FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id'.$clause.' ORDER BY a.id DESC LIMIT '.$perPage.' OFFSET '.(($page-1)*$perPage));$stmt->execute($params);$logs=$stmt->fetchAll();$users=$db->query("SELECT id,name FROM users WHERE status='active' ORDER BY name")->fetchAll();$pageTitle='Denetim Kayıtları';ob_start();require KD_ROOT.'/app/Views/audit/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
