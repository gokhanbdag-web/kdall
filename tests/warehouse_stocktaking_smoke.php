<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);$controller=file_get_contents($root.'/app/Modules/Warehouses/WarehousesController.php');$migration=file_get_contents($root.'/database/migrations/038_warehouse_stocktaking.sql');
if(!class_exists(KodVadAll\Modules\Warehouses\WarehousesController::class)){fwrite(STDERR,"Warehouse controller autoload failed.\n");exit(1);}
foreach(['warehouse_stocktakes','warehouse_stocktake_items','expected_quantity','counted_quantity','variance','completed_at'] as $needle){if($migration===false||strpos($migration,$needle)===false){fwrite(STDERR,"Stocktaking migration missing {$needle}.\n");exit(1);}}
foreach(['create_stocktake','save_stocktake','complete_stocktake','counted_quantity','FOR UPDATE','sayım sırasında stok hareketi görmüş','reference_type','stocktake'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Stocktaking workflow missing {$needle}.\n");exit(1);}}
foreach(['app/Views/warehouses/stocktakes.php','app/Views/warehouses/stocktake.php'] as $path){$view=file_get_contents($root.'/'.$path);if($view===false||strpos($view,'kd_csrf_field')===false){fwrite(STDERR,"Stocktaking view missing or unprotected: {$path}.\n");exit(1);}}
$accounting=file_get_contents($root.'/app/Core/AccountingPostingService.php');
$accountMigration=file_get_contents($root.'/database/migrations/044_stocktake_accounting.sql');
if($accounting===false||strpos($accounting,'postStocktake')===false||strpos($accounting,"'stocktake'")===false||strpos($controller,'AccountingPostingService::postStocktake')===false||$accountMigration===false||strpos($accountMigration,"'679'")===false||strpos($accountMigration,"'689'")===false){fwrite(STDERR,"Stocktake inventory variance accounting missing.\n");exit(1);}
echo "KodVadAll warehouse stocktaking smoke tests passed.\n";
