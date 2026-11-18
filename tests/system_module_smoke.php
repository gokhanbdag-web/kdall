<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Core\BackupService::class)||!class_exists(KodVadAll\Modules\System\SystemController::class)){fwrite(STDERR,"System management classes failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/021_system.sql');
foreach(['system_backups','system_events','system.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"System migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/backup_download.php')){fwrite(STDERR,"Backup download endpoint missing.\n");exit(1);}
echo "KodVadAll system management smoke tests passed.\n";
