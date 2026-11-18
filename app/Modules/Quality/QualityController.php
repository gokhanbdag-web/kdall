<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Quality;
use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\NotificationService;
final class QualityController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('quality.view');$errors=[];$success='';$id=(int)($_GET['id']??0);
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('quality.manage');try{$action=(string)($_POST['form_action']??'');if($action==='create'){$type=in_array($_POST['check_type']??'', ['incoming','production','final','service'],true)?$_POST['check_type']:'final';$product=(int)($_POST['product_id']??0)?:null;$quantity=(float)($_POST['quantity']??1);if($quantity<=0)throw new RuntimeException('Kontrol miktarı geçersiz.');$temp='TMP-QC-'.bin2hex(random_bytes(4));$db->beginTransaction();$db->prepare("INSERT INTO quality_checks (check_number,check_type,product_id,reference_type,reference_id,quantity,result,inspector_user_id,checked_at,notes,created_by,created_at) VALUES (?,?,?,?,?,?,'pending',?,NULL,?,?,NOW())")->execute([$temp,$type,$product,trim((string)($_POST['reference_type']??''))?:null,(int)($_POST['reference_id']??0)?:null,$quantity,(int)($_POST['inspector_user_id']??$admin['id']),trim((string)($_POST['notes']??''))?:null,(int)$admin['id']]);$id=(int)$db->lastInsertId();$number='QC-'.date('Y').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);$db->prepare('UPDATE quality_checks SET check_number=? WHERE id=?')->execute([$number,$id]);$names=is_array($_POST['parameter_name']??null)?$_POST['parameter_name']:[];$expected=is_array($_POST['expected_value']??null)?$_POST['expected_value']:[];$actual=is_array($_POST['actual_value']??null)?$_POST['actual_value']:[];$passed=is_array($_POST['passed']??null)?$_POST['passed']:[];$ins=$db->prepare('INSERT INTO quality_check_items (quality_check_id,parameter_name,expected_value,actual_value,passed,created_at) VALUES (?,?,?,?,?,NOW())');$fail=false;$count=0;foreach($names as $i=>$name){$name=trim((string)$name);if($name==='')continue;$ok=isset($passed[$i])?1:0;$ins->execute([$id,$name,trim((string)($expected[$i]??''))?:null,trim((string)($actual[$i]??''))?:null,$ok]);$fail=$fail||!$ok;$count++;}$result=$count===0?'pending':($fail?'fail':'pass');$db->prepare('UPDATE quality_checks SET result=?,checked_at=IF(?=\'pending\',NULL,NOW()) WHERE id=?')->execute([$result,$result,$id]);$db->commit();kd_audit('quality.created','quality_check',$id);if($result==='fail')NotificationService::notifyRoles($db,['owner','admin','manager'],'Kalite kontrol başarısız',$number.' kontrolünde uygunsuzluk tespit edildi.','index.php?page=quality&id='.$id,'warning');$success='Kalite kontrol kaydı oluşturuldu.';}
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$errors[]=$e instanceof RuntimeException?$e->getMessage():'Kalite kontrol kaydı oluşturulamadı.';}}
  $products=$db->query("SELECT id,name,sku FROM products WHERE status='active' ORDER BY name")->fetchAll();$users=$db->query("SELECT id,name FROM users WHERE status='active' ORDER BY name")->fetchAll();
  if($id){$q=$db->prepare('SELECT q.*,p.name product_name,u.name inspector_name FROM quality_checks q LEFT JOIN products p ON p.id=q.product_id LEFT JOIN users u ON u.id=q.inspector_user_id WHERE q.id=?');$q->execute([$id]);$check=$q->fetch();if(!$check)throw new RuntimeException('Kalite kontrol kaydı bulunamadı.');$q=$db->prepare('SELECT * FROM quality_check_items WHERE quality_check_id=? ORDER BY id');$q->execute([$id]);$items=$q->fetchAll();}
  else{$checks=$db->query('SELECT q.*,p.name product_name,u.name inspector_name FROM quality_checks q LEFT JOIN products p ON p.id=q.product_id LEFT JOIN users u ON u.id=q.inspector_user_id ORDER BY q.id DESC LIMIT 150')->fetchAll();}
  $pageTitle='Kalite Kontrol';ob_start();require KD_ROOT.'/app/Views/quality/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
