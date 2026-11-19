<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Reports\ReportsController::class)){fwrite(STDERR,"Reports controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/014_reports.sql');
if($sql===false||strpos($sql,'reports.view')===false){fwrite(STDERR,"Reports permission migration missing.\n");exit(1);}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='reports')$found=true;
if(!$found){fwrite(STDERR,"Reports navigation missing.\n");exit(1);}
$reportController=file_get_contents(dirname(__DIR__).'/app/Modules/Reports/ReportsController.php');
$reportView=file_get_contents(dirname(__DIR__).'/app/Views/reports/index.php');
foreach(['GROUP BY currency ORDER BY currency','GROUP BY o.currency ORDER BY o.currency','GROUP BY i.currency ORDER BY i.currency','GROUP BY oi.product_id,oi.product_name,o.currency','t.currency=i.currency'] as $needle){if($reportController===false||strpos($reportController,$needle)===false){fwrite(STDERR,"Currency-aware report query missing {$needle}.\n");exit(1);}}
foreach(["foreach(\$salesSummary as \$summary)","foreach(\$financeSummary as \$summary)","\$p['currency']","\$balance['currency']"] as $needle){if($reportView===false||strpos($reportView,$needle)===false){fwrite(STDERR,"Currency-aware report rendering missing {$needle}.\n");exit(1);}}
foreach(["t.transaction_type='collection' AND t.direction='credit'", "t.transaction_type='supplier_payment' AND t.direction='debit'"] as $needle){if($reportController===false||strpos($reportController,$needle)===false){fwrite(STDERR,"Open invoice reconciliation filter missing {$needle}.\\n");exit(1);}}
echo "KodVadAll reports smoke tests passed.\n";
