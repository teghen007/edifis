<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Academics\Models\Stream;
use App\Domain\Academics\Models\Subject;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use App\Domain\Students\Models\Student;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasUuids;
    use HasApiTokens;
    use HasRoles;
    use HasFactory;
    use Notifiable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'pin_hash',
        'must_reset_credential',
        'active',
        'login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'must_reset_credential' => 'boolean',
            'locked_until' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->active && $this->hasAnyRoleName([
            'principal', 'vice_principal', 'bursar', 'class_master',
            'subject_teacher', 'discipline_master', 'secretary', 'school_admin',
        ]);
    }

    public function hasAnyRoleName(array $names): bool
    {
        // Use the eager-loadable relation (loaded once per request, then cached on
        // the model) instead of a fresh query each call — Filament calls this for
        // every resource on every page render to build the navigation.
        return $this->roles->whereIn('name', $names)->isNotEmpty();
    }

    public function children()
    {
        return $this->belongsToMany(Student::class, 'guardian_student', 'guardian_id', 'student_id');
    }

    public function ownsStudent(string $studentId): bool
    {
        return $this->children()->whereKey($studentId)->exists();
    }

    public function assignedStreams(): BelongsToMany
    {
        return $this->belongsToMany(Stream::class, 'teacher_assignments', 'teacher_id', 'stream_id')
            ->withTimestamps();
    }

    public function assignedSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_assignments', 'teacher_id', 'subject_id')
            ->withTimestamps();
    }

    public function teachesSubjectInStream(string $subjectId, string $streamId): bool
    {
        return \Illuminate\Support\Facades\DB::table('teacher_assignments')
            ->where('teacher_id', $this->id)
            ->where('subject_id', $subjectId)
            ->where('stream_id', $streamId)
            ->exists();
    }

    public function mastersStream(string $streamId): bool
    {
        return \Illuminate\Support\Facades\DB::table('class_masters')
            ->where('teacher_id', $this->id)
            ->where('stream_id', $streamId)
            ->exists();
    }

    /** @return array<int,string> */
    public function masteredStreamIds(): array
    {
        return \Illuminate\Support\Facades\DB::table('class_masters')
            ->where('teacher_id', $this->id)
            ->pluck('stream_id')
            ->all();
    }
}
