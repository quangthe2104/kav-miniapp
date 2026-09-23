<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    protected $fillable = ['ward_id', 'external_id', 'name', 'level'];

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function classProfiles(): HasMany
    {
        return $this->hasMany(ClassProfile::class);
    }
}
