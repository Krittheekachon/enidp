<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'user_id',
        'assessment_round_id',
        'competency_id',
        'score',
        'note',
        'status',
        'last_draft_saved_at',
        'self_submitted_at',
        'supervisor_1_submitted_at',
        'supervisor_2_submitted_at',
        'dean_approved_at',
    ];
}
