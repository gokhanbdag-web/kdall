<?php
declare(strict_types=1);

namespace KodVadAll\Modules\Users;

use PDO;
use RuntimeException;
use Throwable;

final class UserController
{
    public static function handle(PDO $pdo, array $admin): void
    {
        kd_require_permission('users.view');
        $action = (string)($_GET['action'] ?? 'list');
        $id = max(0, (int)($_GET['id'] ?? 0));
        $errors = [];
        $formInput = [];
        $canAssignRole = kd_can('roles.manage');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kd_csrf_check();
            kd_require_permission('users.manage');
            $formAction = (string)($_POST['form_action'] ?? '');

            try {
                if ($formAction === 'create') {
                    $data = self::validatedInput($pdo, $_POST, $errors, $canAssignRole, null);
                    $formInput = $data;
                    $password = (string)($_POST['password'] ?? '');
                    if (strlen($password) < 12) {
                        $errors[] = 'Yeni kullanıcı parolası en az 12 karakter olmalıdır.';
                    }
                    if (!$errors) {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'INSERT INTO users (name,email,password_hash,role,status,created_at)
                             VALUES (?,?,?,?,?,NOW())'
                        );
                        $stmt->execute([
                            $data['name'], $data['email'], password_hash($password, PASSWORD_DEFAULT),
                            $data['role'], $data['status']
                        ]);
                        $userId = (int)$pdo->lastInsertId();
                        self::syncRole($pdo, $userId, $data['role']);
                        $pdo->commit();
                        kd_audit('user.created', 'user', $userId);
                        self::redirect('view', $userId);
                    }
                    $action = 'create';
                } elseif ($formAction === 'update') {
                    $userId = max(0, (int)($_POST['user_id'] ?? 0));
                    $existing = self::find($pdo, $userId);
                    if (!$existing) {
                        throw new RuntimeException('Kullanıcı kaydı bulunamadı.');
                    }
                    $data = self::validatedInput($pdo, $_POST, $errors, $canAssignRole, $existing);
                    $formInput = $data;
                    $password = (string)($_POST['password'] ?? '');
                    if ($password !== '' && strlen($password) < 12) {
                        $errors[] = 'Yeni parola en az 12 karakter olmalıdır.';
                    }
                    if ($userId === (int)$admin['id'] && $data['status'] !== 'active') {
                        $errors[] = 'Kendi kullanıcı hesabınızı devre dışı bırakamazsınız.';
                    }
                    if (!$errors) {
                        $pdo->beginTransaction();
                        if ($password !== '') {
                            $stmt = $pdo->prepare(
                                'UPDATE users SET name=?,email=?,password_hash=?,role=?,status=?,updated_at=NOW() WHERE id=?'
                            );
                            $stmt->execute([
                                $data['name'], $data['email'], password_hash($password, PASSWORD_DEFAULT),
                                $data['role'], $data['status'], $userId
                            ]);
                        } else {
                            $stmt = $pdo->prepare(
                                'UPDATE users SET name=?,email=?,role=?,status=?,updated_at=NOW() WHERE id=?'
                            );
                            $stmt->execute([$data['name'], $data['email'], $data['role'], $data['status'], $userId]);
                        }
                        if ($existing['role'] !== 'owner' && $canAssignRole) {
                            self::syncRole($pdo, $userId, $data['role']);
                        }
                        $pdo->commit();
                        kd_audit('user.updated', 'user', $userId);
                        self::redirect('view', $userId);
                    }
                    $id = $userId;
                    $action = 'edit';
                } elseif ($formAction === 'status') {
                    $userId = max(0, (int)($_POST['user_id'] ?? 0));
                    $status = (string)($_POST['status'] ?? '');
                    $target = self::find($pdo, $userId);
                    if (!$target || !in_array($status, ['active', 'inactive'], true)) {
                        throw new RuntimeException('Kullanıcı veya durum bilgisi geçersiz.');
                    }
                    if ($userId === (int)$admin['id'] || $target['role'] === 'owner') {
                        throw new RuntimeException('Kendi hesabınızın veya sahip hesabının durumu değiştirilemez.');
                    }
                    $pdo->prepare('UPDATE users SET status=?,updated_at=NOW() WHERE id=?')->execute([$status, $userId]);
                    kd_audit('user.status_changed', 'user', $userId);
                    self::redirect('view', $userId);
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('KodVadAll user module: ' . $e->getMessage());
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'İşlem tamamlanamadı. E-posta adresini ve bilgileri kontrol edin.';
                if ($formAction === 'create') {
                    $action = 'create';
                } elseif ($formAction === 'update') {
                    $action = 'edit';
                } elseif ($formAction === 'status') {
                    $action = 'view';
                }
            }
        }

        if ($action === 'create' || $action === 'edit') {
            kd_require_permission('users.manage');
            $user = [
                'id' => 0, 'name' => '', 'email' => '', 'role' => 'staff', 'status' => 'active',
                'last_login_at' => null, 'created_at' => null
            ];
            if ($action === 'edit') {
                $user = self::find($pdo, $id);
                if (!$user) {
                    self::notFound();
                }
            }
            if ($formInput) {
                $user = array_merge($user, $formInput);
            }
            $roles = self::roles($pdo);
            $pageTitle = $action === 'create' ? 'Yeni kullanıcı' : 'Kullanıcıyı düzenle';
            ob_start();
            require KD_ROOT . '/app/Views/users/form.php';
            $content = ob_get_clean();
            require KD_ROOT . '/app/Views/layouts/admin.php';
            return;
        }

        if ($action === 'view') {
            $user = self::find($pdo, $id);
            if (!$user) {
                self::notFound();
            }
            $pageTitle = (string)$user['name'];
            ob_start();
            require KD_ROOT . '/app/Views/users/detail.php';
            $content = ob_get_clean();
            require KD_ROOT . '/app/Views/layouts/admin.php';
            return;
        }

        if ($action !== 'list') {
            self::notFound();
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $status = in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? (string)$_GET['status'] : '';
        $role = trim((string)($_GET['role'] ?? ''));
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== '') {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($role !== '') {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $pdo->prepare('SELECT COUNT(*) FROM users u' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $limit = 20;
        $pages = max(1, (int)ceil($total / $limit));
        $page = min(max(1, (int)($_GET['p'] ?? 1)), $pages);
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            'SELECT u.* FROM users u' . $whereSql .
            ' ORDER BY CASE WHEN u.role="owner" THEN 0 ELSE 1 END,u.name ASC
              LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute($params);
        $users = $stmt->fetchAll();
        $roles = self::roles($pdo);
        $pageTitle = 'Kullanıcılar & Yetkiler';
        ob_start();
        require KD_ROOT . '/app/Views/users/list.php';
        $content = ob_get_clean();
        require KD_ROOT . '/app/Views/layouts/admin.php';
    }

    private static function validatedInput(PDO $pdo, array $input, array &$errors, bool $canAssignRole, ?array $existing): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            $errors[] = 'Ad soyad zorunludur ve 160 karakteri geçemez.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');
            $check->execute([$email, (int)($existing['id'] ?? 0)]);
            if ($check->fetchColumn()) {
                $errors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
            }
        }

        $role = (string)($existing['role'] ?? 'staff');
        if ($canAssignRole && (!$existing || $existing['role'] !== 'owner')) {
            $requestedRole = trim((string)($input['role'] ?? 'staff'));
            $checkRole = $pdo->prepare("SELECT slug FROM roles WHERE slug=? AND slug<>'owner' LIMIT 1");
            $checkRole->execute([$requestedRole]);
            $role = (string)($checkRole->fetchColumn() ?: 'staff');
        }
        $status = in_array($input['status'] ?? 'active', ['active', 'inactive'], true)
            ? (string)($input['status'] ?? 'active') : 'active';
        if ($existing && $existing['role'] === 'owner') {
            $status = 'active';
        }

        return ['name' => $name, 'email' => $email, 'role' => $role, 'status' => $status];
    }

    private static function syncRole(PDO $pdo, int $userId, string $slug): void
    {
        $stmt = $pdo->prepare('SELECT id FROM roles WHERE slug=? LIMIT 1');
        $stmt->execute([$slug]);
        $roleId = (int)$stmt->fetchColumn();
        if ($roleId < 1) {
            throw new RuntimeException('Kullanıcı rolü bulunamadı.');
        }
        $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$userId]);
        $pdo->prepare('INSERT INTO user_roles (user_id,role_id,created_at) VALUES (?,?,NOW())')->execute([$userId, $roleId]);
    }

    private static function find(PDO $pdo, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function roles(PDO $pdo): array
    {
        return $pdo->query("SELECT slug,name,description FROM roles WHERE slug<>'owner' ORDER BY FIELD(slug,'admin','manager','staff'),name")->fetchAll();
    }

    private static function redirect(string $action, int $id = 0): never
    {
        $url = 'index.php?page=users';
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
        exit('Kullanıcı kaydı bulunamadı.');
    }
}
