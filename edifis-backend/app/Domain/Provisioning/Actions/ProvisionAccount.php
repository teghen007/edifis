<?php

declare(strict_types=1);

namespace App\Domain\Provisioning\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Bootstraps accounts via claim codes for staff, students, and guardians.
 * White paper §14.3 — credential bootstrapping.
 */
class ProvisionAccount
{
    /** Generate a claim code for a new staff or parent account. */
    public function generateClaimCode(): string
    {
        return Str::upper(Str::random(8));
    }

    /** Provision a staff account from a claim code. */
    public function staff(string $name, string $email, string $role, ?string $password = null): User
    {
        $user = User::create([
            'id' => (string) Uuid::uuid7(),
            'name' => $name,
            'email' => $email,
            'password' => $password ?? Hash::make(Str::random(16)),
            'active' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /** Provision a parent/guardian account linked to a student enrolment. */
    public function guardian(
        string $name,
        string $contact,
        string $studentId,
    ): User {
        $email = $contact . '@edifis.parent';

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'id' => (string) Uuid::uuid7(),
                'name' => $name,
                'password' => Hash::make(Str::random(16)),
                'active' => true,
            ]
        );

        $user->assignRole('parent');

        return $user;
    }
}
