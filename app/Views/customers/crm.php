<?php declare(strict_types=1); ?>

<?php $crmPager=static function(string $key) use ($pagination,$id): void {
 $meta=$pagination[$key];
 echo '<div class="pagination"><span>'.(int)$meta['total'].' kayıt · '.(int)$meta['page'].' / '.(int)$meta['pages'].' sayfa</span><div>';
 foreach ([-1=>'← Önceki',1=>'Sonraki →'] as $step=>$label) {
  $target=$meta['page']+$step;
  if ($target<1 || $target>$meta['pages']) continue;
  $params=$_GET;$params['page']='customers';$params['action']='crm';$params['id']=$id;$params[$key.'_page']=$target;
  echo '<a href="'.kd_e('index.php?'.http_build_query($params)).'">'.kd_e($label).'</a> ';
 }
 echo '</div></div>';
}; ?>
<div class="module-toolbar"><a class="btn secondary" href="index.php?page=customers&action=view&id=<?= (int)$id ?>">← Müşteri profili</a><h2><?= kd_e($customer['name']) ?> — CRM</h2></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ',$errors)) ?></div><?php endif; ?>
<section class="panel"><h3>CRM genel bakış</h3><p><strong>Geciken açık takip:</strong> <?= (int)$overdue ?></p><div class="table-wrap"><table class="data-table"><thead><tr><th>Satış aşaması</th><th>Fırsat sayısı</th><th>Toplam tutar</th></tr></thead><tbody><?php foreach ($pipeline as $row): ?><tr><td><?= kd_e($row['stage']) ?></td><td><?= (int)$row['total'] ?></td><td><?= kd_e(number_format((float)$row['value'],2,',','.')) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel"><h3>İlgili kişiler</h3><?php $crmPager('contacts'); ?>
<?php foreach ($data['contacts'] as $item): ?><p><strong><?= kd_e($item['name']) ?></strong> · <?= kd_e($item['role']) ?> · <?= kd_e($item['email']) ?> · <?= kd_e($item['phone']) ?><?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="delete_contact"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><button class="btn secondary" type="submit">Kişiyi sil</button></form><?php endif; ?></p><?php endforeach; ?>
<?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="contact"><label>Ad soyad<input required maxlength="190" name="title"></label><label>Görev<input name="role" maxlength="120"></label><label>E-posta<input type="email" name="email"></label><label>Telefon<input name="phone" maxlength="40"></label><button class="btn primary">Kişi ekle</button></form><?php endif; ?>
</section>
<section class="panel"><h3>Satış fırsatları</h3><?php $crmPager('opportunities'); ?>
<?php foreach ($data['opportunities'] as $item): ?><p><strong><?= kd_e($item['title']) ?></strong> · <?= kd_e(number_format((float)$item['amount'],2,',','.')) ?> · <?= kd_e($item['stage']) ?><?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="stage"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><select name="stage"><?php foreach (['lead'=>'Potansiyel','qualified'=>'Nitelikli','proposal'=>'Teklif','negotiation'=>'Görüşme','won'=>'Kazanıldı','lost'=>'Kaybedildi'] as $stage=>$label): ?><option value="<?= kd_e($stage) ?>" <?= $item['stage']===$stage?'selected':'' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select><button class="btn secondary">Aşamayı güncelle</button></form><?php endif; ?><?php if (kd_can('customers.manage')): ?><form method="post" onsubmit="return confirm('Bu satış fırsatı silinsin mi?')"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="delete_opportunity"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="confirm_delete" value="1"><button class="btn secondary" type="submit">Fırsatı sil</button></form><?php endif; ?></p><?php endforeach; ?>
<?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="opportunity"><label>Başlık<input required maxlength="190" name="title"></label><label>Tutar<input type="number" min="0" step="0.01" name="amount" required></label><label>Aşama<select name="stage"><option value="lead">Potansiyel</option><option value="qualified">Nitelikli</option><option value="proposal">Teklif</option><option value="negotiation">Görüşme</option><option value="won">Kazanıldı</option><option value="lost">Kaybedildi</option></select></label><button class="btn primary">Fırsat ekle</button></form><?php endif; ?>
</section>
<section class="panel"><h3>Takipler</h3><?php $crmPager('followups'); ?>
<?php foreach ($data['followups'] as $item): ?><p><?= kd_e($item['title']) ?> · <?= kd_e($item['due_at']) ?> · <?= kd_e($item['status']) ?><?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="<?= $item['status']==='done'?'reopen':'done' ?>"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><button class="btn secondary"><?= $item['status']==='done'?'Yeniden aç':'Tamamla' ?></button></form><?php endif; ?><?php if (kd_can('customers.manage')): ?><form method="post" onsubmit="return confirm('Bu takip kaydı silinsin mi?')"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="delete_followup"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="confirm_delete" value="1"><button class="btn secondary" type="submit">Takibi sil</button></form><?php endif; ?></p><?php endforeach; ?>
<?php if (kd_can('customers.manage')): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="followup"><label>Başlık<input name="title" maxlength="190" required></label><label>Tarih<input type="datetime-local" name="due_at" required></label><button class="btn primary">Takip ekle</button></form><?php endif; ?>
</section>
<section class="panel"><h3>Takip ve satış fırsatı ilişkileri</h3>
<?php if (kd_can('customers.manage')): ?>
<?php foreach ($data['followups'] as $item): ?>
<form method="post">
<?= kd_csrf_field() ?>
<input type="hidden" name="kind" value="link_followup_opportunity">
<input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
<label><?= kd_e($item['title']) ?>
<select name="opportunity_id">
<option value="0">Bağlantı yok</option>
<?php foreach ($data['opportunities'] as $opportunity): ?>
<option value="<?= (int)$opportunity['id'] ?>" <?= (int)($item['opportunity_id']??0)===(int)$opportunity['id']?'selected':'' ?>><?= kd_e($opportunity['title']) ?></option>
<?php endforeach; ?>
</select></label>
<button class="btn secondary" type="submit">Bağlantıyı kaydet</button>
</form>
<?php endforeach; ?>
<?php else: ?>
<?php foreach ($data['followups'] as $item): ?>
<?php foreach ($data['opportunities'] as $opportunity): ?>
<?php if ((int)($item['opportunity_id']??0)===(int)$opportunity['id']): ?>
<p><?= kd_e($item['title']) ?> — <?= kd_e($opportunity['title']) ?></p>
<?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>
<?php endif; ?>
</section>
<section class="panel"><h3>Diğer ERP işlemleri</h3><p><a class="btn secondary" href="index.php?page=quotes">Teklifler</a> <a class="btn secondary" href="index.php?page=tasks">Görevler</a> <a class="btn secondary" href="index.php?page=customers&action=view&id=<?= (int)$id ?>">Cari hesap ve görüşme geçmişi</a></p></section>

<section class="panel"><h3>ERP bağlantıları</h3>
<?php if (kd_can('customers.manage') && kd_can('quotes.view') && $quotes): ?>
<h4>Fırsatı teklife bağla</h4>
<?php foreach ($data['opportunities'] as $item): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="link_quote"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><label><?= kd_e($item['title']) ?><select name="quote_id"><?php foreach ($quotes as $quote): ?><option value="<?= (int)$quote['id'] ?>" <?= (int)$item['quote_id']===(int)$quote['id']?'selected':'' ?>><?= kd_e($quote['quote_number']) ?></option><?php endforeach; ?></select></label><button class="btn secondary">Teklifle bağla</button></form><?php endforeach; ?>
<?php endif; ?>
<?php if (kd_can('customers.manage') && kd_can('tasks.view') && $tasks): ?>
<h4>Takibi göreve bağla</h4>
<?php foreach ($data['followups'] as $item): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="link_task"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><label><?= kd_e($item['title']) ?><select name="task_id"><?php foreach ($tasks as $task): ?><option value="<?= (int)$task['id'] ?>" <?= (int)$item['task_id']===(int)$task['id']?'selected':'' ?>><?= kd_e($task['title']) ?></option><?php endforeach; ?></select></label><button class="btn secondary">Göreve bağla</button></form><?php endforeach; ?>
<?php endif; ?>
</section>

<section class="panel"><h3>Kayıt düzenleme</h3>
<?php if (kd_can('customers.manage')): ?>
<h4>İlgili kişiler</h4>
<?php foreach ($data['contacts'] as $item): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="edit_contact"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><label>Ad<input name="title" maxlength="190" required value="<?= kd_e($item['name']) ?>"></label><label>Görev<input name="role" maxlength="120" value="<?= kd_e($item['role']) ?>"></label><label>E-posta<input type="email" name="email" value="<?= kd_e($item['email']) ?>"></label><label>Telefon<input name="phone" maxlength="40" value="<?= kd_e($item['phone']) ?>"></label><button class="btn secondary">Kişiyi güncelle</button></form><?php endforeach; ?>
<h4>Satış fırsatları</h4>
<?php foreach ($data['opportunities'] as $item): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="edit_opportunity"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><label>Başlık<input name="title" maxlength="190" required value="<?= kd_e($item['title']) ?>"></label><label>Tutar<input type="number" name="amount" step="0.01" min="0" required value="<?= kd_e($item['amount']) ?>"></label><label>Aşama<select name="stage"><?php foreach (['lead'=>'Potansiyel','qualified'=>'Nitelikli','proposal'=>'Teklif','negotiation'=>'Görüşme','won'=>'Kazanıldı','lost'=>'Kaybedildi'] as $stage=>$label): ?><option value="<?= kd_e($stage) ?>" <?= $stage===$item['stage']?'selected':'' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select></label><button class="btn secondary">Fırsatı güncelle</button></form><?php endforeach; ?>
<h4>Takipler</h4>
<?php foreach ($data['followups'] as $item): ?><form method="post"><?= kd_csrf_field() ?><input type="hidden" name="kind" value="edit_followup"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><label>Başlık<input name="title" maxlength="190" required value="<?= kd_e($item['title']) ?>"></label><label>Tarih<input type="datetime-local" name="due_at" required value="<?= kd_e(date('Y-m-d\TH:i',strtotime($item['due_at']))) ?>"></label><button class="btn secondary">Takibi güncelle</button></form><?php endforeach; ?>
<?php endif; ?>
</section>
