<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
if (is_file(KD_STORAGE . '/installed.php')) { header('Location: index.php'); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    kd_csrf_check();
    $host = trim((string)($_POST['db_host'] ?? 'localhost'));
    $name = trim((string)($_POST['db_name'] ?? ''));
    $user = trim((string)($_POST['db_user'] ?? ''));
    $pass = (string)($_POST['db_pass'] ?? '');
    $adminName = trim((string)($_POST['admin_name'] ?? ''));
    $email = trim((string)($_POST['admin_email'] ?? ''));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $name) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($adminPass)<12 || $adminName==='' || $user==='') {
        $error = 'Bilgileri kontrol edin. Yönetici parolası en az 12 karakter olmalıdır.';
    } else {
        try {
            $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4",$user,$pass,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false
            ]);
            $schema = file_get_contents(__DIR__.'/database/schema.sql');
            foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/',$schema))) as $sql) { $pdo->exec($sql); }
            $stmt=$pdo->prepare("INSERT INTO users (name,email,password_hash,role,status,created_at) VALUES (?,?,?,?,?,NOW())");
            $stmt->execute([$adminName,$email,password_hash($adminPass,PASSWORD_DEFAULT),'owner','active']);
            $config = "<?php\nreturn ".var_export(['db'=>['host'=>$host,'name'=>$name,'user'=>$user,'pass'=>$pass],'app'=>['name'=>'KodVadAll','locale'=>'tr_TR','timezone'=>'Europe/Istanbul']],true).";\n";
            if (file_put_contents(KD_STORAGE.'/installed.php',$config,LOCK_EX)===false) throw new RuntimeException('storage dizinine yazılamıyor.');
            @chmod(KD_STORAGE.'/installed.php',0600);
            session_regenerate_id(true);
            $_SESSION['user']=['id'=>(int)$pdo->lastInsertId(),'name'=>$adminName,'email'=>$email,'role'=>'owner'];
            header('Location: index.php'); exit;
        } catch (Throwable $e) {
            $error = 'Kurulum tamamlanamadı: '.(defined('KD_DEBUG') && KD_DEBUG ? $e->getMessage() : 'Veritabanı bağlantısını ve storage yazma iznini kontrol edin.');
        }
    }
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KodVadAll · Kurulum</title><link rel="stylesheet" href="assets/app.css?v=20261118-1"></head><body class="install-bg"><main class="install-wrap"><div class="install-brand"><span class="brand-mark">K</span><div><strong>KodVadAll</strong><small>BUSINESS SUITE</small></div></div><div class="install-card"><span class="eyebrow">İLK KURULUM · 01 / 01</span><h1>İşletmenizi bağlayalım.</h1><p class="muted">Veritabanı ve yönetici bilgilerinizi girerek kurulumu tamamlayın.</p><?php if($error): ?><div class="alert"><?= kd_e($error) ?></div><?php endif; ?><form method="post" autocomplete="off"><?= kd_csrf_field() ?><h3>Veritabanı bağlantısı</h3><div class="form-grid"><label>Sunucu<input name="db_host" value="localhost" required></label><label>Veritabanı adı<input name="db_name" required></label><label>Veritabanı kullanıcısı<input name="db_user" required></label><label>Veritabanı parolası<input type="password" name="db_pass"></label></div><hr><h3>Yönetici hesabı</h3><label>Ad soyad<input name="admin_name" required></label><label>E-posta adresi<input type="email" name="admin_email" required></label><label>Yönetici parolası<input type="password" name="admin_pass" minlength="12" required><small>En az 12 karakter kullanın.</small></label><button class="btn primary full">Kurulumu tamamla <span>→</span></button></form></div><p class="install-foot">Güvenli kurulum · PHP 8.5 · MySQL</p></main></body></html>
