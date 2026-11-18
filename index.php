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
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KodVadAll · Giriş</title><link rel="stylesheet" href="assets/app.css"></head><body class="auth"><main class="auth-card"><div class="brand-mark">K</div><p class="eyebrow">İŞLETME YÖNETİM PLATFORMU</p><h1>Tekrar hoş geldiniz</h1><p class="muted">Yönetim panelinize güvenli giriş yapın.</p><?php if (!empty($error)): ?><div class="alert"><?= kd_e($error) ?></div><?php endif; ?><form method="post"><?= kd_csrf_field() ?><label>E-posta<input type="email" name="email" required autocomplete="username"></label><label>Parola<input type="password" name="password" required autocomplete="current-password"></label><button class="btn primary full">Giriş yap</button></form></main></body></html><?php
    exit;
}

kd_require_permission('dashboard.view');

$counts = [];
foreach (['customers'=>'Müşteriler','products'=>'Ürünler','orders'=>'Siparişler','tasks'=>'Görevler'] as $table=>$label) {
    $counts[$label] = (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>KodVadAll · Genel Bakış</title><link rel="stylesheet" href="assets/app.css"></head><body><aside class="sidebar"><a class="brand" href="index.php"><span class="brand-mark">K</span><span>KodVadAll<small>BUSINESS SUITE</small></span></a><div class="nav-caption">ÇALIŞMA ALANI</div><a class="nav-item active" href="index.php">◫ <span>Genel Bakış</span></a><div class="nav-caption">YÖNETİM MODÜLLERİ</div><?php foreach (['CRM'=>'Müşteriler','Ürün & Stok'=>'Ürünler','Satış'=>'Siparişler','İş Takibi'=>'Görevler'] as $group=>$label): ?><a class="nav-item" href="#"><span>◇</span><span><?= kd_e($group) ?></span><span class="soon">Hazırlanıyor</span></a><?php endforeach; ?><div class="sidebar-bottom"><span class="avatar"><?= kd_e(mb_strtoupper(mb_substr($admin['name'],0,1))) ?></span><span><?= kd_e($admin['name']) ?><small>Yönetici</small></span><a class="logout" href="logout.php">Çıkış</a></div></aside><main class="main"><header class="topbar"><div><span class="eyebrow">KONTROL MERKEZİ</span><h1>Genel Bakış</h1></div><div class="top-user"><?= kd_e($admin['name']) ?></div></header><section class="welcome"><div><span class="eyebrow light">KODVADALL BUSINESS SUITE</span><h2>İşinizin tüm kontrolü<br>tek bir yerde.</h2><p>Yönetim altyapınız hazır. Modüller bu merkez üzerinden bir araya gelecek.</p></div><div class="welcome-orbit">K</div></section><section class="stats"><?php foreach ($counts as $label=>$count): ?><article class="stat-card"><span><?= kd_e($label) ?></span><strong><?= number_format($count,0,',','.') ?></strong><small>Toplam kayıt</small></article><?php endforeach; ?></section><section class="panel"><div class="panel-head"><div><h3>Platform durumu</h3><p>Kurulum başarıyla tamamlandı.</p></div><span class="status"><i></i> Sistem aktif</span></div><div class="progress-track"><span></span></div><p class="muted small">KodVadAll temel altyapısı çalışıyor. İş modülleri güvenli ve bağımsız bileşenler halinde genişletilecek.</p></section><footer>© <?= date('Y') ?> KodVadAll · Self-hosted Business Suite</footer></main></body></html>
