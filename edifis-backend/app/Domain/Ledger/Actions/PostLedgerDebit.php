<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Actions;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Notifications\Channels\FcmChannel;
use App\Domain\Notifications\Notifications\FeePosted;
use App\Domain\Students\Models\Student;
use App\Models\User;
use App\Support\Idempotency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

class PostLedgerDebit
{
    public function __construct(private readonly FcmChannel $fcm) {}

    /** Post a positive debit (charge) to the ledger. */
    public function debit(string $studentId, int $amount, string $sourceEventId): LedgerEntry
    {
        $entry = LedgerEntry::create([
            'id' => (string) Uuid::uuid7(),
            'student_id' => $studentId,
            'source_event_id' => $sourceEventId,
            'amount' => $amount,
            'posted_at' => now(),
        ]);

        $this->notifyParents($studentId, $amount);

        return $entry;
    }

    /** Post a negative credit (return/payment) to the ledger. */
    public function credit(string $studentId, int $amount, string $sourceEventId): LedgerEntry
    {
        $entry = LedgerEntry::create([
            'id' => (string) Uuid::uuid7(),
            'student_id' => $studentId,
            'source_event_id' => $sourceEventId,
            'amount' => -$amount,
            'posted_at' => now(),
        ]);

        return $entry;
    }

    private function notifyParents(string $studentId, int $amount): void
    {
        try {
            $student = Student::find($studentId);
            if (!$student) {
                return;
            }

            $studentName = trim($student->given_name . ' ' . $student->family_name);

            $parentIds = DB::table('guardian_student')
                ->where('student_id', $studentId)
                ->pluck('guardian_id');

            foreach ($parentIds as $parentId) {
                $parent = User::find($parentId);
                if (!$parent) {
                    continue;
                }
                $this->fcm->send($parent, new FeePosted($studentId, $studentName, $amount));
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send fee notification', [
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
