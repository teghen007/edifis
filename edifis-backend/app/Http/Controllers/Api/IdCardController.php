<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Students\Models\Student;
use App\Domain\Students\Support\IdCardRenderer;
use Symfony\Component\HttpFoundation\Response;

class IdCardController
{
    public function show(string $studentId, IdCardRenderer $renderer): Response
    {
        $student = Student::findOrFail($studentId);
        $pdf = $renderer->render($student);

        $name = $student->master_pea_id ?: $student->id;

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="id-card-' . $name . '.pdf"',
        ]);
    }
}
