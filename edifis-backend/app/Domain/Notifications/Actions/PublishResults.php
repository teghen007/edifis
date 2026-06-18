<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Notifications\ResultsPublished;
use App\Domain\Students\Models\Student;
use App\Models\User;
use App\Support\Idempotency;

class PublishResults
{
    /** Dispatch notifications to guardians of a student. Idempotent — replay is a no-op. */
    public function handle(Student $student, string $sequence, float $average): void
    {
        $eventKey = "publish-results:{$student->id}:{$sequence}";

        Idempotency::applyOnce($eventKey, $eventKey, function () use ($student, $sequence, $average) {
            $guardians = User::role('parent')->get();

            foreach ($guardians as $guardian) {
                $guardian->notify(new ResultsPublished(
                    studentId: $student->id,
                    studentName: "{$student->given_name} {$student->family_name}",
                    sequence: $sequence,
                    average: $average,
                ));
            }

            return true;
        });
    }
}
