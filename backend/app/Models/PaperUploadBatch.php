<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaperUploadBatch extends Model
{
    protected $fillable = [
        'class_form_id',
        'teacher_id',
        'status',
        'agree_count',
        'disagree_count',
        'unknown_count',
        'pending_note_body',
        'pending_note_files',
    ];

    protected function casts(): array
    {
        return [
            'pending_note_files' => 'array',
        ];
    }

    public function classForm(): BelongsTo
    {
        return $this->belongsTo(ClassForm::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PaperUploadItem::class);
    }
}
