<?php

declare(strict_types=1);

namespace App\Domain\Issuance\Repositories;

use App\Domain\Issuance\Models\IssueEvent;
use App\Support\AppendOnlyRepository;
use Ramsey\Uuid\Uuid;
use Illuminate\Database\Eloquent\Model;

class IssueEventRepository extends AppendOnlyRepository
{
    protected string $model = IssueEvent::class;

    /** Void an issue event by appending a new 'void' event. Original untouched. */
    public function void(string $id, string $reason): Model
    {
        $original = $this->findOrFail($id);

        return $this->append([
            'revision' => ($original->revision ?? '') . '-voided',
            'student_id' => $original->student_id,
            'catalogue_item_id' => $original->catalogue_item_id,
            'cost' => $original->cost,
            'issued_at' => now(),
            'staff_id' => $original->staff_id,
            'signature_ref' => $original->signature_ref,
            'batch_id' => $original->batch_id,
            'device_id' => $original->device_id,
            'status' => 'void',
            'reason' => $reason,
        ]);
    }
}
