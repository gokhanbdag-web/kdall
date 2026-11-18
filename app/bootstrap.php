<?php
declare(strict_types=1);

define('KD_ROOT', dirname(__DIR__));
define('KD_STORAGE', KD_ROOT . '/storage');
define('KD_DEBUG', false);

if (!is_dir(KD_STORAGE)) {
    @mkdir(KD_STORAGE, 0750, true);
}

date_default_timezone_set('Europe/Istanbul');

ini_set('display_errors', '0');
ini_set('log_errors', '1');

spl_autoload_register(static function (string $class): void {
    $maps = [
        'KodVadAll\\Core\\' => KD_ROOT . '/app/Core/',
        'KodVadAll\\Modules\\' => KD_ROOT . '/app/Modules/'
    ];
    foreach ($maps as $prefix => $basePath) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file = $basePath . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }
});

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('kdall_session');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function kd_t(string $key): string
{
    static $translations = null;
    if ($translations === null) {
        $file = KD_ROOT . '/app/lang/tr.php';
        $translations = is_file($file) ? require $file : [];
    }
    return (string)($translations[$key] ?? $key);
}

function kd_e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function kd_csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['_csrf'];
}

function kd_csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . kd_e(kd_csrf_token()) . '">';
}

function kd_csrf_check(): void
{
    $submitted = (string)($_POST['_csrf'] ?? '');
    if ($submitted === '' || !hash_equals(kd_csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Oturum doğrulaması başarısız. Sayfayı yenileyin.');
    }
}

function kd_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $file = KD_STORAGE . '/installed.php';
    if (!is_file($file)) {
        throw new RuntimeException('Uygulama henüz kurulmadı.');
    }

    $cfg = require $file;
    $db = $cfg['db'] ?? [];
    $host = (string)($db['host'] ?? 'localhost');
    $name = (string)($db['name'] ?? '');
    $user = (string)($db['user'] ?? '');
    $pass = (string)($db['pass'] ?? '');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Veritabanı yapılandırması eksik.');
    }

    $pdo = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 8
        ]
    );

    KodVadAll\Core\Migrator::run($pdo, KD_ROOT . '/database/migrations');
    return $pdo;
}

function kd_can(string $permission): bool
{
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    return $userId > 0 && KodVadAll\Core\Authorization::allows(kd_db(), $userId, $permission);
}

function kd_require_permission(string $permission): void
{
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    KodVadAll\Core\Authorization::require(kd_db(), $userId, $permission);
}

function kd_audit(string $action, ?string $entityType = null, ?int $entityId = null): void
{
    KodVadAll\Core\Audit::record(kd_db(), $action, $entityType, $entityId);
}
