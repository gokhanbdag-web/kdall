<?php
declare(strict_types=1);
$isEdit = $action === 'edit';
?>
<div class="module-toolbar"><div><p class="module-intro">Müşteri kartı bilgilerini girin.</p></div><a class="btn secondary action-link" href="index.php?page=customers">← Müşteri listesi</a></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ',$errors)) ?></div><?php endif; ?>
<section class="panel customer-form-panel">
    <form method="post" action="index.php?page=customers&action=<?= $isEdit?'edit&id='.(int)$customer['id']:'create' ?>">
        <?= kd_csrf_field() ?>
        <input type="hidden" name="form_action" value="<?= $isEdit?'update':'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>"><?php endif; ?>
        <div class="form-section-title"><div><h3><?= $isEdit?'Müşteri bilgileri':'Yeni müşteri kartı' ?></h3><p>Yıldızlı alanlar zorunludur.</p></div><?php if ($isEdit): ?><span class="customer-code"><?= kd_e($customer['customer_code'] ?: 'Kod atanmadı') ?></span><?php endif; ?></div>
        <div class="customer-fields">
            <label>Müşteri türü<select name="type"><option value="individual" <?= $customer['type']==='individual'?'selected':'' ?>>Bireysel</option><option value="company" <?= $customer['type']==='company'?'selected':'' ?>>Kurumsal</option></select></label>
            <label>Ad soyad / Firma adı *<input name="name" maxlength="190" required value="<?= kd_e($customer['name']) ?>"></label>
            <label>E-posta adresi<input type="email" name="email" value="<?= kd_e($customer['email']) ?>"></label>
            <label>Telefon<input name="phone" maxlength="40" value="<?= kd_e($customer['phone']) ?>"></label>
            <label>Vergi / T.C. numarası<input name="tax_number" maxlength="40" value="<?= kd_e($customer['tax_number']) ?>"></label>
            <?php if ($isEdit): ?><label>Durum<select name="status"><option value="active" <?= $customer['status']==='active'?'selected':'' ?>>Aktif</option><option value="inactive" <?= $customer['status']==='inactive'?'selected':'' ?>>Pasif</option></select></label><?php endif; ?>
            <label class="field-wide">Notlar<textarea name="notes" rows="4"><?= kd_e($customer['notes']) ?></textarea></label>
        </div>
        <div class="form-actions"><a class="btn secondary" href="index.php?page=customers">Vazgeç</a><button class="btn primary" type="submit"><?= $isEdit?'Değişiklikleri kaydet':'Müşteriyi kaydet' ?></button></div>
    </form>
</section>
