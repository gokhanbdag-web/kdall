<?php
declare(strict_types=1);

return [
    [
        'section' => 'nav.workspace',
        'items' => [
            ['label' => 'nav.dashboard', 'page' => 'dashboard', 'permission' => 'dashboard.view', 'icon' => 'grid-2x2']
        ]
    ],
    [
        'section' => 'nav.modules',
        'items' => [
            ['label' => 'nav.crm', 'page' => 'customers', 'permission' => 'customers.view', 'icon' => 'users'],
            ['label' => 'nav.catalog', 'page' => 'products', 'permission' => 'products.view', 'icon' => 'package'],
            ['label' => 'nav.sales', 'page' => 'orders', 'permission' => 'orders.view', 'icon' => 'shopping-bag'],
            ['label' => 'nav.tasks', 'page' => 'tasks', 'permission' => 'tasks.view', 'icon' => 'list-checks']
        ]
    ],
    [
        'section' => 'nav.system',
        'items' => [
            ['label' => 'nav.users', 'page' => 'users', 'permission' => 'users.view', 'icon' => 'shield-check', 'coming' => true],
            ['label' => 'nav.settings', 'page' => 'settings', 'permission' => 'settings.manage', 'icon' => 'settings', 'coming' => true]
        ]
    ]
];
