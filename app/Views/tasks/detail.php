<?php
declare(strict_types=1);
$statusLabels = ['open' => 'Açık', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal'];
$priorityLabels = ['low' => 'Düşük', 'normal' => 'Normal', 'high' => 'Yüksek', 'urgent' => 'Acil'];
$overdue = !empty($task['due_at']) && strtotime((string)$task['due_at']) < time() && !in_array($task['status'], ['completed', 'cancelled'], true);
?>
<div class="module-toolbar"><div><p class="module-intro">Görev ayrıntıları ve ilerleme durumu.</p></div><div class="toolbar-actions"><a class="btn secondary action-link" href="index.php?page=tasks">← Liste</a><?php if (kd_can('tasks.manage')): ?><a class="btn primary action-link" href="index.php?page=tasks&action=edit&id=<?= (int)$task['id'] ?>">Düzenle</a><?php endif; ?></div></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ', $errors)) ?></div><?php endif; ?>
<section class="panel task-detail-panel">
    <div class="task-detail-heading"><div><span class="eyebrow">GÖREV #<?= (int)$task['id'] ?></span><h2><?= kd_e($task['title']) ?></h2></div><span class="task-status task-status-<?= kd_e($task['status']) ?>"><?= kd_e($statusLabels[$task['status']] ?? $task['status']) ?></span></div>
    <?php if (!empty($task['description'])): ?><div class="task-description"><?= nl2br(kd_e($task['description'])) ?></div><?php else: ?><p class="muted">Bu görev için açıklama eklenmemiş.</p><?php endif; ?>
    <div class="task-meta-grid">
        <div><small>Sorumlu</small><strong><?= kd_e($task['assignee_name'] ?: 'Atanmadı') ?></strong></div>
        <div><small>Öncelik</small><strong><span class="task-priority priority-<?= kd_e($task['priority']) ?>"><?= kd_e($priorityLabels[$task['priority']] ?? $task['priority']) ?></span></strong></div>
        <div><small>Son tarih</small><strong class="<?= $overdue ? 'task-overdue' : '' ?>"><?= $task['due_at'] ? kd_e(date('d.m.Y H:i', strtotime((string)$task['due_at']))) : 'Belirlenmedi' ?></strong><?php if ($overdue): ?><small class="task-overdue">Süresi geçti</small><?php endif; ?></div>
        <div><small>Oluşturan</small><strong><?= kd_e($task['creator_name'] ?: 'Sistem') ?></strong></div>
        <div><small>Oluşturulma</small><strong><?= kd_e(date('d.m.Y H:i', strtotime((string)$task['created_at']))) ?></strong></div>
        <div><small>Son güncelleme</small><strong><?= $task['updated_at'] ? kd_e(date('d.m.Y H:i', strtotime((string)$task['updated_at']))) : '—' ?></strong></div>
    </div>
    <?php if (kd_can('tasks.manage') && !in_array($task['status'], ['completed', 'cancelled'], true)): ?>
        <form class="task-status-form" method="post" action="index.php?page=tasks&action=view&id=<?= (int)$task['id'] ?>">
            <?= kd_csrf_field() ?><input type="hidden" name="form_action" value="status"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
            <label>Görev durumunu güncelle<select name="status"><option value="open" <?= $task['status'] === 'open' ? 'selected' : '' ?>>Açık</option><option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>Devam ediyor</option><option value="completed">Tamamlandı</option><option value="cancelled">İptal</option></select></label>
            <button class="btn primary" type="submit">Durumu kaydet</button>
        </form>
    <?php endif; ?>
</section>
