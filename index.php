<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (!is_file(KD_STORAGE . '/installed.php')) {
    header('Location: install.php', true, 302);
    exit;
}

$pdo = kd_db();
$admin = $_SESSION['user'] ?? null;
if (!$admin) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        kd_csrf_check();
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $stmt = $pdo->prepare('SELECT id,name,email,password_hash,role FROM users WHERE email = ? AND status = ? LIMIT 1');
        $stmt->execute([$email, 'active']);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            $loginUpdate = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
            $loginUpdate->execute([(int)$user['id']]);
            kd_audit('auth.login', 'user', (int)$user['id']);
            header('Location: index.php');
            exit;
        }
        kd_audit('auth.login_failed', 'auth');
        $error = 'E-posta veya parola hatalı.';
    }
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KodVadAll · Giriş</title><link rel="stylesheet" href="assets/app.css?v=20261118-2"></head><body class="auth"><main class="auth-card"><div class="brand-mark">K</div><p class="eyebrow">İŞLETME YÖNETİM PLATFORMU</p><h1>Tekrar hoş geldiniz</h1><p class="muted">Yönetim panelinize güvenli giriş yapın.</p><?php if (!empty($error)): ?><div class="alert"><?= kd_e($error) ?></div><?php endif; ?><form method="post"><?= kd_csrf_field() ?><label>E-posta<input type="email" name="email" required autocomplete="username"></label><label>Parola<input type="password" name="password" required autocomplete="current-password"></label><button class="btn primary full">Giriş yap</button></form></main></body></html><?php
    exit;
}

$page = (string)($_GET['page'] ?? 'dashboard');

if ($page === 'customers') {
    KodVadAll\Modules\Customers\CustomerController::handle($pdo, $admin);
    exit;
}

if ($page === 'products') {
    KodVadAll\Modules\Products\ProductController::handle($pdo, $admin);
    exit;
}

if ($page === 'orders') {
    KodVadAll\Modules\Orders\OrderController::handle($pdo, $admin);
    exit;
}

if ($page === 'tasks') {
    KodVadAll\Modules\Tasks\TaskController::handle($pdo, $admin);
    exit;
}

if ($page === 'users') {
    KodVadAll\Modules\Users\UserController::handle($pdo, $admin);
    exit;
}

if ($page === 'suppliers' || $page === 'purchases') {
    KodVadAll\Modules\Purchasing\PurchasingController::handle($pdo, $admin, $page);
    exit;
}

if ($page === 'finance') {
    KodVadAll\Modules\Finance\FinanceController::handle($pdo, $admin);
    exit;
}

if ($page === 'invoices') {
    KodVadAll\Modules\Invoices\InvoiceController::handle($pdo, $admin);
    exit;
}

if ($page === 'marketplaces') {
    KodVadAll\Modules\Marketplaces\MarketplaceController::handle($pdo, $admin);
    exit;
}

if ($page === 'shipping') {
    KodVadAll\Modules\Shipping\ShippingController::handle($pdo, $admin);
    exit;
}

if ($page === 'returns') {
    KodVadAll\Modules\Returns\ReturnController::handle($pdo, $admin);
    exit;
}

if ($page === 'quotes' || $page === 'contracts') {
    KodVadAll\Modules\SalesDocuments\SalesDocumentsController::handle($pdo, $admin, $page);
    exit;
}

if ($page === 'employees') {
    KodVadAll\Modules\HR\HRController::handle($pdo, $admin);
    exit;
}

if ($page === 'communications') {
    KodVadAll\Modules\Communications\CommunicationsController::handle($pdo, $admin);
    exit;
}

if ($page === 'reports') {
    KodVadAll\Modules\Reports\ReportsController::handle($pdo);
    exit;
}

if ($page === 'marketing') {
    KodVadAll\Modules\Marketing\MarketingController::handle($pdo, $admin);
    exit;
}

if ($page === 'site') {
    KodVadAll\Modules\CMS\CMSController::handle($pdo, $admin);
    exit;
}

if ($page === 'payments') {
    KodVadAll\Modules\Payments\PaymentsController::handle($pdo);
    exit;
}

if ($page === 'notifications') {
    KodVadAll\Modules\Notifications\NotificationsController::handle($pdo, $admin);
    exit;
}

if ($page === 'integrations') {
    KodVadAll\Modules\Integrations\IntegrationsController::handle($pdo, $admin);
    exit;
}

if ($page === 'files') {
    KodVadAll\Modules\Files\FilesController::handle($pdo, $admin);
    exit;
}

if ($page === 'system') {
    KodVadAll\Modules\System\SystemController::handle($pdo, $admin);
    exit;
}

if ($page === 'settings') {
    KodVadAll\Modules\Settings\SettingsController::handle($pdo);
    exit;
}

if ($page !== 'dashboard') {
    http_response_code(404);
    $pageTitle = 'Sayfa bulunamadı';
    $content = '<section class="panel"><h2>Bu modül henüz etkinleştirilmedi.</h2><p class="muted">Modül geliştirme sırası geldiğinde bu alan aktif edilecek.</p></section>';
    require KD_ROOT . '/app/Views/layouts/admin.php';
    exit;
}

kd_require_permission('dashboard.view');

$counts = [];
$tables = [
    'customers' => 'customers',
    'products' => 'products',
    'orders' => 'orders',
    'tasks' => 'tasks'
];
foreach ($tables as $table => $key) {
    $counts[$key] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

require KD_ROOT . '/app/Views/dashboard/index.php';
