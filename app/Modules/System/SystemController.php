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
    if(($_POST['form_action']??'')==='backup'){
     $result=BackupService::create($db,(int)$admin['id']);
     kd_audit('system.backup_created','system_backup',$result['id']);
     $success='Yedek oluşturuldu: '.$result['file_name'].' · '.number_format($result['size_bytes']/1024/1024,2,',','.').' MB';
    }
   }catch(Throwable $e){error_log('KodVadAll backup: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Yedek oluşturulamadı.';}
  }
  $backups=$db->query('SELECT * FROM system_backups ORDER BY id DESC LIMIT 30')->fetchAll();
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
