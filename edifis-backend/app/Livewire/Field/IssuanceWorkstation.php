<?php

namespace App\Livewire\Field;

use App\Domain\Issuance\Actions\IssueItemsToStudent;
use App\Domain\Issuance\Actions\ReturnItem;
use App\Domain\Issuance\Models\CatalogueItem;
use App\Domain\Issuance\Models\IssueEvent;
use App\Domain\Issuance\Models\Signature;
use Livewire\Component;
use Ramsey\Uuid\Uuid;

class IssuanceWorkstation extends Component
{
    public string $studentId = '';
    public string $signatureData = '';
    public array $selectedItems = [];
    public ?string $batchId = null;
    public ?array $issuedResult = null;
    public ?string $lastError = null;

    public function getCatalogueItemsProperty()
    {
        return CatalogueItem::where('active', true)->get();
    }

    public function getRunningTotalProperty(): int
    {
        if (empty($this->selectedItems)) return 0;

        return CatalogueItem::whereIn('id', $this->selectedItems)
            ->sum('cost');
    }

    public function issue(IssueItemsToStudent $action): void
    {
        if (empty($this->studentId)) {
            $this->lastError = 'Please scan or enter a Student ID.';
            return;
        }

        if (empty($this->selectedItems)) {
            $this->lastError = 'Select at least one catalogue item.';
            return;
        }

        if (empty($this->signatureData)) {
            $this->lastError = 'Please provide a signature before issuing.';
            return;
        }

        $this->batchId = (string) Uuid::uuid7();

        $sigId = (string) Uuid::uuid7();
        $this->storeSignature($sigId);

        $items = array_map(fn ($id) => ['catalogue_item_id' => $id], $this->selectedItems);

        $result = $action->handle(
            batchId: $this->batchId,
            studentId: $this->studentId,
            items: $items,
            signatureRef: 'signatures/' . $sigId . '.png',
            staffId: auth()->id(),
        );

        if (($result['status'] ?? null) === 'replay') {
            $this->lastError = 'This batch was already applied (idempotent replay).';
            return;
        }

        $this->issuedResult = $result;
        $this->selectedItems = [];
        $this->studentId = '';
        $this->signatureData = '';
        $this->lastError = null;
    }

    private function storeSignature(string $sigId): void
    {
        if (empty($this->signatureData)) {
            return;
        }

        $data = $this->signatureData;

        if (str_starts_with($data, 'data:image/')) {
            [$meta, $data] = explode(',', $data, 2);
        }

        $binary = base64_decode($data);

        Signature::create([
            'id' => $sigId,
            'batch_id' => $this->batchId,
            'staff_id' => auth()->id(),
            'image_data' => base64_encode($binary),
            'mime_type' => 'image/png',
            'captured_at' => now(),
        ]);
    }

    public function returnItem(ReturnItem $action, string $eventId, string $reason): void
    {
        $action->handle(issueEventId: $eventId, reason: $reason, staffId: auth()->id());
        $this->issuedResult = null;
    }

    public function render()
    {
        return view('livewire.field.issuance-workstation')
            ->layout('components.layouts.app', ['title' => 'Issuance — EDIFIS Field']);
    }
}
