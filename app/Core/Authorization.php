<?php
declare(strict_types=1);

namespace KodVadAll\Core;

use PDO;

final class Authorization
{
    public static function allows(PDO $pdo, int $userId, string $permission): bool
    {
        if ($userId < 1 || $permission === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             INNER JOIN roles r ON r.id = ur.role_id
             INNER JOIN users u ON u.id = ur.user_id
             WHERE ur.user_id = ? AND p.permission_key = ? AND u.status = ? AND r.slug = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $permission, 'active', 'owner']);
        if ($stmt->fetchColumn()) {
            return true;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM user_roles ur
             INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             INNER JOIN users u ON u.id = ur.user_id
             WHERE ur.user_id = ? AND p.permission_key = ? AND u.status = ?
             LIMIT 1'
        );
        $stmt->execute([$userId, $permission, 'active']);
        return (bool)$stmt->fetchColumn();
    }

    public static function require(PDO $pdo, int $userId, string $permission): void
    {
        if (!self::allows($pdo, $userId, $permission)) {
            http_response_code(403);
            exit('Bu işlem için yetkiniz bulunmuyor.');
        }
    }
}
