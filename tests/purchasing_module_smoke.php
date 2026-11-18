<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Purchasing\PurchasingController::class)){fwrite(STDERR,"Purchasing controller autoload failed.\\n");exit(1);}

$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Purchasing/PurchasingController.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/purchasing/index.php');
foreach(['form_action','reorder','min_stock','purchase_order_items','beginTransaction','weightedCost'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Critical stock replenishment logic missing {$needle}.\\n");exit(1);}}
foreach(['Kritik stoktan sipariş önerisi','Öneri miktarı','Taslak satın alma siparişi oluştur'] as $needle){if($view===false||strpos($view,$needle)===false){fwrite(STDERR,"Critical stock replenishment view missing {$needle}.\\n");exit(1);}}

$sql=file_get_contents(dirname(__DIR__).'/database/migrations/005_suppliers_purchasing.sql');
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Purchasing/PurchasingController.php');
foreach(['weightedCost','incomingCost','purchase_price','received_quantity'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Purchasing weighted-cost logic missing {$needle}.\\n");exit(1);}}

foreach(['suppliers','purchase_orders','purchase_order_items','purchase_receipts','purchase_receipt_items','purchases.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Purchasing migration missing {$needle}.\\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=[];foreach($nav as $g)foreach($g['items'] as $i)$found[]=$i['page'];foreach(['suppliers','purchases'] as $p)if(!in_array($p,$found,true)){fwrite(STDERR,"Navigation missing {$p}.\\n");exit(1);}
echo "KodVadAll purchasing module smoke tests passed.\\n";
