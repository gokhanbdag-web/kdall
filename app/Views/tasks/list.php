<?php
declare(strict_types=1);
$statusLabels = ['open' => 'Açık', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal'];
$priorityLabels = ['low' => 'Düşük', 'normal' => 'Normal', 'high' => 'Yüksek', 'urgent' => 'Acil'];
?>
<section class="module-toolbar">
    <div><p class="module-intro">İşleri planlayın, sorumluları belirleyin ve son tarihleri takip edin.</p><span class="result-count"><?= number_format($total, 0, ',', '.') ?> görev kaydı</span></div>
    <?php if (kd_can('tasks.manage')): ?><a class="btn primary action-link" href="index.php?page=tasks&action=create">＋ Yeni görev</a><?php endif; ?>
</section>
<section class="panel customer-list-panel task-list-panel">
    <form class="filter-bar" method="get">
        <input type="hidden" name="page" value="tasks">
        <label class="filter-search"><span>⌕</span><input type="search" name="q" value="<?= kd_e($q) ?>" placeholder="Görev başlığı veya açıklama ara"></label>
        <select name="status" aria-label="Görev durumu"><option value="">Tüm durumlar</option><?php foreach ($statusLabels as $key => $label): ?><option value="<?= kd_e($key) ?>" <?= $status === $key ? 'selected' : '' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select>
        <select name="priority" aria-label="Öncelik"><option value="">Tüm öncelikler</option><?php foreach ($priorityLabels as $key => $label): ?><option value="<?= kd_e($key) ?>" <?= $priority === $key ? 'selected' : '' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select>
        <select name="assigned_user_id" aria-label="Sorumlu"><option value="0">Tüm sorumlular</option><?php foreach ($users as $user): ?><option value="<?= (int)$user['id'] ?>" <?= $assignee === (int)$user['id'] ? 'selected' : '' ?>><?= kd_e($user['name']) ?></option><?php endforeach; ?></select>
        <button class="btn secondary" type="submit">Filtrele</button>
        <?php if ($q !== '' || $status !== '' || $priority !== '' || $assignee > 0): ?><a class="clear-filter" href="index.php?page=tasks">Temizle</a><?php endif; ?>
    </form>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Görev</th><th>Sorumlu</th><th>Son tarih</th><th>Öncelik</th><th>Durum</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <?php $overdue = !empty($task['due_at']) && strtotime((string)$task['due_at']) < time() && !in_array($task['status'], ['completed', 'cancelled'], true); ?>
                <tr>
                    <td><a class="customer-name" href="index.php?page=tasks&action=view&id=<?= (int)$task['id'] ?>"><?= kd_e($task['title']) ?></a><small class="table-sub"><?= kd_e(mb_strimwidth((string)($task['description'] ?? ''), 0, 90, '…')) ?></small></td>
                    <td><?= kd_e($task['assignee_name'] ?: 'Atanmadı') ?></td>
                    <td><span class="<?= $overdue ? 'task-overdue' : '' ?>"><?= $task['due_at'] ? kd_e(date('d.m.Y H:i', strtotime((string)$task['due_at']))) : '—' ?></span><?php if ($overdue): ?><small class="table-sub task-overdue">Süresi geçti</small><?php endif; ?></td>
                    <td><span class="task-priority priority-<?= kd_e($task['priority']) ?>"><?= kd_e($priorityLabels[$task['priority']] ?? $task['priority']) ?></span></td>
                    <td><span class="task-status task-status-<?= kd_e($task['status']) ?>"><?= kd_e($statusLabels[$task['status']] ?? $task['status']) ?></span></td>
                    <td class="table-actions"><a href="index.php?page=tasks&action=view&id=<?= (int)$task['id'] ?>" aria-label="Görevi görüntüle">↗</a><?php if (kd_can('tasks.manage')): ?><a href="index.php?page=tasks&action=edit&id=<?= (int)$task['id'] ?>" aria-label="Görevi düzenle">✎</a><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$tasks): ?><tr><td colspan="6"><div class="empty-state"><strong>Görev bulunamadı</strong><p>Filtreleri değiştirebilir veya yeni bir görev oluşturabilirsiniz.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?><div class="pagination"><span><?= $page ?> / <?= $pages ?> sayfa</span><div><?php if ($page > 1): ?><a href="<?= kd_e('index.php?page=tasks&q='.rawurlencode($q).'&status='.rawurlencode($status).'&priority='.rawurlencode($priority).'&assigned_user_id='.$assignee.'&p='.($page-1)) ?>">← Önceki</a><?php endif; ?><?php if ($page < $pages): ?><a href="<?= kd_e('index.php?page=tasks&q='.rawurlencode($q).'&status='.rawurlencode($status).'&priority='.rawurlencode($priority).'&assigned_user_id='.$assignee.'&p='.($page+1)) ?>">Sonraki →</a><?php endif; ?></div></div><?php endif; ?>
</section>
