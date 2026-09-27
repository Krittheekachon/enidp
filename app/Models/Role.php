<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Role extends Model
{
    protected $fillable = [
        'key',
        'name_th',
        'name_en',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'role_id', Schema::hasColumn('roles', 'role_id') ? 'role_id' : 'id');
    }
}
