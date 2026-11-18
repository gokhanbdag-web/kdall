<?php
declare(strict_types=1);
$roleLabels = ['owner' => 'Sahip', 'admin' => 'Yönetici', 'manager' => 'Yönetici Yardımcısı', 'staff' => 'Personel'];
?>
<section class="module-toolbar">
    <div><p class="module-intro">Sisteme erişen kullanıcıları ve hesap durumlarını yönetin.</p><span class="result-count"><?= number_format($total, 0, ',', '.') ?> kullanıcı</span></div>
    <?php if (kd_can('users.manage')): ?><a class="btn primary action-link" href="index.php?page=users&action=create">＋ Yeni kullanıcı</a><?php endif; ?>
</section>
<section class="panel customer-list-panel">
    <form class="filter-bar" method="get">
        <input type="hidden" name="page" value="users">
        <label class="filter-search"><span>⌕</span><input type="search" name="q" value="<?= kd_e($q) ?>" placeholder="Ad soyad veya e-posta ara"></label>
        <select name="role" aria-label="Kullanıcı rolü"><option value="">Tüm roller</option><?php foreach ($roles as $item): ?><option value="<?= kd_e($item['slug']) ?>" <?= $role === $item['slug'] ? 'selected' : '' ?>><?= kd_e($item['name']) ?></option><?php endforeach; ?><option value="owner" <?= $role === 'owner' ? 'selected' : '' ?>>Sahip</option></select>
        <select name="status" aria-label="Hesap durumu"><option value="">Tüm durumlar</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Devre dışı</option></select>
        <button class="btn secondary" type="submit">Filtrele</button>
        <?php if ($q !== '' || $role !== '' || $status !== ''): ?><a class="clear-filter" href="index.php?page=users">Temizle</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Kullanıcı</th><th>Rol</th><th>Durum</th><th>Son giriş</th><th>Kayıt tarihi</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $item): ?>
                <tr>
                    <td><a class="customer-name" href="index.php?page=users&action=view&id=<?= (int)$item['id'] ?>"><?= kd_e($item['name']) ?></a><small class="table-sub"><?= kd_e($item['email']) ?></small></td>
                    <td><span class="user-role"><?= kd_e($roleLabels[$item['role']] ?? $item['role']) ?></span></td>
                    <td><span class="customer-status <?= $item['status'] === 'active' ? 'is-active' : 'is-inactive' ?>"><?= $item['status'] === 'active' ? 'Aktif' : 'Devre dışı' ?></span></td>
                    <td><?= $item['last_login_at'] ? kd_e(date('d.m.Y H:i', strtotime((string)$item['last_login_at']))) : 'Henüz giriş yapmadı' ?></td>
                    <td><?= kd_e(date('d.m.Y', strtotime((string)$item['created_at']))) ?></td>
                    <td class="table-actions"><a href="index.php?page=users&action=view&id=<?= (int)$item['id'] ?>" aria-label="Kullanıcıyı görüntüle">↗</a><?php if (kd_can('users.manage')): ?><a href="index.php?page=users&action=edit&id=<?= (int)$item['id'] ?>" aria-label="Kullanıcıyı düzenle">✎</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="6"><div class="empty-state"><strong>Kullanıcı bulunamadı</strong><p>Arama ve filtreleri değiştirebilirsiniz.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?><div class="pagination"><span><?= $page ?> / <?= $pages ?> sayfa</span><div><?php if ($page > 1): ?><a href="<?= kd_e('index.php?page=users&q='.rawurlencode($q).'&role='.rawurlencode($role).'&status='.rawurlencode($status).'&p='.($page-1)) ?>">← Önceki</a><?php endif; ?><?php if ($page < $pages): ?><a href="<?= kd_e('index.php?page=users&q='.rawurlencode($q).'&role='.rawurlencode($role).'&status='.rawurlencode($status).'&p='.($page+1)) ?>">Sonraki →</a><?php endif; ?></div></div><?php endif; ?>
</section>
