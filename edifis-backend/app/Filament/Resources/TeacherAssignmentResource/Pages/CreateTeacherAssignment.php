<?php

namespace App\Filament\Resources\TeacherAssignmentResource\Pages;

use App\Filament\Resources\TeacherAssignmentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateTeacherAssignment extends CreateRecord
{
    protected static string $resource = TeacherAssignmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        DB::table('teacher_assignments')->insert([
            'teacher_id' => $data['teacher_id'],
            'subject_id' => $data['subject_id'],
            'stream_id' => $data['stream_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $data;
    }
}
