<?php
declare(strict_types=1);

namespace KodVadAll\Modules\Customers;

use PDO;
use RuntimeException;
use Throwable;

final class CustomerController
{
    public static function handle(PDO $pdo, array $admin): void
    {
        kd_require_permission('customers.view');
        $action = (string)($_GET['action'] ?? 'list');
        $id = max(0, (int)($_GET['id'] ?? 0));
        if ($action === 'crm_dashboard') {
            CrmDashboard::handle($pdo);
            return;
        }
        if ($action === 'crm') {
            CrmWorkspace::handle($pdo, $admin, $id);
            return;
        }
        $errors = [];
        $formInput = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kd_csrf_check();
            $formAction = (string)($_POST['form_action'] ?? '');
            kd_require_permission('customers.manage');

            try {
                if ($formAction === 'create') {
                    $data = self::validatedInput($_POST, $errors);
                    $formInput = $data;
                    if (!$errors) {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'INSERT INTO customers (type,name,email,phone,tax_number,notes,status,created_at)
                             VALUES (?,?,?,?,?,?,?,NOW())'
                        );
                        $stmt->execute([$data['type'],$data['name'],$data['email'],$data['phone'],$data['tax_number'],$data['notes'],'active']);
                        $customerId = (int)$pdo->lastInsertId();
                        $code = 'MUS-' . date('Y') . '-' . str_pad((string)$customerId, 5, '0', STR_PAD_LEFT);
                        $pdo->prepare('UPDATE customers SET customer_code = ? WHERE id = ?')->execute([$code,$customerId]);
                        self::addActivity($pdo,$customerId,(int)$admin['id'],'system','Müşteri kaydı oluşturuldu','Müşteri kartı sisteme eklendi.');
                        $pdo->commit();
                        kd_audit('customer.created','customer',$customerId);
                        self::redirect('list');
                    }
                    $action = 'create';
                } elseif ($formAction === 'update') {
                    $customerId = max(0,(int)($_POST['customer_id'] ?? 0));
                    $data = self::validatedInput($_POST, $errors);
                    $formInput = $data;
                    $status = in_array($_POST['status'] ?? '', ['active','inactive'], true) ? (string)$_POST['status'] : 'active';
                    if (!$errors && $customerId > 0) {
                        if (!self::find($pdo, $customerId)) {
                            self::notFound();
                        }
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'UPDATE customers SET type=?,name=?,email=?,phone=?,tax_number=?,notes=?,status=?,updated_at=NOW() WHERE id=?'
                        );
                        $stmt->execute([$data['type'],$data['name'],$data['email'],$data['phone'],$data['tax_number'],$data['notes'],$status,$customerId]);
                        self::addActivity($pdo,$customerId,(int)$admin['id'],'system','Müşteri bilgileri güncellendi','Müşteri kartı güncellendi.');
                        $pdo->commit();
                        kd_audit('customer.updated','customer',$customerId);
                        self::redirect('view',$customerId);
                    }
                    $id = $customerId;
                    $formInput['status'] = $status;
                    $action = 'edit';
                } elseif ($formAction === 'activity') {
                    $customerId = max(0,(int)($_POST['customer_id'] ?? 0));
                    $subject = trim((string)($_POST['subject'] ?? ''));
                    $body = trim((string)($_POST['body'] ?? ''));
                    if ($subject === '' || mb_strlen($subject) > 190) {
                        $errors[] = 'Etkinlik başlığı zorunludur ve 190 karakteri geçemez.';
                    }
                    if ($customerId < 1) {
                        throw new RuntimeException('Müşteri kaydı bulunamadı.');
                    }
                    if (!$errors) {
                        if (!self::find($pdo, $customerId)) {
                            self::notFound();
                        }
                        self::addActivity($pdo,$customerId,(int)$admin['id'],'note',$subject,$body);
                        kd_audit('customer.activity_added','customer',$customerId);
                        self::redirect('view',$customerId);
                    }
                    $id = $customerId;
                    $action = 'view';
                } elseif ($formAction === 'archive') {
                    $customerId = max(0,(int)($_POST['customer_id'] ?? 0));
                    if (!self::find($pdo, $customerId)) {
                        self::notFound();
                    }
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE customers SET status='inactive',updated_at=NOW() WHERE id=?")->execute([$customerId]);
                    self::addActivity($pdo,$customerId,(int)$admin['id'],'system','Müşteri pasife alındı','Müşteri kaydı silinmeden arşivlendi.');
                    $pdo->commit();
                    kd_audit('customer.archived','customer',$customerId);
                    self::redirect('list');
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('KodVadAll customer module: ' . $e->getMessage());
                $errors[] = 'İşlem tamamlanamadı. Bilgileri kontrol edip tekrar deneyin.';
            }
        }

        if ($action === 'create' || $action === 'edit') {
            kd_require_permission('customers.manage');
            $customer = [
                'id'=>0,'type'=>'individual','name'=>'','email'=>'','phone'=>'','tax_number'=>'','notes'=>'','status'=>'active'
            ];
            if ($action === 'edit') {
                $customer = self::find($pdo,$id);
                if (!$customer) {
                    self::notFound();
                }
            }
            if ($formInput) {
                $customer = array_merge($customer, $formInput);
            }
            $pageTitle = $action === 'create' ? 'Yeni müşteri' : 'Müşteriyi düzenle';
            ob_start();
            require KD_ROOT . '/app/Views/customers/form.php';
            $content = ob_get_clean();
            require KD_ROOT . '/app/Views/layouts/admin.php';
            return;
        }

        if ($action === 'view') {
            $customer = self::find($pdo,$id);
            if (!$customer) {
                self::notFound();
            }
            $stmt = $pdo->prepare(
                'SELECT a.*,u.name AS user_name FROM customer_activities a
                 LEFT JOIN users u ON u.id=a.user_id WHERE a.customer_id=? ORDER BY a.id DESC LIMIT 100'
            );
            $stmt->execute([$id]);
            $activities = $stmt->fetchAll();
            $ledger = \KodVadAll\Core\PartyLedgerService::statement($pdo,'customer',$id);
            $pageTitle = (string)$customer['name'];
            ob_start();
            require KD_ROOT . '/app/Views/customers/detail.php';
            $content = ob_get_clean();
            require KD_ROOT . '/app/Views/layouts/admin.php';
            return;
        }

        if ($action !== 'list') {
            self::notFound();
        }

        $q = trim((string)($_GET['q'] ?? ''));
        $status = in_array($_GET['status'] ?? '', ['active','inactive'], true) ? (string)$_GET['status'] : '';
        $type = in_array($_GET['type'] ?? '', ['individual','company'], true) ? (string)$_GET['type'] : '';
        $page = max(1,(int)($_GET['p'] ?? 1));
        $limit = 20;
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR customer_code LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params,$like,$like,$like,$like);
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($type !== '') {
            $where[] = 'type = ?';
            $params[] = $type;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ',$where) : '';
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM customers' . $whereSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        $pages = max(1,(int)ceil($total/$limit));
        $page = min($page,$pages);
        $offset = ($page-1)*$limit;
        $stmt = $pdo->prepare('SELECT * FROM customers' . $whereSql . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        $pageTitle = 'Müşteriler';
        ob_start();
        require KD_ROOT . '/app/Views/customers/list.php';
        $content = ob_get_clean();
        require KD_ROOT . '/app/Views/layouts/admin.php';
    }

    private static function validatedInput(array $input, array &$errors): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $type = in_array($input['type'] ?? '', ['individual','company'], true) ? (string)$input['type'] : 'individual';
        if ($name === '' || mb_strlen($name) > 190) {
            $errors[] = 'Müşteri adı zorunludur ve 190 karakteri geçemez.';
        }
        if ($email !== '' && !filter_var($email,FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi girin.';
        }
        return [
            'name'=>$name,
            'email'=>$email !== '' ? $email : null,
            'type'=>$type,
            'phone'=>trim((string)($input['phone'] ?? '')) ?: null,
            'tax_number'=>trim((string)($input['tax_number'] ?? '')) ?: null,
            'notes'=>trim((string)($input['notes'] ?? '')) ?: null
        ];
    }

    private static function find(PDO $pdo, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function addActivity(PDO $pdo,int $customerId,int $userId,string $type,string $subject,string $body): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO customer_activities (customer_id,user_id,activity_type,subject,body,created_at)
             VALUES (?,?,?,?,?,NOW())'
        );
        $stmt->execute([$customerId,$userId,$type,$subject,$body !== '' ? $body : null]);
    }

    private static function redirect(string $action, int $id = 0): never
    {
        $url = 'index.php?page=customers';
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
        exit('Müşteri kaydı bulunamadı.');
    }
}
