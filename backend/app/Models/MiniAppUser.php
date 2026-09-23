<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class MiniAppUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'miniapp_users';

    protected $fillable = [
        'zalo_user_id',
        'phone',
        'name',
        'role',
        'teacher_id',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher' && $this->teacher_id;
    }
}
