<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'sso', 'name', 'title',
    'first_name_th', 'last_name_th',
    'first_name_en', 'last_name_en',
    'email', 'username', 'phone',
    'workline', 'department', 'position',
    'level', 'password', 'role_id', 'role_key',
    'is_active', 'profile_photo',
    'position_id', 'level_id',
    'reviewer_template_id', 'idp_reviewer_template_id',
    'profile_affiliation', 'profile_saved',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function assessments()
    {
        return $this->hasMany(Assessment::class);
    }

    public function hasRole(string|array $roleKey): bool
    {
        $normalize = static fn (?string $key): string => match ($key) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => (string) $key,
        };
        $currentRoleKey = $normalize($this->role?->key ?: $this->role_key);

        if (is_array($roleKey)) {
            return in_array($currentRoleKey, array_map($normalize, $roleKey), true);
        }

        return $currentRoleKey === $normalize($roleKey);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isHR(): bool
    {
        return $this->hasRole('hr');
    }

    public function isSupervisor(): bool
    {
        return $this->hasRole('supervisor');
    }

    public function isDeptHead(): bool
    {
        return $this->hasRole('dept_head');
    }

    public function isDivisionHead(): bool
    {
        return $this->hasRole('division_head');
    }

    public function isAcademicDepartmentHead(): bool
    {
        return $this->hasRole('academic_department_head');
    }

    public function isDean(): bool
    {
        return $this->hasRole('dean');
    }

    // ======================================================
}
