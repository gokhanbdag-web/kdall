<?php declare(strict_types=1); ?>
<div class="module-toolbar"><a class="btn secondary" href="index.php?page=customers&action=view&id=<?= (int)$id ?>">← Müşteri profili</a><h2><?= kd_e($customer['name']) ?> — CRM</h2></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ',$errors)) ?></div><?php endif; ?>
<section class="panel"><h3>İlgili kişiler</h3>
<?php foreach ($data['contacts'] as $item): ?><p><strong><?= kd_e($item['name']) ?></strong> · <?= kd_e($item['role']) ?> · <?= kd_e($item['email']) ?> · <?= kd_e($item['phone']) ?></p><?php endforeach; ?>
<?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="contact"><label>Ad soyad<input required maxlength="190" name="title"></label><label>Görev<input name="role" maxlength="120"></label><label>E-posta<input type="email" name="email"></label><label>Telefon<input name="phone" maxlength="40"></label><button class="btn primary">Kişi ekle</button></form><?php endif; ?>
</section>
<section class="panel"><h3>Satış fırsatları</h3>
<?php foreach ($data['opportunities'] as $item): ?><p><strong><?= kd_e($item['title']) ?></strong> · <?= kd_e(number_format((float)$item['amount'],2,',','.')) ?> · <?= kd_e($item['stage']) ?></p><?php endforeach; ?>
<?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="opportunity"><label>Başlık<input required maxlength="190" name="title"></label><label>Tutar<input type="number" min="0" step="0.01" name="amount" required></label><label>Aşama<select name="stage"><option value="lead">Potansiyel</option><option value="qualified">Nitelikli</option><option value="proposal">Teklif</option><option value="negotiation">Görüşme</option><option value="won">Kazanıldı</option><option value="lost">Kaybedildi</option></select></label><button class="btn primary">Fırsat ekle</button></form><?php endif; ?>
</section>
<section class="panel"><h3>Takipler</h3>
<?php foreach ($data['followups'] as $item): ?><p><?= kd_e($item['title']) ?> · <?= kd_e($item['due_at']) ?> · <?= kd_e($item['status']) ?></p><?php endforeach; ?>
<?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="followup"><label>Başlık<input name="title" maxlength="190" required></label><label>Tarih<input type="datetime-local" name="due_at" required></label><button class="btn primary">Takip ekle</button></form><?php endif; ?>
</section>