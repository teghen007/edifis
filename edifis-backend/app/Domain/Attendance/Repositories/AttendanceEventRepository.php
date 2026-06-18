<?php

declare(strict_types=1);

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\Models\AttendanceEvent;
use App\Support\AppendOnlyRepository;
use Illuminate\Database\Eloquent\Model;

class AttendanceEventRepository extends AppendOnlyRepository
{
    protected string $model = AttendanceEvent::class;

    public function void(string $id, string $reason): Model
    {
        $original = $this->findOrFail($id);

        return $this->append([
            'revision' => ($original->revision ?? '') . '-voided',
            'session_id' => $original->session_id,
            'student_id' => $original->student_id,
            'scanned_at' => now(),
            'device_id' => $original->device_id,
            'scanned_by' => $original->scanned_by,
            'source' => $original->source,
            'status' => 'void',
            'void_reason' => $reason,
        ]);
    }
}
