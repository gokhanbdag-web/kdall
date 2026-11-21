<?php
declare(strict_types=1);
?>
<p><a class="btn secondary action-link" href="index.php?page=customers&amp;action=crm_dashboard">CRM Merkezi</a></p>
<section class="module-toolbar">
    <div><p class="module-intro">Müşteri ilişkilerinizi tek yerden yönetin.</p><span class="result-count"><?= number_format($total,0,',','.') ?> müşteri kaydı</span></div>
    <?php if (kd_can('customers.manage')): ?><a class="btn primary action-link" href="index.php?page=customers&action=create">＋ Yeni müşteri</a><?php endif; ?>
</section>
<section class="panel customer-list-panel">
    <form class="filter-bar" method="get">
        <input type="hidden" name="page" value="customers">
        <label class="filter-search"><span>⌕</span><input type="search" name="q" value="<?= kd_e($q) ?>" placeholder="İsim, e-posta, telefon veya kod ara"></label>
        <select name="type" aria-label="Müşteri türü">
            <option value="">Tüm türler</option>
            <option value="individual" <?= $type==='individual'?'selected':'' ?>>Bireysel</option>
            <option value="company" <?= $type==='company'?'selected':'' ?>>Kurumsal</option>
        </select>
        <select name="status" aria-label="Durum">
            <option value="">Tüm durumlar</option>
            <option value="active" <?= $status==='active'?'selected':'' ?>>Aktif</option>
            <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Pasif</option>
        </select>
        <button class="btn secondary" type="submit">Filtrele</button>
        <?php if ($q!=='' || $type!=='' || $status!==''): ?><a class="clear-filter" href="index.php?page=customers">Temizle</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Müşteri</th><th>İletişim</th><th>Tür</th><th>Durum</th><th>Kayıt tarihi</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><a class="customer-name" href="index.php?page=customers&action=view&id=<?= (int)$customer['id'] ?>"><?= kd_e($customer['name']) ?></a><small class="table-sub"><?= kd_e($customer['customer_code'] ?: '—') ?></small></td>
                    <td><span><?= kd_e($customer['email'] ?: '—') ?></span><small class="table-sub"><?= kd_e($customer['phone'] ?: '') ?></small></td>
                    <td><?= $customer['type']==='company'?'Kurumsal':'Bireysel' ?></td>
                    <td><span class="customer-status <?= $customer['status']==='active'?'is-active':'is-inactive' ?>"><?= $customer['status']==='active'?'Aktif':'Pasif' ?></span></td>
                    <td><?= kd_e(date('d.m.Y',strtotime((string)$customer['created_at']))) ?></td>
                    <td class="table-actions"><a href="index.php?page=customers&action=view&id=<?= (int)$customer['id'] ?>" aria-label="Görüntüle">↗</a><?php if (kd_can('customers.manage')): ?><a href="index.php?page=customers&action=edit&id=<?= (int)$customer['id'] ?>" aria-label="Düzenle">✎</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$customers): ?><tr><td colspan="6"><div class="empty-state"><strong>Henüz müşteri bulunmuyor</strong><p>İlk müşteri kaydınızı oluşturarak başlayın.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages>1): ?><div class="pagination"><span><?= $page ?> / <?= $pages ?> sayfa</span><div><?php if ($page>1): ?><a href="<?= kd_e('index.php?page=customers&q='.rawurlencode($q).'&type='.rawurlencode($type).'&status='.rawurlencode($status).'&p='.($page-1)) ?>">← Önceki</a><?php endif; ?><?php if ($page<$pages): ?><a href="<?= kd_e('index.php?page=customers&q='.rawurlencode($q).'&type='.rawurlencode($type).'&status='.rawurlencode($status).'&p='.($page+1)) ?>">Sonraki →</a><?php endif; ?></div></div><?php endif; ?>
</section>
