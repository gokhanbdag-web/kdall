<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Calendar\CalendarController::class)){fwrite(STDERR,"Calendar controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/031_calendar.sql');
foreach(['calendar_events','calendar.manage','reminder_sent_at'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Calendar migration missing {$needle}.\n");exit(1);}}
echo "KodVadAll calendar smoke tests passed.\n";
