<?php

namespace App\Filament\Resources\StudentResource\Pages;

use App\Domain\Students\Actions\EnrolStudent;
use App\Filament\Resources\StudentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $result = app(EnrolStudent::class)->handle([
            'student' => [
                'given_name' => $data['given_name'],
                'family_name' => $data['family_name'],
                'other_names' => $data['other_names'] ?? null,
                'sex' => $data['sex'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'current_class_id' => $data['current_class_id'],
                'photo_ref' => $data['photo_ref'] ?? null,
            ],
            'consent' => $data['consent'] ?? [
                'consenter_name' => 'Unknown',
                'relationship' => 'guardian',
                'scope' => [],
            ],
        ]);

        return \App\Domain\Students\Models\Student::find($result['id']);
    }
}
