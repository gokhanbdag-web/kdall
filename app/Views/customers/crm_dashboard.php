<?php declare(strict_types=1); ?>
<section class="module-toolbar"><div><h2>CRM Merkezi</h2><p class="module-intro">Tüm müşterilerin satış fırsatları, ilgili kişileri ve takipleri.</p></div><a class="btn secondary" href="index.php?page=customers">Müşteri listesi</a></section>
<section class="panel"><h3>Genel görünüm</h3><div class="table-wrap"><table class="data-table"><tbody>
<?php foreach (['customers'=>'Müşteriler','contacts'=>'İlgili kişiler','opportunities'=>'Satış fırsatları','followups'=>'Takipler','open_followups'=>'Açık takipler','overdue'=>'Geciken takipler'] as $key=>$label): ?><tr><th><?= kd_e($label) ?></th><td><?= (int)$stats[$key] ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="panel"><h3>Satış fırsatları — son 50</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Müşteri</th><th>Fırsat</th><th>Aşama</th><th>Tutar</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($opportunities as $item): ?><tr><td><?= kd_e($item['customer_name']) ?></td><td><?= kd_e($item['title']) ?></td><td><?= kd_e($item['stage']) ?></td><td><?= kd_e(number_format((float)$item['amount'],2,',','.')) ?></td><td><a href="index.php?page=customers&amp;action=crm&amp;id=<?= (int)$item['customer_id'] ?>">Yönet</a></td></tr><?php endforeach; ?>
<?php if (!$opportunities): ?><tr><td colspan="5">Fırsat bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel"><h3>Takipler — ilk 50</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Müşteri</th><th>Takip</th><th>Tarih</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($followups as $item): ?><tr><td><?= kd_e($item['customer_name']) ?></td><td><?= kd_e($item['title']) ?></td><td><?= kd_e($item['due_at']) ?></td><td><?= kd_e($item['status']) ?></td><td><a href="index.php?page=customers&amp;action=crm&amp;id=<?= (int)$item['customer_id'] ?>">Yönet</a></td></tr><?php endforeach; ?>
<?php if (!$followups): ?><tr><td colspan="5">Takip bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="panel"><h3>İlgili kişiler — son 50</h3><div class="table-wrap"><table class="data-table"><thead><tr><th>Müşteri</th><th>Kişi</th><th>Görev</th><th>E-posta</th><th>İşlem</th></tr></thead><tbody>
<?php foreach ($contacts as $item): ?><tr><td><?= kd_e($item['customer_name']) ?></td><td><?= kd_e($item['name']) ?></td><td><?= kd_e($item['role']) ?></td><td><?= kd_e($item['email']) ?></td><td><a href="index.php?page=customers&amp;action=crm&amp;id=<?= (int)$item['customer_id'] ?>">Yönet</a></td></tr><?php endforeach; ?>
<?php if (!$contacts): ?><tr><td colspan="5">İlgili kişi bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div></section>
