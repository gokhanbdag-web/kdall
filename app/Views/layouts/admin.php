<?php
declare(strict_types=1);
$pageTitle = $pageTitle ?? kd_t('dashboard.title');
$notificationUnread = 0;
try {
    $nq = kd_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND read_at IS NULL');
    $nq->execute([(int)$admin['id']]);
    $notificationUnread = (int)$nq->fetchColumn();
} catch (Throwable $e) { $notificationUnread = 0; }
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#f7f8fc">
    <title><?= kd_e($pageTitle) ?> · KodVadAll</title>
    <link rel="stylesheet" href="assets/app.css?v=20261118-3">
</head>
<body class="admin-app">
<div class="app-shell">
    <?php require KD_ROOT . '/app/Views/partials/sidebar.php'; ?>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Menüyü aç">☰</button>
                <div>
                    <span class="eyebrow"><?= kd_e(kd_t('dashboard.eyebrow')) ?></span>
                    <h1><?= kd_e($pageTitle) ?></h1>
                </div>
            </div>
            <div class="topbar-actions">
                <label class="quick-search"><span>⌕</span><input type="search" placeholder="<?= kd_e(kd_t('common.search')) ?>" aria-label="<?= kd_e(kd_t('common.search')) ?>"></label>
                <a class="icon-button notification-link" href="index.php?page=notifications" aria-label="<?= kd_e(kd_t('common.notifications')) ?>">♧<?php if($notificationUnread): ?><b><?= $notificationUnread>99?'99+':$notificationUnread ?></b><?php endif; ?></a>
                <div class="top-user"><span class="avatar small-avatar"><?= kd_e($userInitial) ?></span><span><?= kd_e($admin['name']) ?></span></div>
            </div>
        </header>
        <?= $content ?>
        <footer>© <?= date('Y') ?> KodVadAll · <?= kd_e(kd_t('app.tagline')) ?></footer>
    </main>
</div>
<script src="assets/admin.js" defer></script>
</body>
</html>
