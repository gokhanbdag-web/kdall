<?php
declare(strict_types=1);
namespace KodVadAll\Modules\License;
use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\LicenseService;
final class LicenseController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('license.view');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('license.manage');try{$action=(string)($_POST['form_action']??'');if($action==='public_key'){LicenseService::savePublicKey($db,(string)($_POST['public_key']??''));$success='Lisans doğrulama açık anahtarı kaydedildi.';}if($action==='activate'){$file=$_FILES['license_file']??null;if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name'])||(int)$file['size']<1||(int)$file['size']>65536)throw new RuntimeException('64 KB sınırında geçerli bir lisans JSON dosyası seçin.');$p=LicenseService::activate($db,$file['tmp_name']);kd_audit('license.activated','license');$success='Lisans etkinleştirildi: '.(string)($p['license_id']??'');}}catch(Throwable $e){error_log('KodVadAll license: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Lisans işlemi tamamlanamadı.';}}
  $license=LicenseService::current($db);$publicKey=(string)$db->query("SELECT setting_value FROM settings WHERE setting_key='license_public_key'")->fetchColumn();$history=$db->query('SELECT * FROM update_history ORDER BY id DESC LIMIT 20')->fetchAll();$pageTitle='Lisans & Güncellemeler';ob_start();require KD_ROOT.'/app/Views/license/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
