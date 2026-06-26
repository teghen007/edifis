<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\EnrollmentSheetExport;
use App\Imports\EnrollmentSheetImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnrollmentController
{
    public function template(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $streamId = $request->query('stream_id');

        abort_unless($streamId, 400, 'Missing stream_id');
        $this->authorizeStream($user, $streamId);

        return Excel::download(new EnrollmentSheetExport($streamId), 'subject-enrollment.xlsx');
    }

    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx']]);

        $import = new EnrollmentSheetImport;
        $rows = Excel::toCollection($import, $request->file('file'))->first();

        // Find the stream id by content (label "meta_stream_id" + the next cell).
        $streamId = '';
        foreach ($rows as $row) {
            foreach ($row as $col => $val) {
                if (trim((string) $val) === 'meta_stream_id') {
                    $streamId = trim((string) ($row[(int) $col + 1] ?? ''));
                    break 2;
                }
            }
        }
        abort_if($streamId === '', 422, 'Could not read the stream from this file. Please download a fresh sheet.');
        $this->authorizeStream($user, $streamId);

        $import->collection($rows);

        return response()->json($import->getResult());
    }

    private function authorizeStream($user, string $streamId): void
    {
        $unscoped = $user->hasAnyRoleName(['principal', 'vice_principal', 'school_admin']);
        abort_unless($unscoped || $user->mastersStream($streamId), 403,
            'You can only manage enrollment for the class you master.');
    }
}
