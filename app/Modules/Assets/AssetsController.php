<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Assets;
use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\NotificationService;
final class AssetsController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('assets.view');$errors=[];$success='';$id=(int)($_GET['id']??0);
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('assets.manage');try{$action=(string)($_POST['form_action']??'');
   if($action==='save'){$name=trim((string)($_POST['name']??''));$purchaseDate=(string)($_POST['purchase_date']??'');$cost=(float)($_POST['purchase_cost']??0);$life=max(1,min(600,(int)($_POST['useful_life_months']??60)));if($name===''||!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$purchaseDate)||$cost<0)throw new RuntimeException('Demirbaş adı, alım tarihi veya tutarı geçersiz.');$category=(int)($_POST['category_id']??0)?:null;$values=[$name,$category,trim((string)($_POST['serial_number']??''))?:null,$purchaseDate,$cost,max(0,(float)($_POST['residual_value']??0)),$life,in_array($_POST['status']??'', ['in_use','maintenance','disposed'],true)?$_POST['status']:'in_use',(int)($_POST['assigned_employee_id']??0)?:null,trim((string)($_POST['location']??''))?:null,trim((string)($_POST['warranty_end']??''))?:null,trim((string)($_POST['notes']??''))?:null];$assetId=(int)($_POST['asset_id']??0);if($assetId){$db->prepare('UPDATE fixed_assets SET name=?,category_id=?,serial_number=?,purchase_date=?,purchase_cost=?,residual_value=?,useful_life_months=?,status=?,assigned_employee_id=?,location=?,warranty_end=?,notes=?,updated_at=NOW() WHERE id=?')->execute([...$values,$assetId]);$id=$assetId;}else{$code='AST-'.date('Y').'-'.str_pad((string)((int)$db->query('SELECT COALESCE(MAX(id),0)+1 FROM fixed_assets')->fetchColumn()),5,'0',STR_PAD_LEFT);$db->prepare('INSERT INTO fixed_assets (asset_code,name,category_id,serial_number,purchase_date,purchase_cost,residual_value,useful_life_months,status,assigned_employee_id,location,warranty_end,notes,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')->execute([$code,...$values]);$id=(int)$db->lastInsertId();}kd_audit('asset.saved','fixed_asset',$id);$success='Demirbaş kaydedildi.';}
   if($action==='maintenance'){$assetId=(int)($_POST['asset_id']??0);$description=trim((string)($_POST['description']??''));$performed=(string)($_POST['performed_at']??date('Y-m-d'));if($description==='')throw new RuntimeException('Bakım açıklaması zorunludur.');$db->prepare('INSERT INTO asset_maintenance (asset_id,maintenance_type,description,cost,performed_at,next_due_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([$assetId,trim((string)($_POST['maintenance_type']??'periodic')),$description,max(0,(float)($_POST['cost']??0)),$performed,trim((string)($_POST['next_due_at']??''))?:null,(int)$admin['id']]);$db->prepare("UPDATE fixed_assets SET status='in_use',updated_at=NOW() WHERE id=? AND status='maintenance'")->execute([$assetId]);$id=$assetId;$success='Bakım kaydı eklendi.';}
  }catch(Throwable $e){$errors[]=$e instanceof RuntimeException?$e->getMessage():'Demirbaş işlemi tamamlanamadı.';}}
  $categories=$db->query('SELECT * FROM asset_categories ORDER BY name')->fetchAll();$employees=$db->query("SELECT id,name FROM employees WHERE status='active' ORDER BY name")->fetchAll();
  if($id){$q=$db->prepare('SELECT * FROM fixed_assets WHERE id=?');$q->execute([$id]);$asset=$q->fetch();if(!$asset)throw new RuntimeException('Demirbaş bulunamadı.');$q=$db->prepare('SELECT * FROM asset_maintenance WHERE asset_id=? ORDER BY performed_at DESC');$q->execute([$id]);$maintenance=$q->fetchAll();$months=max(0,(int)((strtotime(date('Y-m-01'))-strtotime(date('Y-m-01',strtotime($asset['purchase_date']))))/(30*86400)));$monthly=max(0,((float)$asset['purchase_cost']-(float)$asset['residual_value'])/max(1,(int)$asset['useful_life_months']));$bookValue=max((float)$asset['residual_value'],(float)$asset['purchase_cost']-$monthly*$months);}
  else{$assets=$db->query('SELECT a.*,c.name category_name,e.name employee_name FROM fixed_assets a LEFT JOIN asset_categories c ON c.id=a.category_id LEFT JOIN employees e ON e.id=a.assigned_employee_id ORDER BY a.id DESC LIMIT 200')->fetchAll();}
  $pageTitle='Demirbaş Yönetimi';ob_start();require KD_ROOT.'/app/Views/assets/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
