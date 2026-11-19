<?php
declare(strict_types=1);
?>
<div class="module-toolbar"><div><p class="module-intro">Müşteri profili ve iletişim geçmişi.</p></div><div class="toolbar-actions"><a class="btn secondary action-link" href="index.php?page=customers">← Liste</a><?php if (kd_can('customers.manage')): ?><a class="btn primary action-link" href="index.php?page=customers&action=edit&id=<?= (int)$customer['id'] ?>">Düzenle</a><?php endif; ?></div></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ',$errors)) ?></div><?php endif; ?>
<div class="customer-detail-grid">
    <section class="panel customer-profile">
        <div class="profile-heading"><span class="profile-avatar"><?= kd_e(function_exists('mb_substr')?mb_strtoupper(mb_substr($customer['name'],0,1)):strtoupper(substr($customer['name'],0,1))) ?></span><div><h2><?= kd_e($customer['name']) ?></h2><span class="customer-code"><?= kd_e($customer['customer_code'] ?: 'Müşteri') ?></span></div><span class="customer-status <?= $customer['status']==='active'?'is-active':'is-inactive' ?>"><?= $customer['status']==='active'?'Aktif':'Pasif' ?></span></div>
        <div class="profile-info"><div><small>Müşteri türü</small><strong><?= $customer['type']==='company'?'Kurumsal':'Bireysel' ?></strong></div><div><small>E-posta</small><strong><?= kd_e($customer['email'] ?: '—') ?></strong></div><div><small>Telefon</small><strong><?= kd_e($customer['phone'] ?: '—') ?></strong></div><div><small>Vergi / T.C. no</small><strong><?= kd_e($customer['tax_number'] ?: '—') ?></strong></div><div><small>Kayıt tarihi</small><strong><?= kd_e(date('d.m.Y',strtotime((string)$customer['created_at']))) ?></strong></div></div>
        <?php if ($customer['notes']): ?><div class="profile-notes"><small>Genel notlar</small><p><?= nl2br(kd_e($customer['notes'])) ?></p></div><?php endif; ?>
        <?php if ($customer['status']==='active' && kd_can('customers.manage')): ?><form method="post" class="archive-form" action="index.php?page=customers&action=view&id=<?= (int)$customer['id'] ?>"><?= kd_csrf_field() ?><input type="hidden" name="form_action" value="archive"><input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>"><button type="submit" class="text-danger" onclick="return confirm('Bu müşteri kaydını pasife almak istiyor musunuz?')">Müşteriyi pasife al</button></form><?php endif; ?>
    </section>
    <section class="panel activity-panel">
        <div class="panel-head"><div><span class="eyebrow">CRM</span><h3>İletişim geçmişi</h3></div><span class="result-count"><?= count($activities) ?> kayıt</span></div>
        <?php if (kd_can('customers.manage')): ?><form class="activity-form" method="post" action="index.php?page=customers&action=view&id=<?= (int)$customer['id'] ?>"><?= kd_csrf_field() ?><input type="hidden" name="form_action" value="activity"><input type="hidden" name="customer_id" value="<?= (int)$customer['id'] ?>"><label>Yeni not / etkinlik<input name="subject" maxlength="190" placeholder="Örn. Telefon görüşmesi" required></label><label>Açıklama<textarea name="body" rows="3" placeholder="Görüşme notlarını yazın..."></textarea></label><button class="btn primary" type="submit">Kaydı ekle</button></form><?php endif; ?>
        <div class="timeline">
            <?php foreach ($activities as $activity): ?><article class="timeline-item"><span class="timeline-dot"></span><div><strong><?= kd_e($activity['subject']) ?></strong><?php if ($activity['body']): ?><p><?= nl2br(kd_e($activity['body'])) ?></p><?php endif; ?><small><?= kd_e($activity['user_name'] ?: 'Sistem') ?> · <?= kd_e(date('d.m.Y H:i',strtotime((string)$activity['created_at']))) ?></small></div></article><?php endforeach; ?>
            <?php if (!$activities): ?><p class="muted">Henüz iletişim kaydı bulunmuyor.</p><?php endif; ?>
        </div>
    </section>
</div>

<?php require KD_ROOT.'/app/Views/finance/party_ledger.php'; ?>
