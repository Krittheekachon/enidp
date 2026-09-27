<?php

namespace App\Http\Controllers;

use App\Services\ReviewerChainResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private ReviewerChainResolver $reviewerChainResolver) {}

    /** Display the user's read-only profile. */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'pageTitle' => 'โปรไฟล์',
            'profileUser' => $this->profileData($request->user()),
        ]);
    }

    /** Reject legacy self-service profile updates. Admins edit users through user management. */
    public function update(): never
    {
        abort(403, 'หากต้องการแก้ไขข้อมูลโปรไฟล์ กรุณาติดต่อผู้ดูแลระบบ (Admin)');
    }

    private function profileData($user): array
    {
        return [
            'db_id' => $user->id,
            'sso' => $user->sso ?: (string) $user->id,
            't' => $user->title ?: '',
            'n' => $user->name,
            'fn' => $user->first_name_th ?: $user->name,
            'ln' => $user->last_name_th ?: '',
            'fe' => $user->first_name_en ?: '',
            'le' => $user->last_name_en ?: '',
            'em' => $user->email ?: '',
            'ph' => $user->phone ?: '',
            'w' => $user->workline ?: '',
            'd' => $user->department ?: '',
            'p' => $user->position ?: '',
            'l' => $user->level ?: '',
            'r' => $this->roleKeyForUser($user),
            'reviewerSteps' => $this->reviewerChainResolver->payloadForUser($user),
            'supervisorChain' => $this->reviewerChainResolver->payloadForUser($user),
            'photo' => $user->profile_photo ?: '',
            'act' => (bool) $user->is_active,
        ];
    }

    private function roleKeyFromId(?int $roleId): string
    {
        return DB::table('roles')->where('id', $roleId)->value('key') ?: 'employee';
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager_dept' => 'dept_head',
            'manager' => 'dean',
            default => $roleKey,
        };
    }

    private function roleKeyForUser($user): string
    {
        $roleKey = $user->relationLoaded('role')
            ? $user->role?->key
            : DB::table('roles')->where('id', $user->role_id)->value('key');

        return $this->normalizeRoleKey($roleKey ?: $this->roleKeyFromId($user->role_id));
    }
}
