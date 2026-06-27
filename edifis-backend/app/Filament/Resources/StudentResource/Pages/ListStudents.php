<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Domain\Students\Support\BulkPhotoMatcher;
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

            Actions\Action::make('bulkPhotos')
                ->label('Bulk photos')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->modalDescription('Upload a .zip of images named by PEA ID, student ID, or "Family Given" name. Each matched photo replaces that student\'s current one.')
                ->form([
                    FileUpload::make('zip')
                        ->label('Photos (.zip)')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $r = app(BulkPhotoMatcher::class)->fromZip($data['zip']->getRealPath());

                    $body = "{$r['matched']} photos matched.";
                    if (! empty($r['ambiguous'])) {
                        $body .= ' Ambiguous (skipped): ' . implode(', ', array_slice($r['ambiguous'], 0, 8)) . '.';
                    }
                    if (! empty($r['unmatched'])) {
                        $body .= ' No match: ' . implode(', ', array_slice($r['unmatched'], 0, 8)) . '.';
                    }

                    $clean = empty($r['unmatched']) && empty($r['ambiguous']);
                    Notification::make()
                        ->title('Bulk photos complete')
                        ->body($body)
                        ->color($clean ? 'success' : 'warning')
                        ->success()
                        ->persistent()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }
}
