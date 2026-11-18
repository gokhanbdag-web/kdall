<?php
declare(strict_types=1);

namespace KodVadAll\Modules\Tasks;

use DateTimeImmutable;
use PDO;
use Throwable;

final class TaskController
{
    private const STATUSES = ['open', 'in_progress', 'completed', 'cancelled'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public static function handle(PDO $pdo, array $admin): void
    {
        kd_require_permission('tasks.view');
        $action = (string)($_GET['action'] ?? 'list');
        $id = max(0, (int)($_GET['id'] ?? 0));
        $errors = [];
        $formInput = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kd_csrf_check();
            kd_require_permission('tasks.manage');
            $formAction = (string)($_POST['form_action'] ?? '');

            try {
                if ($formAction === 'create' || $formAction === 'update') {
                    $taskId = max(0, (int)($_POST['task_id'] ?? 0));
                    $data = self::validatedInput($pdo, $_POST, $errors);
                    $formInput = $data;

                    if (!$errors && $formAction === 'create') {
                        $stmt = $pdo->prepare(
                            'INSERT INTO tasks (title,description,status,priority,due_at,assigned_user_id,created_by,created_at)
                             VALUES (?,?,?,?,?,?,?,NOW())'
                        );
                        $stmt->execute([
                            $data['title'], $data['description'], $data['status'], $data['priority'],
                            $data['due_at'], $data['assigned_user_id'], (int)$admin['id']
                        ]);
                        $taskId = (int)$pdo->lastInsertId();
                        kd_audit('task.created', 'task', $taskId);
                        self::redirect('view', $taskId);
                    }

                    if (!$errors && $formAction === 'update' && $taskId > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE tasks SET title=?,description=?,status=?,priority=?,due_at=?,assigned_user_id=?,updated_at=NOW()
                             WHERE id=?'
                        );
                        $stmt->execute([
                            $data['title'], $data['description'], $data['status'], $data['priority'],
                            $data['due_at'], $data['assigned_user_id'], $taskId
                        ]);
                        kd_audit('task.updated', 'task', $taskId);
                        self::redirect('view', $taskId);
                    }

                    $id = $taskId;
                    $action = $formAction === 'create' ? 'create' : 'edit';
                } elseif ($formAction === 'status') {
                    $taskId = max(0, (int)($_POST['task_id'] ?? 0));
                    $status = (string)($_POST['status'] ?? '');
                    if (!in_array($status, self::STATUSES, true)) {
                        $errors[] = 'Geçerli bir görev durumu seçin.';
                    }
                    if ($taskId < 1) {
                        $errors[] = 'Görev kaydı bulunamadı.';
                    }
                    if (!$errors) {
                        $stmt = $pdo->prepare('UPDATE tasks SET status=?,updated_at=NOW() WHERE id=?');
                        $stmt->execute([$status, $taskId]);
                        kd_audit('task.status_changed', 'task', $taskId);
                        self::redirect('view', $taskId);
                    }
                    $id = $taskId;
                    $action = 'view';
                }
            } catch (Throwable $e) {
                error_log('KodVadAll task module: ' . $e->getMessage());
                $errors[] = 'İşlem tamamlanamadı. Bilgileri kontrol edip tekrar deneyin.';
                $action = $formAction === 'create' ? 'create' : ($formAction === 'update' ? 'edit' : 'view');
            }
        }

        if ($action === 'create' || $action === 'edit') {
            kd_require_permission('tasks.manage');
            $task = [
                'id' => 0, 'title' => '', 'description' => '', 'status' => 'open',
                'priority' => 'normal', 'due_at' => null, 'assigned_user_id' => null
            ];
            if ($action === 'edit') {
                $task = self::find($pdo, $id);
                if (!$task) {
                    self::notFound();
                }
            }
            if ($formInput) {
                $task = array_merge($task, $formInput);
            }
            $users = self::users($pdo);
            $pageTitle = $action === 'create' ? 'Yeni görev' : 'Görevi düzenle';
            ob_start();
            require KD_ROOT . '/app/Views/tasks/form.php';
            $content = ob_get_clean();
            require KD_ROOT . '/app/Views/layouts/admin.php';
            return;
        }

        if ($action === 'view') {
            $task = self::find($pdo, $id);
            if (!$task) {
                self::notFound();
            }
            $pageTitle = (string)$task['title'];
            ob_start();
            require KD_ROOT . '/app/Views/tasks/detail.php';
            $content = ob_get_clean();
            require KD_ROOT . '/app/Views/layouts/admin.php';
            return;
        }

        if ($action !== 'list') {
            self::notFound();
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $status = in_array($_GET['status'] ?? '', self::STATUSES, true) ? (string)$_GET['status'] : '';
        $priority = in_array($_GET['priority'] ?? '', self::PRIORITIES, true) ? (string)$_GET['priority'] : '';
        $assignee = max(0, (int)($_GET['assigned_user_id'] ?? 0));
        $page = max(1, (int)($_GET['p'] ?? 1));
        $limit = 20;
        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }
        if ($priority !== '') {
            $where[] = 't.priority = ?';
            $params[] = $priority;
        }
        if ($assignee > 0) {
            $where[] = 't.assigned_user_id = ?';
            $params[] = $assignee;
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $pdo->prepare('SELECT COUNT(*) FROM tasks t' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $limit));
        $page = min($page, $pages);
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare(
            'SELECT t.*,u.name AS assignee_name
             FROM tasks t LEFT JOIN users u ON u.id=t.assigned_user_id' . $whereSql .
            ' ORDER BY CASE WHEN t.status IN ("completed","cancelled") THEN 1 ELSE 0 END,
              CASE t.priority WHEN "urgent" THEN 0 WHEN "high" THEN 1 WHEN "normal" THEN 2 ELSE 3 END,
              t.due_at IS NULL, t.due_at ASC, t.id DESC
             LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $tasks = $stmt->fetchAll();
        $users = self::users($pdo);
        $pageTitle = 'Görevler';
        ob_start();
        require KD_ROOT . '/app/Views/tasks/list.php';
        $content = ob_get_clean();
        require KD_ROOT . '/app/Views/layouts/admin.php';
    }

    private static function validatedInput(PDO $pdo, array $input, array &$errors): array
    {
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $status = in_array($input['status'] ?? '', self::STATUSES, true) ? (string)$input['status'] : 'open';
        $priority = in_array($input['priority'] ?? '', self::PRIORITIES, true) ? (string)$input['priority'] : 'normal';
        $assignee = max(0, (int)($input['assigned_user_id'] ?? 0));
        $dueInput = trim((string)($input['due_at'] ?? ''));
        $dueAt = null;

        if ($title === '' || mb_strlen($title) > 190) {
            $errors[] = 'Görev başlığı zorunludur ve 190 karakteri geçemez.';
        }
        if ($assignee > 0) {
            $check = $pdo->prepare("SELECT id FROM users WHERE id=? AND status='active' LIMIT 1");
            $check->execute([$assignee]);
            if (!$check->fetchColumn()) {
                $errors[] = 'Seçilen görev sorumlusu bulunamadı veya aktif değil.';
            }
        }
        if ($dueInput !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $dueInput);
            $dateErrors = DateTimeImmutable::getLastErrors();
            if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
                $errors[] = 'Geçerli bir son tarih girin.';
            } else {
                $dueAt = $date->format('Y-m-d H:i:s');
            }
        }

        return [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'status' => $status,
            'priority' => $priority,
            'due_at' => $dueAt,
            'assigned_user_id' => $assignee > 0 ? $assignee : null
        ];
    }

    private static function find(PDO $pdo, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT t.*,a.name AS assignee_name,c.name AS creator_name
             FROM tasks t
             LEFT JOIN users a ON a.id=t.assigned_user_id
             LEFT JOIN users c ON c.id=t.created_by
             WHERE t.id=? LIMIT 1'
        );
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    private static function users(PDO $pdo): array
    {
        return $pdo->query("SELECT id,name,email FROM users WHERE status='active' ORDER BY name")->fetchAll();
    }

    private static function redirect(string $action, int $id = 0): never
    {
        $url = 'index.php?page=tasks';
        if ($action !== 'list') {
            $url .= '&action=' . rawurlencode($action);
        }
        if ($id > 0) {
            $url .= '&id=' . $id;
        }
        header('Location: ' . $url, true, 303);
        exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);
        exit('Görev kaydı bulunamadı.');
    }
}
