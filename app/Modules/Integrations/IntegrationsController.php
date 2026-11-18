<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Integrations;
use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\WebhookService;
final class IntegrationsController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('api.view');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('api.manage');try{$f=(string)($_POST['form_action']??'');
   if($f==='token'){$name=trim((string)($_POST['name']??''));$scopes=is_array($_POST['scopes']??null)?$_POST['scopes']:[];$allowed=['products:read','customers:read','orders:read','orders:write'];$scopes=array_values(array_intersect($scopes,$allowed));$days=max(0,min(3650,(int)($_POST['expires_days']??0)));if($name===''||!$scopes)throw new RuntimeException('API anahtarı adı ve en az bir yetki seçin.');$raw=rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');$expires=$days?date('Y-m-d H:i:s',time()+$days*86400):null;$db->prepare('INSERT INTO api_tokens (user_id,name,token_prefix,token_hash,scopes_json,expires_at,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([(int)$admin['id'],$name,substr($raw,0,12),hash('sha256',$raw),json_encode($scopes),$expires]);$_SESSION['new_api_token']=$raw;$success='API anahtarı oluşturuldu. Ham anahtar yalnızca bu ekranda bir kez gösterilir.';}
   if($f==='revoke'){$db->prepare('UPDATE api_tokens SET revoked_at=NOW() WHERE id=? AND user_id=? AND revoked_at IS NULL')->execute([(int)($_POST['token_id']??0),(int)$admin['id']]);$success='API anahtarı iptal edildi.';}
   if($f==='webhook'){$events=is_array($_POST['events']??null)?$_POST['events']:[];$secret=WebhookService::create($db,(int)$admin['id'],trim((string)($_POST['name']??'')),trim((string)($_POST['url']??'')),$events);$_SESSION['new_webhook_secret']=$secret;$success='Webhook uç noktası oluşturuldu. Gizli anahtar yalnızca bir kez gösterilir.';}
   if($f==='webhook_status'){$id=(int)($_POST['endpoint_id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['active','inactive'],true))throw new RuntimeException('Webhook durumu geçersiz.');$db->prepare('UPDATE webhook_endpoints SET status=?,updated_at=NOW() WHERE id=? AND user_id=?')->execute([$status,$id,(int)$admin['id']]);$success='Webhook durumu güncellendi.';}
  }catch(Throwable $e){error_log('KodVadAll integrations: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Entegrasyon işlemi tamamlanamadı.';}}
  $tokens=$db->prepare('SELECT * FROM api_tokens WHERE user_id=? ORDER BY id DESC');$tokens->execute([(int)$admin['id']]);$tokens=$tokens->fetchAll();$endpoints=$db->prepare('SELECT * FROM webhook_endpoints WHERE user_id=? ORDER BY id DESC');$endpoints->execute([(int)$admin['id']]);$endpoints=$endpoints->fetchAll();$deliveries=$db->query('SELECT d.*,e.name endpoint_name FROM webhook_deliveries d JOIN webhook_endpoints e ON e.id=d.endpoint_id ORDER BY d.id DESC LIMIT 50')->fetchAll();$newToken=$_SESSION['new_api_token']??null;$newSecret=$_SESSION['new_webhook_secret']??null;unset($_SESSION['new_api_token'],$_SESSION['new_webhook_secret']);$pageTitle='API & Webhook Entegrasyonları';ob_start();require KD_ROOT.'/app/Views/integrations/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
