<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Actions;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Domain\Attendance\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordRollCall
{
    /**
     * Record (or update) a daily roll call for a section.
     *
     * @param  array<int, array{student_id:string, status:string, reason?:?string}>  $entries
     */
    public function handle(string $streamId, string $date, string $period, string $teacherId, array $entries): AttendanceSession
    {
        return DB::transaction(function () use ($streamId, $date, $period, $teacherId, $entries) {
            $stream = DB::table('streams')->where('id', $streamId)->first();

            $session = AttendanceSession::firstOrNew([
                'stream_id' => $streamId,
                'attendance_date' => $date,
                'period' => $period,
                'mode' => 'rollcall',
            ]);
            $session->class_id = $stream?->class_id;
            $session->teacher_id = $teacherId;
            $session->status = 'closed';
            $session->opened_at = $session->opened_at ?? now();
            $session->closed_at = now();
            $session->headcount = collect($entries)->where('status', 'present')->count();
            $session->save();

            foreach ($entries as $e) {
                $event = AttendanceEvent::firstOrNew([
                    'session_id' => $session->id,
                    'student_id' => $e['student_id'],
                ]);
                $event->status = $e['status'];
                $event->reason = $e['reason'] ?? null;
                $event->source = 'rollcall';
                $event->scanned_by = $teacherId;
                $event->scanned_at = now();
                $event->revision = (string) Str::uuid();
                $event->save();
            }

            return $session->load('events');
        });
    }
}
