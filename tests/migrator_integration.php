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

    $directory = $root . '/database/migrations';
    $files = glob($directory . '/*.sql') ?: [];
    sort($files, SORT_STRING);
    $expected = array_map('basename', $files);

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

    foreach (['payment_transactions', 'accounting_journals', 'warehouse_stocktakes'] as $table) {
        $check = $pdo->prepare('SHOW TABLES LIKE ?');
        $check->execute([$table]);
        if (!$check->fetchColumn()) {
            throw new RuntimeException("Expected migrated table is missing: {$table}");
        }
    }

    echo "KodVadAll MySQL migration integration tests passed ({$after} migrations).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration integration test failed: ' . $e->getMessage() . "\n");
    exit(1);
}
