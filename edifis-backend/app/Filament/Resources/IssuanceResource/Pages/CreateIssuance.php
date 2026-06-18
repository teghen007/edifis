<?php

namespace App\Filament\Resources\IssuanceResource\Pages;

use App\Domain\Issuance\Actions\IssueItemsToStudent;
use App\Filament\Resources\IssuanceResource;
use Filament\Resources\Pages\CreateRecord;
use Ramsey\Uuid\Uuid;

class CreateIssuance extends CreateRecord
{
    protected static string $resource = IssuanceResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $batchId = (string) Uuid::uuid7();

        $items = array_map(fn ($catalogueId) => [
            'catalogue_item_id' => $catalogueId,
        ], $data['items'] ?? []);

        app(IssueItemsToStudent::class)->handle(
            batchId: $batchId,
            studentId: $data['student_id'],
            items: $items,
            signatureRef: $data['signature_ref'] ?? 'sig-' . $batchId,
            staffId: auth()->id(),
        );

        return \App\Domain\Issuance\Models\IssueEvent::where('batch_id', $batchId)->first();
    }
}
