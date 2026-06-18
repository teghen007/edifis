<?php

declare(strict_types=1);

namespace App\Domain\Consent\Actions;

use App\Domain\Consent\Models\Consent;

class CaptureConsent
{
    public function handle(
        string $studentId,
        string $consenterName,
        string $relationship,
        ?string $consenterContact,
        array $scope,
    ): Consent {
        $latest = Consent::where('student_id', $studentId)
            ->whereNull('revoked_at')
            ->orderByDesc('version')
            ->first();

        $version = $latest ? $latest->version + 1 : 1;

        if ($latest && $latest->scope === $scope) {
            return $latest;
        }

        return Consent::create([
            'id' => (string) \Ramsey\Uuid\Uuid::uuid7(),
            'student_id' => $studentId,
            'consenter_name' => $consenterName,
            'relationship' => $relationship,
            'consenter_contact' => $consenterContact,
            'consented_at' => now(),
            'scope' => $scope,
            'version' => $version,
        ]);
    }
}
