<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperUploadItem extends Model
{
    protected $fillable = [
        'paper_upload_batch_id',
        'image_path',
        'ocr_suggestion',
        'ocr_confidence',
        'confirmed_choice',
        'status',
        'response_id',
    ];

    protected function casts(): array
    {
        return [
            'ocr_confidence' => 'float',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaperUploadBatch::class, 'paper_upload_batch_id');
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }
}
