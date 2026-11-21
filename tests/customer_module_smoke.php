<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!class_exists(KodVadAll\Modules\Customers\CustomerController::class)) {
    fwrite(STDERR, "Customer controller autoload failed.\n");
    exit(1);
}

$navigation = require dirname(__DIR__) . '/app/config/navigation.php';
$customerItem = null;
foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        if (($item['page'] ?? '') === 'customers') {
            $customerItem = $item;
        }
    }
}
if (!$customerItem || !empty($customerItem['coming'])) {
    fwrite(STDERR, "CRM navigation activation test failed.\n");
    exit(1);
}

$translations = require dirname(__DIR__) . '/app/lang/tr.php';
if (($translations['customers.title'] ?? '') !== 'Müşteriler') {
    fwrite(STDERR, "CRM translation test failed.\n");
    exit(1);
}

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/002_customer_activities.sql');
if ($migration === false || strpos($migration, 'CREATE TABLE IF NOT EXISTS customer_activities') === false) {
    fwrite(STDERR, "Customer activity migration test failed.\n");
    exit(1);
}

$controller = file_get_contents(dirname(__DIR__) . '/app/Modules/Customers/CustomerController.php');
if ($controller === false || substr_count($controller, '$pdo->beginTransaction();') < 3 || substr_count($controller, '$pdo->commit();') < 3 || substr_count($controller, 'self::find($pdo, $customerId)') < 3) {
    fwrite(STDERR, "CRM customer mutation consistency checks failed.\\n");
    exit(1);
}

echo "KodVadAll CRM customer smoke tests passed.\\n";
