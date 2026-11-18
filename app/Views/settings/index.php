<?php
declare(strict_types=1);
?>
<section class="module-toolbar"><div><p class="module-intro">İşletme kimliğini ve sistemin temel çalışma tercihlerini yönetin.</p></div></section>
<?php if ($saved): ?><div class="settings-success"><span>✓</span> Ayarlar başarıyla kaydedildi.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ', $errors)) ?></div><?php endif; ?>
<form method="post" action="index.php?page=settings" class="settings-form">
    <?= kd_csrf_field() ?>
    <section class="panel settings-panel">
        <div class="form-section-title"><div><h3>İşletme bilgileri</h3><p>Fatura, belge ve işletme kayıtlarında kullanılacak temel bilgiler.</p></div></div>
        <div class="customer-fields">
            <label>İşletme adı *<input name="business_name" maxlength="190" required value="<?= kd_e($settings['business_name']) ?>"></label>
            <label>Ticari unvan<input name="legal_name" maxlength="190" value="<?= kd_e($settings['legal_name']) ?>"></label>
            <label>Vergi dairesi<input name="tax_office" maxlength="120" value="<?= kd_e($settings['tax_office']) ?>"></label>
            <label>Vergi numarası<input name="tax_number" maxlength="40" value="<?= kd_e($settings['tax_number']) ?>"></label>
            <label>E-posta<input type="email" name="email" value="<?= kd_e($settings['email']) ?>"></label>
            <label>Telefon<input name="phone" maxlength="40" value="<?= kd_e($settings['phone']) ?>"></label>
            <label>Web sitesi<input type="url" name="website" placeholder="https://" value="<?= kd_e($settings['website']) ?>"></label>
            <label class="field-wide">Adres<textarea name="address" rows="3"><?= kd_e($settings['address']) ?></textarea></label>
        </div>
    </section>
    <section class="panel settings-panel">
        <div class="form-section-title"><div><h3>Bölgesel ayarlar</h3><p>Para birimi, saat dilimi ve tarih gösterimi.</p></div></div>
        <div class="customer-fields">
            <label>Varsayılan para birimi<select name="currency"><?php foreach (['TRY'=>'Türk Lirası (₺)','USD'=>'ABD Doları ($)','EUR'=>'Euro (€)','GBP'=>'İngiliz Sterlini (£)'] as $key=>$label): ?><option value="<?= kd_e($key) ?>" <?= $settings['currency']===$key?'selected':'' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select></label>
            <label>Saat dilimi<select name="timezone"><?php foreach (['Europe/Istanbul'=>'İstanbul (UTC+03:00)','Europe/London'=>'Londra','Europe/Berlin'=>'Berlin','UTC'=>'UTC','America/New_York'=>'New York','Asia/Dubai'=>'Dubai'] as $key=>$label): ?><option value="<?= kd_e($key) ?>" <?= $settings['timezone']===$key?'selected':'' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select></label>
            <label>Tarih biçimi<select name="date_format"><?php foreach (['d.m.Y'=>'18.11.2026','Y-m-d'=>'2026-11-18','d/m/Y'=>'18/11/2026'] as $key=>$label): ?><option value="<?= kd_e($key) ?>" <?= $settings['date_format']===$key?'selected':'' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="form-actions"><button class="btn primary" type="submit">Ayarları kaydet</button></div>
    </section>
</form>
