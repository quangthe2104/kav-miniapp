<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Response extends Model
{
    protected $fillable = [
        'class_form_id',
        'channel',
        'choice',
        'zalo_user_id',
        'phone',
        'coverage_weight',
        'paper_code',
        'image_path',
        'ocr_suggestion',
        'ocr_confidence',
        'status',
        'created_by_type',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'ocr_confidence' => 'float',
        ];
    }

    public function classForm(): BelongsTo
    {
        return $this->belongsTo(ClassForm::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }
}
