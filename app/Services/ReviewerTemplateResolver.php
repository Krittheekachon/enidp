<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewerTemplateResolver
{
    public function payload(): array
    {
        if (! Schema::hasTable('reviewer_chain_templates')) {
            return [];
        }

        $templates = DB::table('reviewer_chain_templates')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get($this->templateColumns());

        $steps = DB::table('reviewer_chain_template_steps')
            ->whereIn('template_id', $templates->pluck('id'))
            ->orderBy('step_order')
            ->get(['template_id', 'step_order', 'resolver_type', 'role_key', 'reviewer_id'])
            ->groupBy('template_id');

        $assignments = DB::table('reviewer_chain_template_assignments')
            ->whereIn('template_id', $templates->pluck('id'))
            ->get(['template_id', 'scope_type', 'scope_value', 'user_id'])
            ->groupBy('template_id');

        return $templates
            ->map(fn (object $template): array => [
                'id' => (int) $template->id,
                'name' => $template->name,
                'description' => $template->description ?: '',
                'chainType' => $template->chain_type ?? 'assessment',
                'isDefault' => (bool) $template->is_default,
                'steps' => ($steps->get($template->id) ?? collect())
                    ->map(fn (object $step): array => [
                        'step' => (int) $step->step_order,
                        'resolverType' => $step->resolver_type,
                        'roleKey' => $this->normalizeRoleKey((string) ($step->role_key ?? '')),
                        'reviewerId' => $step->reviewer_id ? (int) $step->reviewer_id : null,
                        'label' => $this->stepLabel((string) $step->resolver_type, (string) ($step->role_key ?? '')),
                    ])
                    ->values()
                    ->all(),
                'assignments' => ($assignments->get($template->id) ?? collect())
                    ->map(fn (object $assignment): array => [
                        'scopeType' => $assignment->scope_type,
                        'scopeValue' => $assignment->scope_value,
                        'userId' => $assignment->user_id ? (int) $assignment->user_id : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function resolveReviewerIdsForUser(object $user, ?int $templateId = null, string $chainType = 'assessment'): array
    {
        if (! Schema::hasTable('reviewer_chain_templates')) {
            return [];
        }

        $templateId = $templateId ?: $this->matchingTemplateIdForUser($user, $chainType);

        if (! $templateId) {
            return [];
        }

        $steps = DB::table('reviewer_chain_template_steps')
            ->where('template_id', $templateId)
            ->orderBy('step_order')
            ->get(['resolver_type', 'role_key', 'reviewer_id']);

        $userId = (int) ($user->id ?? $user->db_id ?? 0);
        $reviewerIds = [];

        foreach ($steps as $step) {
            $reviewerId = $this->resolveStepReviewerId($user, $step, $reviewerIds);

            if (! $reviewerId || $reviewerId === $userId || in_array($reviewerId, $reviewerIds, true)) {
                continue;
            }

            $reviewerIds[] = $reviewerId;
        }

        return $reviewerIds;
    }

    public function matchingTemplateIdForUser(object $user, string $chainType = 'assessment'): ?int
    {
        if (! Schema::hasTable('reviewer_chain_template_assignments')) {
            return null;
        }

        $userId = (int) ($user->id ?? $user->db_id ?? 0);
        $workline = trim((string) ($user->workline ?? $user->w ?? ''));
        $department = trim((string) ($user->department ?? $user->d ?? ''));
        $jobFamily = $this->jobFamilyFromDepartment($department);
        $position = trim((string) ($user->position ?? $user->p ?? ''));

        $queries = [
            ['user', null, $userId],
            ['position', $position, null],
            ['job_family', $jobFamily, null],
            ['workline', $workline, null],
            ['default', null, null],
        ];

        foreach ($queries as [$scopeType, $scopeValue, $scopeUserId]) {
            if ($scopeType !== 'default' && $scopeValue === '' && ! $scopeUserId) {
                continue;
            }

            $query = DB::table('reviewer_chain_template_assignments')
                ->join('reviewer_chain_templates', 'reviewer_chain_template_assignments.template_id', '=', 'reviewer_chain_templates.id')
                ->where('reviewer_chain_templates.is_active', true)
                ->where('scope_type', $scopeType);

            if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
                $query->where('reviewer_chain_templates.chain_type', $chainType);
            }

            if ($scopeType === 'user') {
                $query->where('user_id', $scopeUserId);
            } elseif ($scopeType !== 'default') {
                $query->where('scope_value', $scopeValue);
            }

            $templateId = $query
                ->orderByDesc('reviewer_chain_templates.is_default')
                ->value('reviewer_chain_templates.id');

            if ($templateId) {
                return (int) $templateId;
            }
        }

        return null;
    }

    private function resolveStepReviewerId(object $user, object $step, array $blockedIds): ?int
    {
        if ($step->resolver_type === 'fixed_user') {
            return $step->reviewer_id ? (int) $step->reviewer_id : null;
        }

        $roleKey = $this->normalizeRoleKey((string) ($step->role_key ?? ''));

        if ($roleKey === '') {
            return null;
        }

        $query = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.is_active', true)
            ->where('roles.key', $roleKey)
            ->whereNotIn('users.id', $blockedIds)
            ->orderBy('users.name');

        $userId = (int) ($user->id ?? $user->db_id ?? 0);
        if ($userId > 0) {
            $query->where('users.id', '!=', $userId);
        }

        $department = trim((string) ($user->department ?? $user->d ?? ''));
        $workline = trim((string) ($user->workline ?? $user->w ?? ''));

        if ($step->resolver_type === 'role_same_department' && $department !== '') {
            $departmentReviewerId = (clone $query)
                ->where('users.department', 'like', $this->jobFamilyFromDepartment($department).'%')
                ->value('users.id');

            if ($departmentReviewerId) {
                return (int) $departmentReviewerId;
            }
        }

        if (in_array($step->resolver_type, ['role_same_department', 'role_same_workline'], true) && $workline !== '') {
            $worklineReviewerId = (clone $query)
                ->where('users.workline', $workline)
                ->value('users.id');

            if ($worklineReviewerId) {
                return (int) $worklineReviewerId;
            }
        }

        $fallbackReviewerId = $query->value('users.id');

        return $fallbackReviewerId ? (int) $fallbackReviewerId : null;
    }

    private function templateColumns(): array
    {
        $columns = ['id', 'name', 'description', 'is_default'];

        if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
            $columns[] = 'chain_type';
        }

        return $columns;
    }

    private function jobFamilyFromDepartment(string $department): string
    {
        return trim(explode(' > ', $department)[0] ?? '');
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }

    private function stepLabel(string $resolverType, string $roleKey): string
    {
        $roleLabel = match ($this->normalizeRoleKey($roleKey)) {
            'supervisor' => 'หัวหน้าหน่วย',
            'dept_head' => 'หัวหน้างาน',
            'division_head' => 'หัวหน้าฝ่าย',
            'academic_department_head' => 'หัวหน้าภาควิชา',
            'dean' => 'ผู้บริหารคณะ',
            'hr' => 'งานทรัพยากรบุคคล',
            'admin' => 'ผู้ดูแลระบบ',
            default => 'ผู้ประเมิน',
        };

        return match ($resolverType) {
            'fixed_user' => 'ผู้ประเมินที่ระบุ',
            'role_same_department' => $roleLabel.'ในกลุ่มงานเดียวกัน',
            'role_same_workline' => $roleLabel.'ในสายงานเดียวกัน',
            default => $roleLabel,
        };
    }
}
