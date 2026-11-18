<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\HR\HRController::class)){fwrite(STDERR,"HR controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/012_hr.sql');
foreach(['departments','employees','employee_leaves','employee_attendance','employee_payments','employees.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"HR migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='employees')$found=true;
if(!$found){fwrite(STDERR,"HR navigation missing.\n");exit(1);}
echo "KodVadAll HR module smoke tests passed.\n";
