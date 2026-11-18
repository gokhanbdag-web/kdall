<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use Throwable;
final class NotificationService
{
 public static function notifyRoles(PDO $db,array $roles,string $title,string $message,?string $url=null,string $type='info'):void
 {
  try{$roles=array_values(array_filter($roles,'is_string'));if(!$roles)return;$marks=implode(',',array_fill(0,count($roles),'?'));$q=$db->prepare("SELECT DISTINCT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug IN ($marks) AND u.status='active'");$q->execute($roles);$ids=$q->fetchAll(PDO::FETCH_COLUMN);$ins=$db->prepare('INSERT INTO notifications (user_id,title,message,type,url,created_at) VALUES (?,?,?,?,?,NOW())');foreach($ids as $id)$ins->execute([(int)$id,mb_substr($title,0,190),mb_substr($message,0,500),$type,$url]);}catch(Throwable $e){error_log('KodVadAll notification service: '.$e->getMessage());}
 }
 public static function notifyUser(PDO $db,int $userId,string $title,string $message,?string $url=null,string $type='info'):void
 {
  try{$db->prepare('INSERT INTO notifications (user_id,title,message,type,url,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$userId,mb_substr($title,0,190),mb_substr($message,0,500),$type,$url]);}catch(Throwable $e){error_log('KodVadAll notification service: '.$e->getMessage());}
 }
 public static function runDaily(PDO $db):int
 {
  $sent=0;
  $invoiceRows=$db->query("SELECT id,invoice_number,due_date FROM invoices WHERE status IN ('issued','overdue') AND due_date<CURDATE()")->fetchAll();
  foreach($invoiceRows as $r){if(self::claim($db,'overdue_invoice',(string)$r['id'],'Vadesi geçen fatura')){self::notifyRoles($db,['owner','admin','manager'],'Vadesi geçen fatura',$r['invoice_number'].' numaralı faturanın vadesi '.$r['due_date'].' tarihinde doldu.','index.php?page=invoices','warning');$sent++;}}
  $stockRows=$db->query("SELECT id,name,stock_quantity,min_stock FROM products WHERE status='active' AND track_stock=1 AND stock_quantity<=min_stock")->fetchAll();
  foreach($stockRows as $r){if(self::claim($db,'low_stock',(string)$r['id'],'Kritik stok seviyesi')){self::notifyRoles($db,['owner','admin','manager'],'Kritik stok uyarısı',$r['name'].' ürününün stok miktarı minimum seviyeye indi.','index.php?page=products&action=view&id='.(int)$r['id'],'warning');$sent++;}}
  $contractRows=$db->query("SELECT id,contract_number,title,end_date FROM contracts WHERE status='active' AND end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)")->fetchAll();
  foreach($contractRows as $r){if(self::claim($db,'contract_expiry',(string)$r['id'],'Sözleşme bitiş tarihi yaklaşıyor')){self::notifyRoles($db,['owner','admin','manager'],'Sözleşme bitişi yaklaşıyor',$r['contract_number'].' · '.$r['title'].' sözleşmesinin bitiş tarihi '.$r['end_date'].'.','index.php?page=contracts&action=view&id='.(int)$r['id'],'warning');$sent++;}}
  return $sent;
 }
 private static function claim(PDO $db,string $rule,string $reference,string $message):bool
 {
  $q=$db->prepare("INSERT IGNORE INTO automation_logs (rule_key,reference_id,run_date,status,message,created_at) VALUES (?,?,CURDATE(),'done',?,NOW())");$q->execute([$rule,$reference,$message]);return $q->rowCount()>0;
 }
}
