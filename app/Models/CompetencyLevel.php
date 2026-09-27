<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyLevel extends Model
{
    protected $fillable = [
        'competency_id',
        'level',
        'description',
    ];

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(CompLevelIndicator::class);
    }
}
