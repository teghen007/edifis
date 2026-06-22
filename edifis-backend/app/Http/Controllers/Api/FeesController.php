<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Fees\Models\FeeStructure;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Students\Models\Student;
use App\Exports\FeesSheetExport;
use App\Imports\FeesSheetImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FeesController
{
    /**
     * Bill every active student in a class for the fee structures that apply to them
     * (respecting day/boarding). Idempotent — re-running won't double-charge.
     */
    public function bill(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'uuid'],
        ]);

        $fees = FeeStructure::where('class_id', $validated['class_id'])->get();
        if ($fees->isEmpty()) {
            return response()->json(['message' => 'No fee structure defined for this class.'], 422);
        }

        $students = Student::where('active', true)
            ->where('current_class_id', $validated['class_id'])
            ->get();

        $billedStudents = 0;
        $chargesPosted = 0;
        $total = 0;

        foreach ($students as $student) {
            $status = $student->boarding_status ?? 'day';
            $applicable = $fees->filter(fn ($f) => $f->applies_to === 'all' || $f->applies_to === $status);
            if ($applicable->isEmpty()) {
                continue;
            }

            $postedForStudent = false;
            foreach ($applicable as $fee) {
                // Deterministic id so re-billing the same fee for the same student is a no-op.
                $sourceEventId = (string) Uuid::uuid5(Uuid::NAMESPACE_DNS, "fee:{$fee->id}:{$student->id}");
                if (LedgerEntry::where('source_event_id', $sourceEventId)->exists()) {
                    continue;
                }

                LedgerEntry::create([
                    'id' => (string) Uuid::uuid7(),
                    'student_id' => $student->id,
                    'source_event_id' => $sourceEventId,
                    'amount' => (int) $fee->amount,
                    'description' => $fee->name,
                    'posted_at' => now(),
                ]);

                $chargesPosted++;
                $total += (int) $fee->amount;
                $postedForStudent = true;
            }

            if ($postedForStudent) {
                $billedStudents++;
            }
        }

        return response()->json([
            'billed_students' => $billedStudents,
            'charges_posted' => $chargesPosted,
            'total' => $total,
        ]);
    }

    public function template(Request $request): BinaryFileResponse
    {
        $classId = $request->query('class_id');
        return Excel::download(new FeesSheetExport($classId), 'fees-sheet.xlsx');
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx']]);

        $import = new FeesSheetImport;
        Excel::import($import, $request->file('file'));

        return response()->json($import->getResult());
    }

    public function balance(string $studentId, BalanceQuery $query): JsonResponse
    {
        return response()->json($query->get($studentId));
    }

    public function balances(BalanceQuery $query): JsonResponse
    {
        $students = Student::where('active', true)
            ->with('schoolClass')
            ->get();

        $result = $students->map(function ($student) use ($query) {
            $balanceData = $query->get($student->id);
            return [
                'student_id' => $student->id,
                'name' => trim($student->given_name . ' ' . $student->family_name),
                'class_name' => $student->schoolClass?->name ?? '',
                'balance' => $balanceData['balance'],
                'currency' => $balanceData['currency'],
            ];
        })->sortByDesc('balance')->values();

        return response()->json($result);
    }
}
