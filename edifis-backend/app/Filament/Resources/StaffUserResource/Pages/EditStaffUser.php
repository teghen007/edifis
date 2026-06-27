<?php

namespace App\Filament\Resources\StaffUserResource\Pages;

use App\Filament\Resources\StaffUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaffUser extends EditRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    /** Pre-select the user's current staff role in the form. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = $this->record->roles
            ->whereIn('name', StaffUserResource::STAFF_ROLES)
            ->pluck('name')
            ->first();

        return $data;
    }

    protected function afterSave(): void
    {
        StaffUserResource::syncStaffRole($this->record, $this->data['role'] ?? null);
    }
}
