<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class FcmToken extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'user_id', 'token', 'device_name'];
}
