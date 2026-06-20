<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Students\Models\Student;
use Illuminate\Http\JsonResponse;

class FeesController
{
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
