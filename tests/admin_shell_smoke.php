<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$required = [
    'app/Views/layouts/admin.php',
    'app/Views/partials/sidebar.php',
    'app/Views/dashboard/index.php',
    'app/config/navigation.php',
    'app/lang/tr.php',
    'assets/admin.js',
];

foreach ($required as $path) {
    if (!is_file(dirname(__DIR__) . '/' . $path)) {
        fwrite(STDERR, "Missing admin shell file: {$path}\n");
        exit(1);
    }
}

$navigation = require dirname(__DIR__) . '/app/config/navigation.php';
if (!is_array($navigation) || count($navigation) < 2) {
    fwrite(STDERR, "Navigation configuration test failed.\n");
    exit(1);
}

if (kd_t('dashboard.title') !== 'Genel Bakış') {
    fwrite(STDERR, "Turkish translation lookup test failed.\n");
    exit(1);
}

echo "KodVadAll admin shell smoke tests passed.\n";
