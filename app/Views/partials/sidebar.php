<?php
declare(strict_types=1);
$navigation = require KD_ROOT . '/app/config/navigation.php';
$currentPage = (string)($_GET['page'] ?? 'dashboard');
$userName = (string)($admin['name'] ?? 'Yönetici');
$userInitial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($userName, 0, 1)) : strtoupper(substr($userName, 0, 1));
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="index.php">
        <span class="brand-mark">K</span>
        <span class="brand-copy">KodVadAll<small>BUSINESS SUITE</small></span>
    </a>
    <nav class="side-nav" aria-label="Ana menü">
        <?php foreach ($navigation as $group): ?>
            <div class="nav-caption"><?= kd_e(kd_t($group['section'])) ?></div>
            <?php foreach ($group['items'] as $item): ?>
                <?php if (!kd_can($item['permission'])) { continue; } ?>
                <?php $active = $currentPage === $item['page']; ?>
                <a class="nav-item<?= $active ? ' active' : '' ?><?= !empty($item['coming']) ? ' nav-coming' : '' ?>"
                   href="<?= empty($item['coming']) ? 'index.php?page=' . rawurlencode($item['page']) : '#' ?>"
                   <?= !empty($item['coming']) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                    <span class="nav-icon" data-icon="<?= kd_e($item['icon']) ?>" aria-hidden="true"></span>
                    <span class="nav-label"><?= kd_e(kd_t($item['label'])) ?></span>
                    <?php if (!empty($item['coming'])): ?><span class="nav-soon"><?= kd_e(kd_t('nav.coming')) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <span class="avatar"><?= kd_e($userInitial) ?></span>
        <span class="sidebar-user"><?= kd_e($userName) ?><small><?= kd_e(kd_t('common.admin')) ?></small></span>
        <a class="logout" href="logout.php" title="<?= kd_e(kd_t('nav.logout')) ?>" aria-label="<?= kd_e(kd_t('nav.logout')) ?>">↗</a>
    </div>
</aside>
