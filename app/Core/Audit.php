<?php
declare(strict_types=1);

namespace KodVadAll\\Core;

use PDO;
use Throwable;

final class Audit
{
    public static function record(
        PDO $pdo,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $userId = null
    ): void {
        try {
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs (user_id,action,entity_type,entity_id,ip_address,user_agent,created_at)
                 VALUES (?,?,?,?,?,?,NOW())'
            );
            $stmt->execute([
                ($userId !== null && $userId > 0) ? $userId : (((int)($_SESSION['user']['id'] ?? 0)) ?: null),
                substr($action, 0, 100),
                $entityType !== null ? substr($entityType, 0, 80) : null,
                $entityId,
                $ip !== '' ? $ip : null,
                $agent !== '' ? $agent : null
            ]);
        } catch (Throwable $e) {
            error_log('KodVadAll audit failure: ' . $e->getMessage());
        }
    }
}
