<?php declare(strict_types=1); ?>
<?php
$crmPager=static function(string $key) use ($pagination): void {
 $meta=$pagination[$key];
 echo '<div class="pagination"><span>'.(int)$meta['total'].' kayıt · '.(int)$meta['page'].' / '.(int)$meta['pages'].' sayfa</span><div>';
 foreach ([-1=> '← Önceki',1=>'Sonraki →'] as $step=>$label) {
  $target=$meta['page']+$step;
  if ($target<1 || $target>$meta['pages']) continue;
  $params=$_GET;
  $params['page']='customers';$params['action']='crm_dashboard';$params[$key]=$target;
  echo '<a href="'.kd_e('index.php?'.http_build_query($params)).'">'.kd_e($label).'</a> ';
 }
 echo '</div></div>';
};
?>

<section class="module-toolbar"><div><h2>CRM Merkezi</h2><p class="module-intro">Tüm müşterilerin satış fırsatları, ilgili kişileri ve takipleri.</p></div><div><a class="btn secondary" href="index.php?page=customers&amp;action=crm_reports">CRM Raporları</a> <a class="btn secondary" href="index.php?page=customers">Müşteri listesi</a></div></section>
<section class="panel"><h3>Satış tahmini</h3><p>Açık fırsat toplamı: <?= kd_e(number_format($forecast['total'],2,',','.')) ?> · Olasılık ağırlıklı tahmin: <strong><?= kd_e(number_format($forecast['weighted'],2,',','.')) ?></strong> · Kapanış tarihi belirlenen: <?= (int)$forecast['dated'] ?> · Tarihsiz: <?= (int)$forecast['undated'] ?></p></section><section class="panel"><h3>CRM arama ve filtreleme</h3><form method="get" class="filter-bar">
<input type="hidden" name="page" value="customers"><input type="hidden" name="action" value="crm_dashboard">
<label>Arama <input type="search" name="q" value="<?= kd_e($q) ?>" placeholder="Müşteri, kişi veya kayıt adı"></label>
<label>Müşteri ID <input type="number" min="1" name="customer_id" value="<?= $customerId?:'' ?>"></label>
<label>Fırsat aşaması <select name="stage"><option value="">Tümü</option><?php foreach (['lead','qualified','proposal','negotiation','won','lost'] as $option): ?><option value="<?= kd_e($option) ?>" <?= $stage===$option?'selected':'' ?>><?= kd_e($option) ?></option><?php endforeach; ?></select></label>
<label>Takip durumu <select name="status"><option value="">Tümü</option><option value="open" <?= $status==='open'?'selected':'' ?>>Açık</option><option value="done" <?= $status==='done'?'selected':'' ?>>Tamamlandı</option></select></label>
<label>Sorumlu kullanıcı ID <input type="number" min="1" name="assigned_user_id" value="<?= $assignedUserId?:'' ?>"></label>
<label>Başlangıç <input type="date" name="from" value="<?= kd_e($from) ?>"></label><label>Bitiş <input type="date" name="to" value="<?= kd_e($to) ?>"></label>
<label>En az tutar <input type="number" min="0" step="0.01" name="min_amount" value="<?= kd_e($minAmount) ?>"></label>
<label>En fazla tutar <input type="number" min="0" step="0.01" name="max_amount" value="<?= kd_e($maxAmount) ?>"></label>
<button class="btn primary" type="submit">Filtrele</button><a class="btn secondary" href="index.php?page=customers&amp;action=crm_dashboard">Temizle</a>
</form><p class="module-intro">Aşama ve tutar fırsatlara; durum ve sorumlu takip kayıtlarına uygulanır. Tarih filtresi fırsat oluşturma ve takip son tarihine uygulanır.</p></section>
<section class="panel"><h3>Genel görünüm</h3><div class="table-wrap"><table class="data-table"><tbody>
<?php foreach (['customers'=>'Müşteriler','contacts'=>'İlgili kişiler','opportunities'=>'Satış fırsatları','followups'=>'Takipler','open_followups'=>'Açık takipler','overdue'=>'Geciken takipler'] as $key=>$label): ?><tr><th><?= kd_e($label) ?></th><td><?= (int)$stats[$key] ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel"><h3>Satış fırsatları </h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Müşteri</th><th>Fırsat</th><th>Aşama</th><th>Tutar</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($opportunities as $item): ?><tr><td><?= kd_e($item['customer_name']) ?></td><td><?= kd_e($item['title']) ?></td><td><?= kd_e($item['stage']) ?> · <?= (int)\KodVadAll\Modules\Customers\CrmForecast::probability((string)$item['stage'],$item['probability']===null?null:(int)$item['probability']) ?>%</td><td><?= kd_e(number_format((float)$item['amount'],2,',','.')) ?></td><td><a href="index.php?page=customers&amp;action=crm&amp;id=<?= (int)$item['customer_id'] ?>">Yönet</a></td></tr><?php endforeach; ?>
<?php if (!$opportunities): ?><tr><td colspan="5">Fırsat bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div><?php $crmPager('opp_page'); ?></section>
<section class="panel"><h3>Takipler </h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Müşteri</th><th>Takip</th><th>Tarih</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($followups as $item): ?><tr><td><?= kd_e($item['customer_name']) ?></td><td><?= kd_e($item['title']) ?></td><td><?= kd_e($item['due_at']) ?></td><td><?= kd_e($item['status']) ?></td><td><a href="index.php?page=customers&amp;action=crm&amp;id=<?= (int)$item['customer_id'] ?>">Yönet</a></td></tr><?php endforeach; ?>
<?php if (!$followups): ?><tr><td colspan="5">Takip bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div><?php $crmPager('follow_page'); ?></section>
<section class="panel"><h3>İlgili kişiler — son 50</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Müşteri</th><th>Kişi</th><th>Görev</th><th>E-posta</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($contacts as $item): ?><tr><td><?= kd_e($item['customer_name']) ?></td><td><?= kd_e($item['name']) ?></td><td><?= kd_e($item['role']) ?></td><td><?= kd_e($item['email']) ?></td><td><a href="index.php?page=customers&amp;action=crm&amp;id=<?= (int)$item['customer_id'] ?>">Yönet</a></td></tr><?php endforeach; ?>
<?php if (!$contacts): ?><tr><td colspan="5">İlgili kişi bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div><?php $crmPager('contact_page'); ?></section>
