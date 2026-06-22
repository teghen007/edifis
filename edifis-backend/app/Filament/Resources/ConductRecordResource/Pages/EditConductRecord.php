<?php

namespace App\Filament\Resources\ConductRecordResource\Pages;

use App\Filament\Resources\ConductRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConductRecord extends EditRecord
{
    protected static string $resource = ConductRecordResource::class;
    protected function getHeaderActions(): array { return [Actions\DeleteAction::make()]; }
}
