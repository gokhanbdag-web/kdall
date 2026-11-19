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
foreach(["i.due_date<CURDATE()","financial_advance_allocations","HAVING remaining>0","overdue_days"] as $needle){if($reportController===false||strpos($reportController,$needle)===false){fwrite(STDERR,"Overdue report guard missing {$needle}.\\n");exit(1);}}
if($reportView===false||strpos($reportView,'Vadesi geçmiş alacaklar ve borçlar')===false){fwrite(STDERR,"Overdue report view missing.\\n");exit(1);}
foreach(['GROUP BY currency ORDER BY currency','GROUP BY o.currency ORDER BY o.currency','GROUP BY i.currency ORDER BY i.currency','GROUP BY oi.product_id,oi.product_name,o.currency','t.currency=i.currency'] as $needle){if($reportController===false||strpos($reportController,$needle)===false){fwrite(STDERR,"Currency-aware report query missing {$needle}.\n");exit(1);}}
foreach(["foreach(\$salesSummary as \$summary)","foreach(\$financeSummary as \$summary)","\$p['currency']","\$balance['currency']"] as $needle){if($reportView===false||strpos($reportView,$needle)===false){fwrite(STDERR,"Currency-aware report rendering missing {$needle}.\n");exit(1);}}
foreach(["t.transaction_type='collection' AND t.direction='credit'", "t.transaction_type='supplier_payment' AND t.direction='debit'"] as $needle){if($reportController===false||strpos($reportController,$needle)===false){fwrite(STDERR,"Open invoice reconciliation filter missing {$needle}.\\n");exit(1);}}
foreach(['oi.quantity*oi.unit_cost','oi.line_subtotal','GROUP BY o.currency ORDER BY o.currency','$grossMargins'] as $needle){if($reportController===false||strpos($reportController,$needle)===false){fwrite(STDERR,"Order gross margin report missing {$needle}.\\n");exit(1);}}
if($reportView===false||strpos($reportView,'Sipariş brüt kârlılığı')===false||strpos($reportView,'İade, kargo ve işletme giderleri düşülmemiştir.')===false){fwrite(STDERR,"Order gross margin report disclosure missing.\\n");exit(1);}
if(substr_count($reportController,'SELECT SUM(a.amount) FROM financial_advance_allocations a WHERE a.invoice_id=i.id')<3){fwrite(STDERR,"Receivable, payable and overdue reports must all deduct applied advances.\\n");exit(1);}
echo "KodVadAll reports smoke tests passed.\n";
