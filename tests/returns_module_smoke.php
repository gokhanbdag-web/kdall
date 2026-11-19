<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Returns\ReturnController::class)){fwrite(STDERR,"Return controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/010_returns.sql');
foreach(['sales_returns','sales_return_items','sales_return_events','returns.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Returns migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='returns')$found=true;
if(!$found){fwrite(STDERR,"Returns navigation missing.\n");exit(1);}
$returnController=file_get_contents(dirname(__DIR__).'/app/Modules/Returns/ReturnController.php');
if($returnController===false||strpos($returnController,'SELECT warehouse_id FROM orders')===false||strpos($returnController,'$warehouseId')===false){fwrite(STDERR,"Returns must restock to the source order warehouse.\\n");exit(1);}
if($returnController===false||strpos($returnController,"status IN ('shipped','completed') FOR UPDATE")===false||strpos($returnController,'$db->beginTransaction();$q=$db->prepare')===false){fwrite(STDERR,"Return creation must lock source order before quantity checks.\\n");exit(1);}
$accounting=file_get_contents(dirname(__DIR__).'/app/Core/AccountingPostingService.php');
if($accounting===false||strpos($accounting,'postSalesReturnRestock')===false||strpos($accounting,'postSalesReturnRefund')===false||strpos($returnController,'AccountingPostingService::postSalesReturnRestock')===false||strpos($returnController,'AccountingPostingService::postSalesReturnRefund')===false){fwrite(STDERR,"Sales returns must post stock and refund accounting entries.\n");exit(1);}
echo "KodVadAll returns module smoke tests passed.\n";
