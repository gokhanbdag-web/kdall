<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Service\ServiceController::class)){fwrite(STDERR,"Service controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/030_service.sql');
foreach(['service_jobs','service_job_parts','service_job_history','service.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Service migration missing {$needle}.\n");exit(1);}}
echo "KodVadAll technical service smoke tests passed.\n";
