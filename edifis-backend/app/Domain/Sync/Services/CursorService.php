<?php

declare(strict_types=1);

namespace App\Domain\Sync\Services;

use Illuminate\Support\Facades\DB;

/** Opaque cursor service for delta sync. Cursor = ULID/timestamp per type. */
class CursorService
{
    const TABLE = 'sync_cursors';

    public function get(string $nodeId, string $type): ?string
    {
        $row = DB::table(self::TABLE)
            ->where('node_id', $nodeId)
            ->where('entity_type', $type)
            ->first();

        return $row->cursor ?? null;
    }

    public function set(string $nodeId, string $type, string $cursor): void
    {
        DB::table(self::TABLE)->upsert(
            ['node_id' => $nodeId, 'entity_type' => $type, 'cursor' => $cursor, 'updated_at' => now()],
            ['node_id', 'entity_type'],
            ['cursor', 'updated_at'],
        );
    }

    /** Build a since cursor — the max across all entity types for this node. */
    public function since(string $nodeId): ?string
    {
        $max = DB::table(self::TABLE)
            ->where('node_id', $nodeId)
            ->max('cursor');

        return $max;
    }
}
