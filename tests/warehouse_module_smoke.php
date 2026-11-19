<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Warehouses\WarehousesController::class)){
 fwrite(STDERR,"Warehouse controller autoload failed.\n");exit(1);
}
$root=dirname(__DIR__);
$read=static function(string $path)use($root):string{$value=file_get_contents($root.'/'.$path);if($value===false){fwrite(STDERR,"Missing source: {$path}\n");exit(1);}return $value;};
$migration=$read('database/migrations/037_multi_warehouse.sql');
foreach(['warehouses','product_warehouse_stocks','warehouse_transfers','warehouse_transfer_items','warehouse_id','transfer_id','warehouses.view','warehouses.manage','is_default'] as $needle){
 if(strpos($migration,$needle)===false){fwrite(STDERR,"Warehouse migration missing {$needle}.\n");exit(1);}
}
$controller=$read('app/Modules/Warehouses/WarehousesController.php');
foreach(['set_default','transfer','FOR UPDATE','transfer_out','transfer_in','product_warehouse_stocks','warehouse_transfers'] as $needle){
 if(strpos($controller,$needle)===false){fwrite(STDERR,"Warehouse controller missing {$needle}.\n");exit(1);}
}
$index=$read('index.php');$navigation=$read('app/config/navigation.php');
if(strpos($index,"page === 'warehouses'")===false||strpos($navigation,"'page' => 'warehouses'")===false){fwrite(STDERR,"Warehouse route or navigation missing.\n");exit(1);}
$products=$read('app/Modules/Products/ProductController.php');
foreach(['product_warehouse_stocks','warehouse_id','warehouseStocks'] as $needle){if(strpos($products,$needle)===false){fwrite(STDERR,"Product warehouse integration missing {$needle}.\n");exit(1);}}
foreach([
 'app/Modules/Purchasing/PurchasingController.php',
 'app/Modules/Production/ProductionController.php',
 'app/Modules/Orders/OrderController.php',
 'app/Modules/POS/POSController.php',
 'app/Modules/Returns/ReturnController.php'
] as $path){
 $source=$read($path);
 if(strpos($source,'product_warehouse_stocks')===false||strpos($source,'warehouse_id')===false){
  fwrite(STDERR,"Operational stock flow is not warehouse-aware: {$path}\n");exit(1);
 }
}
foreach(['app/Views/warehouses/index.php','app/Views/warehouses/transfers.php'] as $path){
 $source=$read($path);if(strpos($source,'kd_csrf_field')===false){fwrite(STDERR,"CSRF form protection missing: {$path}\n");exit(1);}
}
$reconcile=$read('app/Views/warehouses/reconcile.php');
$warehouseController=$read('app/Modules/Warehouses/WarehousesController.php');
foreach(['warehouse_total','HAVING ABS(difference)>0.0005','action===\'reconcile\''] as $needle){if(strpos($warehouseController,$needle)===false){fwrite(STDERR,"Warehouse reconciliation missing {$needle}.\\n");exit(1);}}
if(strpos($reconcile,'Stok mutabakatı')===false){fwrite(STDERR,"Warehouse reconciliation report missing.\\n");exit(1);}
echo "KodVadAll multi-warehouse inventory smoke tests passed.\n";
