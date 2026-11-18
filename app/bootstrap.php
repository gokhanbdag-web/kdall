<?php
declare(strict_types=1);
define('KD_ROOT', dirname(__DIR__));
define('KD_STORAGE', KD_ROOT.'/storage');
define('KD_DEBUG', false);
if (!is_dir(KD_STORAGE)) { @mkdir(KD_STORAGE,0750,true); }
date_default_timezone_set('Europe/Istanbul');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('kdall_session');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$isHttps,'httponly'=>true,'samesite'=>'Lax']);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function kd_e(mixed $value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function kd_csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function kd_csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.kd_e(kd_csrf_token()).'">'; }
function kd_csrf_check(): void { if(!hash_equals(kd_csrf_token(),(string)($_POST['_csrf']??''))){http_response_code(419);exit('Oturum doğrulaması başarısız. Sayfayı yenileyin.');} }
function kd_db(): PDO {
    static $pdo=null;
    if($pdo instanceof PDO) return $pdo;
    $file=KD_STORAGE.'/installed.php';
    if(!is_file($file)) throw new RuntimeException('Uygulama henüz kurulmadı.');
    $cfg=require $file; $db=$cfg['db'];
    $pdo=new PDO("mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",$db['user'],$db['pass'],[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false
    ]);
    return $pdo;
}
