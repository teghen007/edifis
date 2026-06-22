<?php

namespace App\Filament\Resources\ConductRecordResource\Pages;

use App\Filament\Resources\ConductRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConductRecords extends ListRecords
{
    protected static string $resource = ConductRecordResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
