<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!class_exists(KodVadAll\Modules\Users\UserController::class)) {
    fwrite(STDERR, "User controller autoload failed.\n");
    exit(1);
}

$navigation = require dirname(__DIR__) . '/app/config/navigation.php';
$userItem = null;
foreach ($navigation as $group) {
    foreach ($group['items'] as $item) {
        if (($item['page'] ?? '') === 'users') {
            $userItem = $item;
        }
    }
}
if (!$userItem || !empty($userItem['coming'])) {
    fwrite(STDERR, "User navigation activation test failed.\n");
    exit(1);
}

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
foreach (['CREATE TABLE IF NOT EXISTS users', 'password_hash', 'last_login_at'] as $required) {
    if ($schema === false || strpos($schema, $required) === false) {
        fwrite(STDERR, "User schema missing {$required}.\n");
        exit(1);
    }
}

$migration = file_get_contents(dirname(__DIR__) . '/database/migrations/001_access_control.sql');
foreach (['user_roles', 'roles', 'users.manage', 'roles.manage'] as $required) {
    if ($migration === false || strpos($migration, $required) === false) {
        fwrite(STDERR, "User access migration missing {$required}.\n");
        exit(1);
    }
}

echo "KodVadAll user management smoke tests passed.\n";
