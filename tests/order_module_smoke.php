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

echo "KodVadAll sales order smoke tests passed.\n";
