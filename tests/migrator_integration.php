<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/Core/Migrator.php';

use KodVadAll\Core\Migrator;

$host = getenv('KD_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('KD_TEST_DB_PORT') ?: '3306';
$name = getenv('KD_TEST_DB_NAME') ?: 'kdall_test';
$user = getenv('KD_TEST_DB_USER') ?: 'root';
$pass = getenv('KD_TEST_DB_PASS') ?: 'root';

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $root = dirname(__DIR__);
    $schema = file_get_contents($root . '/database/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Base schema could not be read.');
    }

    $statements = preg_split('/;\\s*(?:\\r?\n|$)/', trim($schema)) ?: [];
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    $pdo->prepare("INSERT INTO orders (order_number,status,subtotal,tax_total,grand_total,currency,created_at) VALUES (?,'draft',0,0,0,'TRY',NOW())")->execute(['MIGRATION-TEST-ORDER']);

    $directory = $root . '/database/migrations';
    $files = glob($directory . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    $expected = array_map('basename', $files);

    // Apply migrations through 039 first, then create a legacy purchase order
    // so migration 040 can prove that existing purchases are backfilled.
    $beforePurchaseWarehouse = sys_get_temp_dir() . '/kdall-migrations-pre-040-' . bin2hex(random_bytes(5));
    if (!mkdir($beforePurchaseWarehouse, 0700, true)) {
        throw new RuntimeException('Could not create temporary migration directory.');
    }
    foreach ($files as $file) {
        if (strcmp(basename($file), '040_purchase_order_warehouse.sql') >= 0) {
            continue;
        }
        if (!copy($file, $beforePurchaseWarehouse . '/' . basename($file))) {
            throw new RuntimeException('Could not stage migration: ' . basename($file));
        }
    }
    Migrator::run($pdo, $beforePurchaseWarehouse);
    foreach (glob($beforePurchaseWarehouse . '/*') ?: [] as $stagedFile) {
        unlink($stagedFile);
    }
    rmdir($beforePurchaseWarehouse);

    $pdo->prepare("INSERT INTO suppliers (name,created_at) VALUES (?,NOW())")->execute(['MIGRATION-TEST-SUPPLIER']);
    $supplierId = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO purchase_orders (purchase_number,supplier_id,status,subtotal,tax_total,grand_total,created_at) VALUES (?,?,'draft',0,0,0,NOW())")->execute(['MIGRATION-TEST-PO', $supplierId]);

    Migrator::run($pdo, $directory);
    $applied = $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    if ($applied !== $expected) {
        throw new RuntimeException('Not all migrations were recorded after the first run.');
    }

    $before = count($applied);
    Migrator::run($pdo, $directory);
    $after = (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    if ($after !== $before) {
        throw new RuntimeException('A second migration run changed the applied migration count.');
    }

    $legacyOrder = $pdo->prepare('SELECT w.is_default FROM orders o LEFT JOIN warehouses w ON w.id=o.warehouse_id WHERE o.order_number=?');
    $legacyOrder->execute(['MIGRATION-TEST-ORDER']);
    if ((int)$legacyOrder->fetchColumn() !== 1) {
        throw new RuntimeException('Existing sales orders were not backfilled to the default warehouse.');
    }

    $legacyPurchase = $pdo->prepare('SELECT w.is_default FROM purchase_orders p LEFT JOIN warehouses w ON w.id=p.warehouse_id WHERE p.purchase_number=?');
    $legacyPurchase->execute(['MIGRATION-TEST-PO']);
    if ((int)$legacyPurchase->fetchColumn() !== 1) {
        throw new RuntimeException('Existing purchase orders were not backfilled to the default warehouse.');
    }

    foreach (['payment_transactions', 'accounting_journals', 'warehouse_stocktakes'] as $table) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $check->execute([$table]);
        if ((int)$check->fetchColumn() !== 1) {
            throw new RuntimeException("Expected migrated table is missing: {$table}");
        }
    }

    echo "KodVadAll MySQL migration integration tests passed ({$after} migrations).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration integration test failed: ' . $e->getMessage() . "\n");
    exit(1);
}
