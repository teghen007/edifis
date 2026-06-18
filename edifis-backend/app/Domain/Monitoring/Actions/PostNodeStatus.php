<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\Actions;

use Illuminate\Support\Facades\DB;

class PostNodeStatus
{
    /** Receive node + UPS telemetry and persist for the monitoring dashboard. White paper §2.1, §11. */
    public function handle(array $status): array
    {
        $required = [
            'node_id' => $status['node_id'],
            'reported_at' => $status['reported_at'] ?? now()->toIso8601ZuluString(),
            'disk_ok' => $status['disk_ok'] ?? true,
            'ups_on_battery' => $status['ups_on_battery'] ?? false,
            'last_sync_at' => $status['last_sync_at'] ?? null,
            'pending_outbox' => $status['pending_outbox'] ?? 0,
        ];

        DB::table('node_statuses')->insert($required);

        return $required;
    }
}
