<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetencyType extends Model
{
    protected $fillable = [
        'code',
        'full_name',
        'description',
    ];
}
