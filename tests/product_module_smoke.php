<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!class_exists(KodVadAll\Modules\Products\ProductController::class)) {
    fwrite(STDERR, "Product controller autoload failed.\n");
    exit(1);
}

$navigation = require dirname(__DIR__) . '/app/config/navigation.php';
$productItem = null;
foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        if (($item['page'] ?? '') === 'products') {
            $productItem = $item;
        }
    }
}
if (!$productItem || !empty($productItem['coming'])) {
    fwrite(STDERR, "Product navigation activation test failed.\n");
    exit(1);
}

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/003_product_inventory.sql');
foreach (['product_categories','product_stock_movements','min_stock','barcode'] as $required) {
    if ($migration === false || strpos($migration, $required) === false) {
        fwrite(STDERR, "Inventory migration missing {$required}.\n");
        exit(1);
    }
}

echo "KodVadAll product inventory smoke tests passed.\n";
