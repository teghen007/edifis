<?php

declare(strict_types=1);

namespace App\Domain\Students\Support;

use App\Domain\Academics\Models\SchoolClass;
use App\Domain\School\Models\SchoolSetting;
use App\Domain\Students\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IdCardRenderer
{
    public function render(Student $student): \Barryvdh\DomPDF\PDF
    {
        $settings = SchoolSetting::current();

        $className = SchoolClass::find($student->current_class_id)?->name
            ?? SchoolClass::find($student->class_id)?->name;

        return Pdf::loadView('id-card', [
            'student' => $student,
            'schoolName' => $settings->name ?: 'School',
            'motto' => $settings->motto,
            'className' => $className,
            'photo' => $this->toDataUri($student->getFirstMedia('photo')?->getPath()),
            'logo' => $this->logoDataUri($settings->logo_url),
            'year' => now()->year,
        ])->setPaper('a6', 'landscape');
    }

    private function logoDataUri(?string $logo): ?string
    {
        if (! $logo || Str::startsWith($logo, ['http://', 'https://'])) {
            return null; // only embed locally-stored logos (dompdf + remote is unreliable)
        }

        return $this->toDataUri(Storage::disk('public')->path($logo));
    }

    private function toDataUri(?string $path): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
