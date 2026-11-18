<?php
declare(strict_types=1);
$pageTitle = kd_t('dashboard.title');
$cards = [
    ['label' => 'dashboard.customers', 'count' => $counts['customers'] ?? 0, 'icon' => 'users', 'tone' => 'blue'],
    ['label' => 'dashboard.products', 'count' => $counts['products'] ?? 0, 'icon' => 'package', 'tone' => 'violet'],
    ['label' => 'dashboard.orders', 'count' => $counts['orders'] ?? 0, 'icon' => 'shopping-bag', 'tone' => 'amber'],
    ['label' => 'dashboard.tasks', 'count' => $counts['tasks'] ?? 0, 'icon' => 'list-checks', 'tone' => 'green'],
];
ob_start();
?>
<section class="welcome">
    <div class="welcome-copy">
        <span class="eyebrow light">KODVADALL BUSINESS SUITE</span>
        <h2><?= kd_e(kd_t('dashboard.welcome')) ?></h2>
        <p><?= kd_e(kd_t('dashboard.subtitle')) ?></p>
    </div>
    <div class="welcome-art" aria-hidden="true"><span>K</span><i></i><b></b></div>
</section>
<section class="stats" aria-label="İşletme özeti">
    <?php foreach ($cards as $card): ?>
        <article class="stat-card">
            <div class="stat-top"><span><?= kd_e(kd_t($card['label'])) ?></span><span class="stat-icon <?= kd_e($card['tone']) ?>"><?= kd_e($card['icon']) ?></span></div>
            <strong><?= number_format((int)$card['count'], 0, ',', '.') ?></strong>
            <small><?= kd_e(kd_t('dashboard.total')) ?></small>
        </article>
    <?php endforeach; ?>
</section>
<section class="dashboard-grid">
    <article class="panel system-panel">
        <div class="panel-head">
            <div><span class="eyebrow">PLATFORM</span><h3><?= kd_e(kd_t('dashboard.activity')) ?></h3></div>
            <span class="status"><i></i><?= kd_e(kd_t('dashboard.active')) ?></span>
        </div>
        <div class="system-ready"><span class="ready-check">✓</span><div><strong><?= kd_e(kd_t('dashboard.ready')) ?></strong><p><?= kd_e(kd_t('dashboard.ready_text')) ?></p></div></div>
        <div class="system-tags"><span>PHP 8.5</span><span>MySQL</span><span>RBAC</span><span>Audit log</span></div>
    </article>
    <article class="panel quick-panel">
        <span class="eyebrow">KODVADALL</span>
        <h3>İşletmeniz için tek merkez</h3>
        <p class="muted">Modüller etkinleştirildikçe tüm operasyonlarınız bu panelde birleşecek.</p>
        <div class="quick-line"><span class="quick-dot"></span><span>Güvenlik ve yetkilendirme altyapısı aktif</span></div>
        <div class="quick-line"><span class="quick-dot"></span><span>Otomatik veritabanı güncellemeleri hazır</span></div>
    </article>
</section>
<?php
$content = ob_get_clean();
require KD_ROOT . '/app/Views/layouts/admin.php';
