<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassFormNote extends Model
{
    protected $fillable = [
        'class_form_id',
        'teacher_id',
        'body',
        'file_path',
        'file_name',
        'mime',
        'file_size',
    ];

    public function classForm(): BelongsTo
    {
        return $this->belongsTo(ClassForm::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime);
        if (str_starts_with($mime, 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpe?g|png|webp|gif)$/i', (string) ($this->file_name ?: $this->file_path));
    }

    public function isPdf(): bool
    {
        $mime = strtolower((string) $this->mime);
        if ($mime === 'application/pdf') {
            return true;
        }

        return (bool) preg_match('/\.pdf$/i', (string) ($this->file_name ?: $this->file_path));
    }
}
