<?php

declare(strict_types=1);

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditEntry;

class AuditLogger
{
    public function log(
        string $actorId,
        string $action,
        string $entityType,
        string $entityId,
        ?array $before = null,
        ?array $after = null,
        ?string $deviceId = null,
        ?string $actorRole = null,
    ): AuditEntry {
        return AuditEntry::create([
            'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $this->sanitizeForJsonb($before),
            'after' => $this->sanitizeForJsonb($after),
            'device_id' => $deviceId,
            'occurred_at' => now(),
        ]);
    }

    /** Convert Carbon instances to ISO8601 strings for PostgreSQL jsonb compatibility. */
    private function sanitizeForJsonb(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return array_map(function ($value) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d\TH:i:s.u\Z');
            }
            if (is_array($value)) {
                return $this->sanitizeForJsonb($value);
            }
            return $value;
        }, $data);
    }
}
