<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ClassForm extends Model
{
    protected $fillable = [
        'class_profile_id',
        'form_id',
        'invite_token_hash',
        'invite_token',
        'status',
        'teacher_note',
    ];

    public function classProfile(): BelongsTo
    {
        return $this->belongsTo(ClassProfile::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClassFormNote::class)->latest();
    }

    public function isVoteOpen(): bool
    {
        if (! in_array($this->status, ['open', 'quota_full'], true)) {
            return false;
        }

        return (bool) $this->form?->isActive();
    }

    public function effectiveStatus(): string
    {
        if (! $this->form?->isActive()) {
            return 'closed';
        }

        return (string) $this->status;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function generatePlainToken(): string
    {
        return Str::random(48);
    }
}
