<?php

declare(strict_types=1);

namespace App\Domain\Students\Actions;

use App\Domain\Academics\Models\AcademicYear;
use App\Domain\Consent\Actions\CaptureConsent;
use App\Domain\Students\Models\Student;
use App\Domain\Tenancy\Services\ModeGate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\Models\Role;

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

            // A section (stream) is the authoritative placement; derive the class from it.
            $streamId = $studentData['stream_id'] ?? null;
            $classId = $studentData['current_class_id'] ?? null;
            if ($streamId) {
                $stream = DB::table('streams')->where('id', $streamId)->first();
                $classId = $stream?->class_id ?? $classId;
            }

            $student = Student::create([
                'id' => $internalId,
                'master_pea_id' => $masterPeaId,
                'given_name' => $studentData['given_name'],
                'family_name' => $studentData['family_name'],
                'other_names' => $studentData['other_names'] ?? null,
                'sex' => $studentData['sex'] ?? null,
                'date_of_birth' => $studentData['date_of_birth'] ?? null,
                'current_class_id' => $classId,
                'class_id' => $classId,
                'stream_id' => $streamId,
                'boarding_status' => $studentData['boarding_status'] ?? 'day',
                'photo_ref' => $studentData['photo_ref'] ?? null,
                'enrolled_at' => now(),
            ]);

            $this->placeInSection($internalId, $streamId);

            app(CaptureConsent::class)->handle(
                studentId: $internalId,
                consenterName: $data['consent']['consenter_name'],
                relationship: $data['consent']['relationship'],
                consenterContact: $data['consent']['consenter_contact'] ?? null,
                scope: $data['consent']['scope'],
            );

            // Phone-keyed guardian: create/reuse a parent account and link it.
            $phone = $data['guardian']['phone'] ?? $data['consent']['consenter_contact'] ?? null;
            $name = $data['guardian']['name'] ?? $data['consent']['consenter_name'] ?? null;
            $relationship = $data['guardian']['relationship'] ?? $data['consent']['relationship'] ?? 'guardian';
            $guardian = $this->linkGuardian($internalId, $phone, $name, $relationship);

            return [$student, $guardian];
        });

        [$student, $guardian] = $student;

        return [
            'id' => $student->id,
            'master_pea_id' => $student->master_pea_id,
            'given_name' => $student->given_name,
            'family_name' => $student->family_name,
            'current_class_id' => $student->current_class_id,
            'stream_id' => $student->stream_id,
            'guardian_id' => $guardian?->id,
            'guardian_phone' => $guardian?->phone,
            'enrolled_at' => $student->enrolled_at?->toIso8601ZuluString(),
        ];
    }

    /** Year-scoped section enrolment (authoritative student->section link). */
    private function placeInSection(string $studentId, ?string $streamId): void
    {
        if (! $streamId) {
            return;
        }

        $year = AcademicYear::where('is_current', true)->first();
        if (! $year) {
            return;
        }

        $exists = DB::table('student_stream')
            ->where('student_id', $studentId)
            ->where('stream_id', $streamId)
            ->where('academic_year_id', $year->id)
            ->exists();

        if (! $exists) {
            DB::table('student_stream')->insert([
                'student_id' => $studentId,
                'stream_id' => $streamId,
                'academic_year_id' => $year->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Create or reuse a guardian's parent account (keyed by phone, so siblings
     * share one account) and link them to the student.
     */
    private function linkGuardian(string $studentId, ?string $phone, ?string $name, string $relationship): ?User
    {
        $phone = $phone ? trim($phone) : null;
        if (! $phone || ! $name) {
            return null;
        }

        $guardian = User::where('phone', $phone)->first();
        if (! $guardian) {
            $digits = preg_replace('/\D/', '', $phone) ?: Str::random(8);
            $guardian = User::create([
                'id' => (string) Uuid::uuid7(),
                'name' => $name,
                'phone' => $phone,
                'email' => 'guardian+' . $digits . '@parent.local',
                'password' => Hash::make(Str::random(24)),
                'active' => true,
            ]);
        }

        // Parent role under both guards (web for any panel checks, sanctum for the app/API).
        foreach (['web', 'sanctum'] as $guard) {
            $role = Role::where('name', 'parent')->where('guard_name', $guard)->first();
            if ($role && ! DB::table('model_has_roles')
                ->where(['role_id' => $role->id, 'model_id' => $guardian->id])->exists()) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $role->id,
                    'model_type' => $guardian->getMorphClass(),
                    'model_id' => $guardian->id,
                ]);
            }
        }

        if (! DB::table('guardian_student')
            ->where('guardian_id', $guardian->id)->where('student_id', $studentId)->exists()) {
            DB::table('guardian_student')->insert([
                'id' => (string) Uuid::uuid7(),
                'guardian_id' => $guardian->id,
                'student_id' => $studentId,
                'relationship' => $relationship,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $guardian;
    }
}
