<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!class_exists(KodVadAll\Modules\Orders\OrderController::class)) {
    fwrite(STDERR, "Order controller autoload failed.\n");
    exit(1);
}

$navigation = require dirname(__DIR__) . '/app/config/navigation.php';
$orderItem = null;
foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        if (($item['page'] ?? '') === 'orders') $orderItem = $item;
    }
}
if (!$orderItem || !empty($orderItem['coming'])) {
    fwrite(STDERR, "Sales navigation activation test failed.\n");
    exit(1);
}

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/004_sales_orders.sql');
foreach (['order_items','order_status_history','stock_deducted_at','reference_id'] as $required) {
    if ($migration === false || strpos($migration,$required)===false) {
        fwrite(STDERR, "Sales migration missing {$required}.\n");
        exit(1);
    }
}

$warehouseMigration=file_get_contents(dirname(__DIR__).'/database/migrations/039_sales_order_warehouse.sql');
$orderController=file_get_contents(dirname(__DIR__).'/app/Modules/Orders/OrderController.php');
$orderForm=file_get_contents(dirname(__DIR__).'/app/Views/orders/form.php');
$orderDetail=file_get_contents(dirname(__DIR__).'/app/Views/orders/detail.php');
foreach(['ADD COLUMN warehouse_id','fk_orders_warehouse'] as $needle){if($warehouseMigration===false||strpos($warehouseMigration,$needle)===false){fwrite(STDERR,"Sales warehouse migration missing {$needle}.\n");exit(1);}}
foreach(["warehouse_id","resolveWarehouseId","warehouse_name"] as $needle){if($orderController===false||strpos($orderController,$needle)===false){fwrite(STDERR,"Sales warehouse routing missing {$needle}.\n");exit(1);}}
if($orderForm===false||strpos($orderForm,'name="warehouse_id"')===false||$orderDetail===false||strpos($orderDetail,'warehouse_name')===false){fwrite(STDERR,"Sales warehouse selection/detail UI missing.\n");exit(1);}
foreach(['store.php','app/Modules/POS/POSController.php'] as $path){$source=file_get_contents(dirname(__DIR__).'/'.$path);if($source===false||strpos($source,'warehouse_id')===false){fwrite(STDERR,"Sales channel warehouse assignment missing in {$path}.\n");exit(1);}}
echo "KodVadAll sales order smoke tests passed.\n";
