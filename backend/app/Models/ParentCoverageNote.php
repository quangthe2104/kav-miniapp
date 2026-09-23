<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentCoverageNote extends Model
{
    protected $fillable = [
        'class_profile_id',
        'phone_or_zalo_ref',
        'children_count',
        'note_text',
        'created_by_teacher_id',
    ];

    public function classProfile(): BelongsTo
    {
        return $this->belongsTo(ClassProfile::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'created_by_teacher_id');
    }
}
