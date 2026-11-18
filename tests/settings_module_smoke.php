<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!class_exists(KodVadAll\Modules\Settings\SettingsController::class)) {
    fwrite(STDERR, "Settings controller autoload failed.\n");
    exit(1);
}

$navigation = require dirname(__DIR__) . '/app/config/navigation.php';
$settingsItem = null;
foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        if (($item['page'] ?? '') === 'settings') {
            $settingsItem = $item;
        }
    }
}
if (!$settingsItem || !empty($settingsItem['coming'])) {
    fwrite(STDERR, "Settings navigation activation test failed.\n");
    exit(1);
}

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schema === false || strpos($schema, 'CREATE TABLE IF NOT EXISTS settings') === false) {
    fwrite(STDERR, "Settings table missing from schema.\n");
    exit(1);
}

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/001_access_control.sql');
if ($migration === false || strpos($migration, 'settings.manage') === false) {
    fwrite(STDERR, "Settings permission missing.\n");
    exit(1);
}

echo "KodVadAll settings module smoke tests passed.\n";
