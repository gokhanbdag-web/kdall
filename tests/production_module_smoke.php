<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Production\ProductionController::class)){fwrite(STDERR,"Production controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/029_production.sql');
foreach(['production_recipes','production_orders','production_order_materials','production.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Production migration missing {$needle}.\n");exit(1);}}
$warehouseMigration=file_get_contents(dirname(__DIR__).'/database/migrations/041_production_order_warehouse.sql');
$productionController=file_get_contents(dirname(__DIR__).'/app/Modules/Production/ProductionController.php');
$productionView=file_get_contents(dirname(__DIR__).'/app/Views/production/index.php');
foreach(['ADD COLUMN warehouse_id','fk_production_order_warehouse'] as $needle){if($warehouseMigration===false||strpos($warehouseMigration,$needle)===false){fwrite(STDERR,"Production warehouse migration missing {$needle}.\\n");exit(1);}}
foreach(['warehouse_id','$warehouseId','warehouse_name'] as $needle){if($productionController===false||strpos($productionController,$needle)===false){fwrite(STDERR,"Production warehouse routing missing {$needle}.\\n");exit(1);}}
if($productionView===false||strpos($productionView,'name="warehouse_id"')===false||strpos($productionView,'warehouse_name')===false){fwrite(STDERR,"Production warehouse selection/detail UI missing.\\n");exit(1);}
echo "KodVadAll production smoke tests passed.\n";
