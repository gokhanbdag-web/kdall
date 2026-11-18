<?php
declare(strict_types=1);
$isEdit = $action === 'edit';
$roleLabels = ['owner' => 'Sahip', 'admin' => 'Yönetici', 'manager' => 'Yönetici Yardımcısı', 'staff' => 'Personel'];
$isOwnerAccount = $isEdit && $user['role'] === 'owner';
?>
<div class="module-toolbar"><div><p class="module-intro">Kullanıcı hesabı, erişim rolü ve parola ayarları.</p></div><a class="btn secondary action-link" href="index.php?page=users">← Kullanıcı listesi</a></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ', $errors)) ?></div><?php endif; ?>
<section class="panel customer-form-panel">
    <form method="post" action="index.php?page=users&action=<?= $isEdit ? 'edit&id='.(int)$user['id'] : 'create' ?>" autocomplete="off">
        <?= kd_csrf_field() ?>
        <input type="hidden" name="form_action" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>"><?php endif; ?>
        <div class="form-section-title"><div><h3><?= $isEdit ? 'Kullanıcı bilgileri' : 'Yeni kullanıcı hesabı' ?></h3><p><?= $isEdit ? 'Parola alanını boş bırakırsanız mevcut parola korunur.' : 'İlk giriş için en az 12 karakterli parola belirleyin.' ?></p></div></div>
        <div class="customer-fields">
            <label>Ad soyad *<input name="name" maxlength="160" required value="<?= kd_e($user['name']) ?>"></label>
            <label>E-posta adresi *<input type="email" name="email" maxlength="190" required value="<?= kd_e($user['email']) ?>"></label>
            <label><?= $isEdit ? 'Yeni parola' : 'Parola *' ?><input type="password" name="password" minlength="12" <?= $isEdit ? '' : 'required' ?> autocomplete="new-password"><small><?= $isEdit ? 'Değiştirmek istemiyorsanız boş bırakın.' : 'En az 12 karakter.' ?></small></label>
            <?php if (kd_can('roles.manage') && !$isOwnerAccount): ?>
                <label>Erişim rolü<select name="role"><?php foreach ($roles as $item): ?><option value="<?= kd_e($item['slug']) ?>" <?= $user['role'] === $item['slug'] ? 'selected' : '' ?>><?= kd_e($item['name']) ?><?= $item['description'] ? ' · '.kd_e($item['description']) : '' ?></option><?php endforeach; ?></select></label>
            <?php else: ?>
                <label>Erişim rolü<input value="<?= kd_e($roleLabels[$user['role']] ?? $user['role']) ?>" disabled><small>Rol değişikliği için rol yönetimi yetkisi gerekir.</small></label>
            <?php endif; ?>
            <?php if (!$isOwnerAccount && (int)$user['id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
                <label>Hesap durumu<select name="status"><option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Aktif</option><option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Devre dışı</option></select></label>
            <?php else: ?>
                <label>Hesap durumu<input value="Aktif" disabled><small>Sahip hesabı ve oturum açtığınız hesap korunur.</small></label>
            <?php endif; ?>
        </div>
        <div class="form-actions"><a class="btn secondary" href="index.php?page=users">Vazgeç</a><button class="btn primary" type="submit"><?= $isEdit ? 'Değişiklikleri kaydet' : 'Kullanıcıyı oluştur' ?></button></div>
    </form>
</section>
