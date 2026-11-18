<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!class_exists(KodVadAll\Modules\Tasks\TaskController::class)) {
    fwrite(STDERR, "Task controller autoload failed.\n");
    exit(1);
}

$navigation = require dirname(__DIR__) . '/app/config/navigation.php';
$taskItem = null;
foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        if (($item['page'] ?? '') === 'tasks') {
            $taskItem = $item;
        }
    }
}
if (!$taskItem || !empty($taskItem['coming'])) {
    fwrite(STDERR, "Task navigation activation test failed.\n");
    exit(1);
}

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
foreach (['CREATE TABLE IF NOT EXISTS tasks', 'assigned_user_id', 'due_at', 'priority'] as $required) {
    if ($schema === false || strpos($schema, $required) === false) {
        fwrite(STDERR, "Task schema missing {$required}.\n");
        exit(1);
    }
}

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/001_access_control.sql');
foreach (['tasks.view', 'tasks.manage'] as $required) {
    if ($migration === false || strpos($migration, $required) === false) {
        fwrite(STDERR, "Task permission missing {$required}.\n");
        exit(1);
    }
}

echo "KodVadAll task management smoke tests passed.\n";
