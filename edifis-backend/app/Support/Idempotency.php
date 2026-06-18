<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Atomic, claim-first idempotency. A {entity_id, entity_revision} pair is applied at most once.
 * The composite PK on idempotency_log is the concurrency guard via insertOrIgnore.
 * On replay, returns ['status' => 'replay'] without calling fn.
 *
 * Pattern (T-3.2.0): claim-first inside one DB::transaction.
 *   insertOrIgnore the PK claim; only run fn if the claim inserted a row.
 *   If fn throws, the whole transaction rolls back — no claim, no partial events.
 */
class Idempotency
{
    public static function applyOnce(string $id, string $revision, callable $fn): mixed
    {
        return DB::transaction(function () use ($id, $revision, $fn) {
            $table = config('edifis.idempotency_table', 'idempotency_log');

            $claimed = DB::table($table)->insertOrIgnore([
                'entity_id' => $id,
                'entity_revision' => $revision,
                'applied_at' => now(),
            ]);

            if (! $claimed) {
                return ['status' => 'replay', 'id' => $id, 'revision' => $revision];
            }

            return $fn();
        });
    }

    public static function wasApplied(string $id, string $revision): bool
    {
        $table = config('edifis.idempotency_table', 'idempotency_log');

        return DB::table($table)
            ->where('entity_id', $id)
            ->where('entity_revision', $revision)
            ->exists();
    }
}
