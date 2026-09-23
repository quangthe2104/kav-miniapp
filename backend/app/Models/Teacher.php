<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teacher extends Model
{
    protected $fillable = [
        'zalo_id',
        'phone',
        'name',
        'school_id',
        'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function classProfiles(): HasMany
    {
        return $this->hasMany(ClassProfile::class, 'created_by_teacher_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
