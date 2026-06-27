<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Exports\StudentAdmissionTemplate;
use App\Filament\Resources\StudentResource;
use App\Imports\StudentAdmissionImport;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('downloadTemplate')
                ->label('Admission template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => Excel::download(new StudentAdmissionTemplate, 'student-admission-template.xlsx')),

            Actions\Action::make('importStudents')
                ->label('Import students')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->label('Admission sheet (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $import = new StudentAdmissionImport;
                    Excel::import($import, $data['file']);
                    $r = $import->getResult();

                    $body = "{$r['created']} enrolled, {$r['skipped']} skipped.";
                    if (! empty($r['errors'])) {
                        $body .= ' ' . implode(' ', array_slice($r['errors'], 0, 5));
                    }

                    Notification::make()
                        ->title('Admission import complete')
                        ->body($body)
                        ->color(empty($r['errors']) ? 'success' : 'warning')
                        ->success()
                        ->persistent()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }
}
