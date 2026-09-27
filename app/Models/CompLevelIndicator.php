<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompLevelIndicator extends Model
{
    protected $fillable = [
        'competency_level_id',
        'description',
        'weight',
    ];

    public function competencyLevel(): BelongsTo
    {
        return $this->belongsTo(CompetencyLevel::class);
    }
}
