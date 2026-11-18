<?php
declare(strict_types=1);
$roleLabels = ['owner' => 'Sahip', 'admin' => 'Yönetici', 'manager' => 'Yönetici Yardımcısı', 'staff' => 'Personel'];
$isSelf = (int)$user['id'] === (int)($_SESSION['user']['id'] ?? 0);
?>
<div class="module-toolbar"><div><p class="module-intro">Kullanıcı hesabı ve erişim bilgileri.</p></div><div class="toolbar-actions"><a class="btn secondary action-link" href="index.php?page=users">← Liste</a><?php if (kd_can('users.manage')): ?><a class="btn primary action-link" href="index.php?page=users&action=edit&id=<?= (int)$user['id'] ?>">Düzenle</a><?php endif; ?></div></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ', $errors)) ?></div><?php endif; ?>
<section class="panel user-detail-panel">
    <div class="user-detail-heading"><span class="profile-avatar"><?= kd_e(function_exists('mb_substr') ? mb_strtoupper(mb_substr($user['name'], 0, 1)) : strtoupper(substr($user['name'], 0, 1))) ?></span><div><h2><?= kd_e($user['name']) ?></h2><p><?= kd_e($user['email']) ?></p></div><span class="customer-status <?= $user['status'] === 'active' ? 'is-active' : 'is-inactive' ?>"><?= $user['status'] === 'active' ? 'Aktif' : 'Devre dışı' ?></span></div>
    <div class="task-meta-grid user-meta-grid">
        <div><small>Erişim rolü</small><strong><?= kd_e($roleLabels[$user['role']] ?? $user['role']) ?></strong></div>
        <div><small>Son giriş</small><strong><?= $user['last_login_at'] ? kd_e(date('d.m.Y H:i', strtotime((string)$user['last_login_at']))) : 'Henüz giriş yapmadı' ?></strong></div>
        <div><small>Hesap oluşturma</small><strong><?= kd_e(date('d.m.Y H:i', strtotime((string)$user['created_at']))) ?></strong></div>
    </div>
    <?php if (kd_can('users.manage') && !$isSelf && $user['role'] !== 'owner'): ?>
        <form class="user-status-form" method="post" action="index.php?page=users&action=view&id=<?= (int)$user['id'] ?>">
            <?= kd_csrf_field() ?><input type="hidden" name="form_action" value="status"><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
            <span><?= $user['status'] === 'active' ? 'Bu kullanıcının sisteme erişimini durdurabilirsiniz.' : 'Bu kullanıcının sisteme erişimini yeniden açabilirsiniz.' ?></span>
            <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'inactive' : 'active' ?>">
            <button class="btn <?= $user['status'] === 'active' ? 'secondary' : 'primary' ?>" type="submit"><?= $user['status'] === 'active' ? 'Hesabı devre dışı bırak' : 'Hesabı etkinleştir' ?></button>
        </form>
    <?php endif; ?>
</section>
