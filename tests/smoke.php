<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$classes = [
    KodVadAll\\Core\\Migrator::class,
    KodVadAll\\Core\\Authorization::class,
    KodVadAll\\Core\\Audit::class,
];

foreach ($classes as $class) {
    if (!class_exists($class)) {
        fwrite(STDERR, "Autoload failed: {$class}\n");
        exit(1);
    }
}

if (kd_e('<script>') !== '&lt;script&gt;') {
    fwrite(STDERR, "HTML escaping test failed.\n");
    exit(1);
}

if (strlen(kd_csrf_token()) !== 64) {
    fwrite(STDERR, "CSRF token test failed.\n");
    exit(1);
}

echo "KodVadAll core smoke tests passed.\n";
