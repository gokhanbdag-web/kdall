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
            ['label' => 'nav.suppliers', 'page' => 'suppliers', 'permission' => 'suppliers.view', 'icon' => 'truck'],
            ['label' => 'nav.purchases', 'page' => 'purchases', 'permission' => 'purchases.view', 'icon' => 'clipboard-list'],
            ['label' => 'nav.finance', 'page' => 'finance', 'permission' => 'finance.view', 'icon' => 'wallet'],
            ['label' => 'nav.invoices', 'page' => 'invoices', 'permission' => 'invoices.view', 'icon' => 'file-text'],
            ['label' => 'nav.marketplaces', 'page' => 'marketplaces', 'permission' => 'marketplaces.view', 'icon' => 'store'],
            ['label' => 'nav.shipping', 'page' => 'shipping', 'permission' => 'shipments.view', 'icon' => 'truck'],
            ['label' => 'nav.returns', 'page' => 'returns', 'permission' => 'returns.view', 'icon' => 'rotate-ccw'],
            ['label' => 'nav.quotes', 'page' => 'quotes', 'permission' => 'quotes.view', 'icon' => 'file-check'],
            ['label' => 'nav.contracts', 'page' => 'contracts', 'permission' => 'contracts.view', 'icon' => 'file-signature'],
            ['label' => 'nav.hr', 'page' => 'employees', 'permission' => 'employees.view', 'icon' => 'user-round-cog'],
            ['label' => 'nav.communications', 'page' => 'communications', 'permission' => 'communications.view', 'icon' => 'messages-square'],
            ['label' => 'nav.reports', 'page' => 'reports', 'permission' => 'reports.view', 'icon' => 'chart-no-axes-combined'],
            ['label' => 'nav.marketing', 'page' => 'marketing', 'permission' => 'marketing.view', 'icon' => 'ticket-percent'],
            ['label' => 'nav.site', 'page' => 'site', 'permission' => 'site.view', 'icon' => 'panels-top-left'],
            ['label' => 'nav.payments', 'page' => 'payments', 'permission' => 'payments.view', 'icon' => 'credit-card'],
            ['label' => 'nav.notifications', 'page' => 'notifications', 'permission' => 'notifications.view', 'icon' => 'bell-ring'],
            ['label' => 'nav.integrations', 'page' => 'integrations', 'permission' => 'api.view', 'icon' => 'webhook'],
            ['label' => 'nav.files', 'page' => 'files', 'permission' => 'files.view', 'icon' => 'folder-lock'],
            ['label' => 'nav.system', 'page' => 'system', 'permission' => 'system.view', 'icon' => 'server-cog'],
            ['label' => 'nav.tasks', 'page' => 'tasks', 'permission' => 'tasks.view', 'icon' => 'list-checks']
        ]
    ],
    [
        'section' => 'nav.system',
        'items' => [
            ['label' => 'nav.users', 'page' => 'users', 'permission' => 'users.view', 'icon' => 'shield-check'],
            ['label' => 'nav.settings', 'page' => 'settings', 'permission' => 'settings.manage', 'icon' => 'settings']
        ]
    ]
];
