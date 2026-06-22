<?php

declare(strict_types=1);

namespace App\Domain\School\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Singleton institution profile (one row per tenant DB). Use SchoolSetting::current().
 */
class SchoolSetting extends Model
{
    use HasUuids;

    protected $table = 'school_settings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'school_type', 'motto', 'address', 'phone',
        'email', 'currency', 'principal_name', 'logo_url',
    ];

    private static ?self $cached = null;

    public static function current(): self
    {
        return self::$cached ??= self::query()->first() ?? self::create([
            'name' => config('app.name'),
            'school_type' => 'day',
            'currency' => 'XAF',
        ]);
    }

    /** Resolve the school name, falling back to app config. */
    public static function schoolName(): string
    {
        $name = self::current()->name;
        return $name !== '' && $name !== null ? $name : (string) config('app.name');
    }

    public static function flush(): void
    {
        self::$cached = null;
    }
}
