<?php
declare(strict_types=1);
$isEdit = $action === 'edit';
$statusLabels = ['open' => 'Açık', 'in_progress' => 'Devam ediyor', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal'];
$priorityLabels = ['low' => 'Düşük', 'normal' => 'Normal', 'high' => 'Yüksek', 'urgent' => 'Acil'];
$dueValue = !empty($task['due_at']) ? date('Y-m-d\TH:i', strtotime((string)$task['due_at'])) : '';
?>
<div class="module-toolbar"><div><p class="module-intro">Görev ayrıntılarını, önceliğini ve sorumlusunu belirleyin.</p></div><a class="btn secondary action-link" href="index.php?page=tasks">← Görev listesi</a></div>
<?php if ($errors): ?><div class="alert"><?= kd_e(implode(' ', $errors)) ?></div><?php endif; ?>
<section class="panel customer-form-panel">
    <form method="post" action="index.php?page=tasks&action=<?= $isEdit ? 'edit&id='.(int)$task['id'] : 'create' ?>">
        <?= kd_csrf_field() ?>
        <input type="hidden" name="form_action" value="<?= $isEdit ? 'update' : 'create' ?>">
        <?php if ($isEdit): ?><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><?php endif; ?>
        <div class="form-section-title"><div><h3><?= $isEdit ? 'Görev bilgileri' : 'Yeni görev' ?></h3><p>Görev başlığı zorunludur.</p></div></div>
        <div class="customer-fields">
            <label class="field-wide">Görev başlığı *<input name="title" maxlength="190" required value="<?= kd_e($task['title']) ?>" autofocus></label>
            <label>Görev sorumlusu<select name="assigned_user_id"><option value="0">Henüz atanmadı</option><?php foreach ($users as $user): ?><option value="<?= (int)$user['id'] ?>" <?= (int)($task['assigned_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>><?= kd_e($user['name']) ?> · <?= kd_e($user['email']) ?></option><?php endforeach; ?></select></label>
            <label>Son tarih<input type="datetime-local" name="due_at" value="<?= kd_e($dueValue) ?>"></label>
            <label>Öncelik<select name="priority"><?php foreach ($priorityLabels as $key => $label): ?><option value="<?= kd_e($key) ?>" <?= $task['priority'] === $key ? 'selected' : '' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select></label>
            <label>Durum<select name="status"><?php foreach ($statusLabels as $key => $label): ?><option value="<?= kd_e($key) ?>" <?= $task['status'] === $key ? 'selected' : '' ?>><?= kd_e($label) ?></option><?php endforeach; ?></select></label>
            <label class="field-wide">Açıklama<textarea name="description" rows="5" placeholder="Görevle ilgili ayrıntılar..."><?= kd_e($task['description'] ?? '') ?></textarea></label>
        </div>
        <div class="form-actions"><a class="btn secondary" href="index.php?page=tasks">Vazgeç</a><button class="btn primary" type="submit"><?= $isEdit ? 'Değişiklikleri kaydet' : 'Görevi oluştur' ?></button></div>
    </form>
</section>
