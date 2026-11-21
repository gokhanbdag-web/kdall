<?php declare(strict_types=1); ?>
<section class="panel" style="margin-top:1rem">
<h3>Cari hesap ekstresi</h3>
<p class="module-intro">Kesilmiş faturalar ve kaydedilmiş tahsilat/ödemeler. Para birimleri ayrı hesaplanır. Hareketler sayfalanır; özetler tüm kayıtları kapsar.</p>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Para birimi</th><th>Faturalar</th><th>Ödemeler</th><th>Kalan bakiye</th></tr></thead><tbody>
<?php foreach($ledger['totals'] as $currency=>$total): ?><tr><td><?= kd_e($currency) ?></td><td><?= number_format($total['invoiced'],2,',','.') ?></td><td><?= number_format($total['paid'],2,',','.') ?></td><td><strong><?= number_format($total['invoiced']-$total['paid'],2,',','.') ?></strong></td></tr><?php endforeach; ?>
<?php if(!$ledger['totals']): ?><tr><td colspan="4">Henüz cari hareket bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div>
<h3>Cari hareketler (<?= (int)($ledger['entry_count']??0) ?> kayıt)</h3>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Tarih</th><th>Tür</th><th>Referans</th><th>Tutar</th><th>Para birimi</th></tr></thead><tbody>
<?php foreach($ledger['entries'] as $entry): ?><tr><td><?= kd_e($entry['entry_date']) ?></td><td><?= $entry['entry_type']==='invoice'?'Fatura':'Tahsilat / ödeme' ?></td><td><?= kd_e($entry['reference_no']) ?></td><td><?= number_format((float)$entry['amount'],2,',','.') ?></td><td><?= kd_e($entry['currency']) ?></td></tr><?php endforeach; ?>
<?php if(!$ledger['entries']): ?><tr><td colspan="5">Hareket bulunmuyor.</td></tr><?php endif; ?>
</tbody></table></div>
<?php if(($ledger['pages']??1)>1): ?>
<nav aria-label="Cari hareket sayfaları" style="display:flex;gap:.75rem;align-items:center;margin-top:1rem">
<?php $ledgerQuery=$_GET; ?>
<?php if($ledger['page']>1): $ledgerQuery['ledger_page']=$ledger['page']-1; ?><a href="?<?= kd_e(http_build_query($ledgerQuery)) ?>">Önceki</a><?php endif; ?>
<span>Sayfa <?= (int)$ledger['page'] ?> / <?= (int)$ledger['pages'] ?></span>
<?php if($ledger['page']<$ledger['pages']): $ledgerQuery['ledger_page']=$ledger['page']+1; ?><a href="?<?= kd_e(http_build_query($ledgerQuery)) ?>">Sonraki</a><?php endif; ?>
</nav>
<?php endif; ?>
</section>
