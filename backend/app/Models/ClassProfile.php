<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassProfile extends Model
{
    protected $fillable = [
        'school_id',
        'class_name',
        'quota',
        'created_by_teacher_id',
        'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by_teacher_id');
    }

    public function classForms(): HasMany
    {
        return $this->hasMany(ClassForm::class);
    }

    public function parentCoverageNotes(): HasMany
    {
        return $this->hasMany(ParentCoverageNote::class);
    }
}
