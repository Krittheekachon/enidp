<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Competency extends Model
{
    protected $fillable = [
        'competency_type_id',
        'code',
        'name',
        'detail',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(CompetencyType::class, 'competency_type_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(CompetencyLevel::class);
    }
}
