<?php

declare(strict_types=1);

namespace App\Domain\Students\Actions;

use App\Domain\Consent\Actions\CaptureConsent;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Services\ModeGate;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class EnrolStudent
{
    public function handle(array $data): array
    {
        $internalId = (string) Uuid::uuid7();

        $masterPeaId = null;
        if (ModeGate::cloud()) {
            $year = now()->year;
            $count = Student::whereYear('enrolled_at', $year)->count() + 1;
            $masterPeaId = sprintf('PEA-%d-%05d', $year, $count);
        }

        $student = DB::transaction(function () use ($data, $internalId, $masterPeaId) {
            $studentData = $data['student'];

            $student = Student::create([
                'id' => $internalId,
                'master_pea_id' => $masterPeaId,
                'given_name' => $studentData['given_name'],
                'family_name' => $studentData['family_name'],
                'other_names' => $studentData['other_names'] ?? null,
                'sex' => $studentData['sex'] ?? null,
                'date_of_birth' => $studentData['date_of_birth'] ?? null,
                'current_class_id' => $studentData['current_class_id'],
                'photo_ref' => $studentData['photo_ref'] ?? null,
                'enrolled_at' => now(),
            ]);

            app(CaptureConsent::class)->handle(
                studentId: $internalId,
                consenterName: $data['consent']['consenter_name'],
                relationship: $data['consent']['relationship'],
                consenterContact: $data['consent']['consenter_contact'] ?? null,
                scope: $data['consent']['scope'],
            );

            return $student;
        });

        return [
            'id' => $student->id,
            'master_pea_id' => $student->master_pea_id,
            'given_name' => $student->given_name,
            'family_name' => $student->family_name,
            'current_class_id' => $student->current_class_id,
            'enrolled_at' => $student->enrolled_at?->toIso8601ZuluString(),
        ];
    }
}
