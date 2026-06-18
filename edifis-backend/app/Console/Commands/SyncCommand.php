<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Sync\Actions\ApplyEnvelope;
use App\Domain\Sync\Services\CursorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SyncCommand extends Command
{
    protected $signature = 'edifis:sync
                            {--push-only : Only push local changes to the cloud}
                            {--pull-only : Only pull changes from the cloud}
                            {--once : Run once and exit (do not schedule)}';

    protected $description = 'Push/pull deltas, conflicts, and revocations between this node and the cloud. Idempotent.';

    public function handle(ApplyEnvelope $apply, CursorService $cursors): int
    {
        $cloudUrl = config('sync.cloud_base_url');
        $nodeId = config('edifis.node_id');

        if (empty($cloudUrl)) {
            $this->error('SYNC_CLOUD_BASE_URL is not set. Cannot sync.');
            return self::FAILURE;
        }

        $pushOnly = $this->option('push-only');
        $pullOnly = $this->option('pull-only');

        try {
            if (! $pullOnly) {
                $this->pushChanges($apply, $cursors, $cloudUrl, $nodeId);
            }

            if (! $pushOnly) {
                $this->pullChanges($apply, $cursors, $cloudUrl, $nodeId);
            }

            $this->info('Sync complete.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Sync failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function pushChanges(ApplyEnvelope $apply, CursorService $cursors, string $cloudUrl, string $nodeId): void
    {
        $pushCursor = $cursors->get($nodeId, '__push__');

        $this->info('Pushing changes since cursor: ' . ($pushCursor ?? 'beginning'));

        $items = $this->collectUnsyncedItems($pushCursor);

        if (empty($items)) {
            $this->info('No local changes to push.');
            return;
        }

        $envelope = [
            'direction' => 'push',
            'node_id' => $nodeId,
            'since_cursor' => $pushCursor,
            'priority' => 'accountability',
            'items' => $items,
        ];

        $this->info('Pushing ' . count($items) . ' items...');

        $response = Http::timeout(30)
            ->acceptJson()
            ->post("{$cloudUrl}/sync", $envelope);

        if ($response->successful()) {
            $body = $response->json();
            $applied = $body['applied'] ?? 0;
            $conflicts = count($body['conflicts'] ?? []);
            $this->info("Push: {$applied} applied, {$conflicts} conflicts surfaced.");

            // Bug fix 3: stamp local synced_time on pushed records so they aren't re-pushed
            $this->markPushed($items);

            $cursors->set($nodeId, '__push__', now()->toIso8601ZuluString());
        } else {
            $this->error("Push failed (HTTP {$response->status()}): " . $response->body());
        }
    }

    private function pullChanges(ApplyEnvelope $apply, CursorService $cursors, string $cloudUrl, string $nodeId): void
    {
        $sinceCursor = $cursors->get($nodeId, '__pull__');

        $this->info('Pulling changes since cursor: ' . ($sinceCursor ?? 'beginning'));

        $response = Http::timeout(30)
            ->acceptJson()
            ->post("{$cloudUrl}/sync", [
                'direction' => 'pull',
                'node_id' => $nodeId,
                'since_cursor' => $sinceCursor,
            ]);

        if ($response->successful()) {
            $body = $response->json();
            $items = count($body['items'] ?? []);
            $conflicts = count($body['conflicts'] ?? []);
            $nextCursor = $body['next_cursor'] ?? null;
            $this->info("Pull: {$items} deltas, {$conflicts} conflicts, next cursor: {$nextCursor}");

            // Bug fix 1: use applyPulled() — preserves the cloud's synced_time
            if ($items > 0) {
                $apply->applyPulled([
                    'direction' => 'push',
                    'node_id' => $nodeId,
                    'since_cursor' => $sinceCursor,
                    'items' => $body['items'],
                ]);
            }

            // Bug fix 2: persist next_cursor so pull window advances
            if ($nextCursor) {
                $cursors->set($nodeId, '__pull__', $nextCursor);
            }

            $this->pullRevocations($cloudUrl);

            if ($conflicts > 0) {
                foreach ($body['conflicts'] as $conflict) {
                    if (! empty($conflict['delivery_id'])) {
                        $apply->ackConflict($conflict['delivery_id']);
                    }
                }
            }
        } else {
            if ($response->status() === 429) {
                $retryAfter = (int) ($response->json()['retry_after_seconds'] ?? config('sync.backoff_base_seconds', 2));
                $this->warn("Rate limited — backing off for {$retryAfter}s.");
                return;
            }

            $this->error("Pull failed (HTTP {$response->status()}): " . $response->body());
        }
    }

    private function pullRevocations(string $cloudUrl): void
    {
        $lastRevocation = DB::table('revocations')->max('revoked_at');
        $url = "{$cloudUrl}/auth/revocations";
        if ($lastRevocation) {
            $url .= '?since=' . $lastRevocation;
        }

        $response = Http::timeout(10)->acceptJson()->get($url);

        if ($response->successful()) {
            $body = $response->json();
            $revokedUsers = count($body['revoked_user_ids'] ?? []);
            if ($revokedUsers > 0) {
                $this->info("Revocations pulled: {$revokedUsers} users.");
            }
        }
    }

    /**
     * Mark pushed records so they aren't collected again.
     * Local nodes never stamp their own synced_time — the cloud stamps only its copy.
     * After a successful push, we stamp synced_time locally so whereNull('synced_time')
     * excludes them on the next push cycle. Idempotent.
     */
    private function markPushed(array $items): void
    {
        $now = now()->toIso8601ZuluString();

        foreach ($items as $item) {
            $modelClass = match ($item['type']) {
                'issue_event' => \App\Domain\Issuance\Models\IssueEvent::class,
                'attendance_event' => \App\Domain\Attendance\Models\AttendanceEvent::class,
                'ledger_entry' => \App\Domain\Ledger\Models\LedgerEntry::class,
                'audit_entry' => \App\Domain\Audit\Models\AuditEntry::class,
                default => null,
            };

            if ($modelClass) {
                $modelClass::where('id', $item['id'])->update(['synced_time' => $now]);
            }
        }
    }

    /**
     * Collect locally-created records that have never been pushed.
     */
    private function collectUnsyncedItems(?string $pushCursor): array
    {
        $items = [];
        $types = ApplyEnvelope::ACCOUNTABILITY_TYPES;

        foreach ($types as $type) {
            $modelClass = match ($type) {
                'issue_event' => \App\Domain\Issuance\Models\IssueEvent::class,
                'attendance_event' => \App\Domain\Attendance\Models\AttendanceEvent::class,
                'ledger_entry' => \App\Domain\Ledger\Models\LedgerEntry::class,
                'audit_entry' => \App\Domain\Audit\Models\AuditEntry::class,
                default => null,
            };

            if (! $modelClass) continue;

            $records = $modelClass::query()
                ->whereNull('synced_time')
                ->limit(500)
                ->get();

            foreach ($records as $record) {
                $items[] = [
                    'type' => $type,
                    'id' => $record->id,
                    'revision' => $record->revision ?? $record->id,
                    'payload' => $record->toArray(),
                ];
            }
        }

        return $items;
    }
}
