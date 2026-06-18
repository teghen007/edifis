<?php

declare(strict_types=1);

namespace App\Domain\Issuance\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Issuance\Models\IssueEvent;
use Ramsey\Uuid\Uuid;

class ImportCatalogue
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(array $rows): array
    {
        $imported = [];

        foreach ($rows as $row) {
            $item = CatalogueItem::firstOrCreate(
                ['name' => $row['name']],
                [
                    'id' => (string) Uuid::uuid7(),
                    'description' => $row['description'] ?? null,
                    'cost' => (int) $row['cost'],
                    'category' => $row['category'] ?? 'other',
                    'default_for_forms' => $row['default_for_forms'] ?? null,
                    'isbn' => $row['isbn'] ?? null,
                ]
            );

            $imported[] = $item;

            $this->audit->log(
                actorId: '00000000-0000-0000-0000-000000000001',
                action: 'catalogue.import',
                entityType: 'catalogue_item',
                entityId: $item->id,
                after: $item->toArray(),
            );
        }

        return $imported;
    }
}
