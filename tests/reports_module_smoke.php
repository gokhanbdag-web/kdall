<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Reports\ReportsController::class)){fwrite(STDERR,"Reports controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/014_reports.sql');
if($sql===false||strpos($sql,'reports.view')===false){fwrite(STDERR,"Reports permission migration missing.\n");exit(1);}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='reports')$found=true;
if(!$found){fwrite(STDERR,"Reports navigation missing.\n");exit(1);}
echo "KodVadAll reports smoke tests passed.\n";
