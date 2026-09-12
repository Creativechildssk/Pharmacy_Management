<?php

declare(strict_types=1);

namespace Pharmacy\Audit;

use PDO;

final class AuditLogger
{
    public function __construct(private PDO $pdo) {}

    public function log(?int $userId, string $action, string $module, string $recordType, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null, ?string $ip = null): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO audit_logs(user_id,action,module,record_type,record_id,old_values,new_values,ip_address) VALUES(:user_id,:action,:module,:record_type,:record_id,:old_values,:new_values,:ip)');
        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'module' => $module,
            'record_type' => $recordType,
            'record_id' => $recordId,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'new_values' => $newValues === null ? null : json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => $ip,
        ]);
    }
}
