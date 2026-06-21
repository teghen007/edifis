<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Ledger\Queries\BalanceQuery;
use App\Domain\Students\Models\Student;
use App\Exports\FeesSheetExport;
use App\Imports\FeesSheetImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FeesController
{
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
