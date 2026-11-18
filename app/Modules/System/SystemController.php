<?php
declare(strict_types=1);
namespace KodVadAll\Modules\System;
use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\BackupService;
final class SystemController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('system.view');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('system.manage');
   try{
    if(($_POST['form_action']??'')==='backup_schedule'){$settings=['backup_enabled'=>isset($_POST['backup_enabled'])?'1':'0','backup_frequency'=>in_array($_POST['backup_frequency']??'daily',['daily','weekly'],true)?$_POST['backup_frequency']:'daily','backup_retention'=>(string)max(3,min(50,(int)($_POST['backup_retention']??10)))];$save=$db->prepare('INSERT INTO settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');foreach($settings as $key=>$value)$save->execute([$key,$value]);$success='Otomatik yedekleme ayarları kaydedildi.';}if(($_POST['form_action']??'')==='backup'){
     $result=BackupService::create($db,(int)$admin['id']);
     kd_audit('system.backup_created','system_backup',$result['id']);
     $success='Yedek oluşturuldu: '.$result['file_name'].' · '.number_format($result['size_bytes']/1024/1024,2,',','.').' MB';
    }
   }catch(Throwable $e){error_log('KodVadAll backup: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Yedek oluşturulamadı.';}
  }
  $schedule=[];foreach($db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('backup_enabled','backup_frequency','backup_retention')")->fetchAll() as $r)$schedule[$r['setting_key']]=$r['setting_value'];$backups=$db->query('SELECT * FROM system_backups ORDER BY id DESC LIMIT 30')->fetchAll();
  $events=$db->query('SELECT * FROM system_events ORDER BY id DESC LIMIT 30')->fetchAll();
  $php=PHP_VERSION;$mysql=(string)$db->query('SELECT VERSION()')->fetchColumn();
  $extensions=[];foreach(['pdo_mysql','curl','mbstring','openssl','fileinfo','json','zlib'] as $ext)$extensions[$ext]=extension_loaded($ext);
  $tableCount=(int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
  $migrationCount=(int)$db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
  $free=disk_free_space(KD_STORAGE);
  $pendingWebhooks=(int)$db->query("SELECT COUNT(*) FROM webhook_deliveries WHERE status='pending'")->fetchColumn();
  $latestCron=$db->query('SELECT MAX(created_at) FROM automation_logs')->fetchColumn();
  $pageTitle='Sistem Yönetimi & Yedekleme';
  ob_start();require KD_ROOT.'/app/Views/system/index.php';$content=ob_get_clean();
  require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
