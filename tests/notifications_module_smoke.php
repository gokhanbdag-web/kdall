<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Core\NotificationService::class)||!class_exists(KodVadAll\Modules\Notifications\NotificationsController::class)){fwrite(STDERR,"Notification classes failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/018_notifications.sql');
foreach(['notifications','automation_logs','notifications.view'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Notifications migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/cron/automation.php')){fwrite(STDERR,"Automation cron entrypoint missing.\n");exit(1);}
echo "KodVadAll notifications smoke tests passed.\n";
