<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReviewerTemplateResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReviewerChainTemplateController extends Controller
{
    public function __construct(
        private ReviewerTemplateResolver $reviewerTemplateResolver,
    )
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'chain_type' => ['nullable', 'in:assessment,idp'],
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['required', 'integer', 'exists:users,id'],
            'assignment_user_ids' => ['nullable', 'array'],
            'assignment_user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $reviewerIds = collect($data['reviewer_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $assignmentUserIds = collect($data['assignment_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($reviewerIds->count() !== count($data['reviewer_ids'])) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'ไม่สามารถเลือกผู้ประเมินซ้ำในลำดับเดียวกันได้',
            ]);
        }

        $activeReviewerCount = User::query()
            ->whereIn('id', $reviewerIds)
            ->where('is_active', true)
            ->count();
        $activeAssignmentCount = User::query()
            ->whereIn('id', $assignmentUserIds)
            ->where('is_active', true)
            ->count();

        if ($activeReviewerCount !== $reviewerIds->count() || $activeAssignmentCount !== $assignmentUserIds->count()) {
            throw ValidationException::withMessages([
                'users' => 'กรุณาเลือกเฉพาะผู้ใช้งานที่เปิดใช้งานอยู่',
            ]);
        }

        foreach ($assignmentUserIds as $userId) {
            if ($reviewerIds->contains((int) $userId)) {
                throw ValidationException::withMessages([
                    'assignment_user_ids' => 'ผู้ใช้ที่ถูกประเมินต้องไม่อยู่ในลำดับผู้ประเมินของตัวเอง',
                ]);
            }
        }

        $chainType = $data['chain_type'] ?? 'assessment';

        DB::transaction(function () use ($data, $reviewerIds, $assignmentUserIds, $chainType): void {
            $now = now();
            $templatePayload = [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
                $templatePayload['chain_type'] = $chainType;
            }

            $templateId = (int) DB::table('reviewer_chain_templates')->insertGetId($templatePayload);

            DB::table('reviewer_chain_template_steps')->insert($reviewerIds
                ->map(fn (int $reviewerId, int $index): array => [
                    'template_id' => $templateId,
                    'step_order' => $index + 1,
                    'resolver_type' => 'fixed_user',
                    'role_key' => null,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all());

            foreach ($assignmentUserIds as $userId) {
                $this->removeExistingAssignments((int) $userId, $chainType);
            }

            if ($assignmentUserIds->isNotEmpty()) {
                DB::table('reviewer_chain_template_assignments')->insert($assignmentUserIds
                    ->map(fn (int $userId): array => [
                        'template_id' => $templateId,
                        'scope_type' => 'user',
                        'scope_value' => null,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all());
            }

            foreach ($assignmentUserIds as $userId) {
                $this->applyReviewerStepsToUser(
                    User::query()->findOrFail($userId),
                    $templateId,
                    $reviewerIds->all(),
                    $chainType,
                );
            }
        });

        return back()->with('success', 'บันทึกลำดับในการประเมินเรียบร้อยแล้ว');
    }

    public function update(Request $request, int $template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'reviewer_ids' => ['required', 'array', 'min:1'],
            'reviewer_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $templateRow = DB::table('reviewer_chain_templates')
            ->where('id', $template)
            ->where('is_active', true)
            ->first();

        if (! $templateRow) {
            throw ValidationException::withMessages([
                'template' => 'ไม่พบลำดับการประเมินนี้',
            ]);
        }
        $chainType = $this->chainTypeFromTemplate($templateRow);

        $reviewerIds = collect($data['reviewer_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($reviewerIds->count() !== count($data['reviewer_ids'])) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'ไม่สามารถเลือกผู้ประเมินซ้ำในลำดับเดียวกันได้',
            ]);
        }

        $activeReviewerCount = User::query()
            ->whereIn('id', $reviewerIds)
            ->where('is_active', true)
            ->count();

        if ($activeReviewerCount !== $reviewerIds->count()) {
            throw ValidationException::withMessages([
                'reviewer_ids' => 'กรุณาเลือกเฉพาะผู้ใช้งานที่เปิดใช้งานอยู่',
            ]);
        }

        $assignedUserIds = DB::table('reviewer_chain_template_assignments')
            ->where('template_id', $template)
            ->where('scope_type', 'user')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->merge(DB::table('users')
                ->where($this->templateColumnForChainType($chainType), $template)
                ->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        foreach ($assignedUserIds as $userId) {
            if ($reviewerIds->contains((int) $userId)) {
                throw ValidationException::withMessages([
                    'reviewer_ids' => 'ผู้ใช้ที่ถูกประเมินต้องไม่อยู่ในลำดับผู้ประเมินของตัวเอง',
                ]);
            }
        }

        DB::transaction(function () use ($data, $template, $reviewerIds, $assignedUserIds, $chainType): void {
            $now = now();

            DB::table('reviewer_chain_templates')
                ->where('id', $template)
                ->update([
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'updated_at' => $now,
                ]);

            DB::table('reviewer_chain_template_steps')
                ->where('template_id', $template)
                ->delete();

            DB::table('reviewer_chain_template_steps')->insert($reviewerIds
                ->map(fn (int $reviewerId, int $index): array => [
                    'template_id' => $template,
                    'step_order' => $index + 1,
                    'resolver_type' => 'fixed_user',
                    'role_key' => null,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all());

            $assignedUsers = User::query()
                ->whereIn('id', $assignedUserIds)
                ->where('is_active', true)
                ->get();

            foreach ($assignedUsers as $user) {
                $this->applyReviewerStepsToUser($user, $template, $reviewerIds->all(), $chainType);
            }
        });

        return back()->with('success', 'แก้ไขลำดับการประเมินเรียบร้อยแล้ว');
    }

    public function addUsers(Request $request, int $template): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $templateRow = DB::table('reviewer_chain_templates')
            ->where('id', $template)
            ->where('is_active', true)
            ->first();

        if (! $templateRow) {
            throw ValidationException::withMessages([
                'template' => 'ไม่พบลำดับการประเมินนี้',
            ]);
        }
        $chainType = $this->chainTypeFromTemplate($templateRow);

        $userIds = collect($data['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($users->count() !== $userIds->count()) {
            throw ValidationException::withMessages([
                'user_ids' => 'กรุณาเลือกเฉพาะผู้ใช้งานที่เปิดใช้งานอยู่',
            ]);
        }

        DB::transaction(function () use ($template, $userIds, $users, $chainType): void {
            foreach ($userIds as $userId) {
                $user = $users->get($userId);
                $reviewerIds = collect($this->reviewerTemplateResolver->resolveReviewerIdsForUser($user, $template, $chainType))
                    ->filter()
                    ->unique()
                    ->values();

                if ($reviewerIds->isEmpty()) {
                    throw ValidationException::withMessages([
                        'user_ids' => "ไม่พบผู้ประเมินสำหรับ {$user->name}",
                    ]);
                }

                if ($reviewerIds->contains((int) $user->id)) {
                    throw ValidationException::withMessages([
                        'user_ids' => "ผู้ใช้ {$user->name} ต้องไม่อยู่ในลำดับผู้ประเมินของตัวเอง",
                    ]);
                }

                $this->removeExistingAssignments((int) $user->id, $chainType);
                $this->insertTemplateAssignment($template, (int) $user->id);
                $this->applyReviewerStepsToUser($user, $template, $reviewerIds->all(), $chainType);
            }
        });

        return back()->with('success', 'เพิ่มผู้ใช้ในลำดับการประเมินเรียบร้อยแล้ว');
    }

    public function removeUser(int $template, User $user): RedirectResponse
    {
        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $templateRow = DB::table('reviewer_chain_templates')
            ->where('id', $template)
            ->where('is_active', true)
            ->first();

        if (! $templateRow) {
            throw ValidationException::withMessages([
                'template' => 'ไม่พบลำดับการประเมินนี้',
            ]);
        }
        $chainType = $this->chainTypeFromTemplate($templateRow);

        DB::transaction(function () use ($template, $user, $chainType): void {
            DB::table('reviewer_chain_template_assignments')
                ->where('template_id', $template)
                ->where('scope_type', 'user')
                ->where('user_id', $user->id)
                ->delete();

            $templateColumn = $this->templateColumnForChainType($chainType);
            if ((int) ($user->{$templateColumn} ?? 0) === $template) {
                $this->clearUserChain((int) $user->id, $template, $chainType);
            }
        });

        return back()->with('success', 'ลบผู้ใช้ออกจากลำดับการประเมินเรียบร้อยแล้ว');
    }

    public function destroy(int $template): RedirectResponse
    {
        if (! Schema::hasTable('reviewer_chain_templates')) {
            throw ValidationException::withMessages([
                'template' => 'ยังไม่ได้สร้างตาราง reviewer chain templates',
            ]);
        }

        $templateRow = DB::table('reviewer_chain_templates')
            ->where('id', $template)
            ->where('is_active', true)
            ->first();

        if (! $templateRow) {
            throw ValidationException::withMessages([
                'template' => 'ไม่พบลำดับการประเมินนี้',
            ]);
        }
        $chainType = $this->chainTypeFromTemplate($templateRow);

        DB::transaction(function () use ($template, $chainType): void {
            $affectedUserIds = DB::table('reviewer_chain_template_assignments')
                ->where('template_id', $template)
                ->where('scope_type', 'user')
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->merge(DB::table('users')
                    ->where($this->templateColumnForChainType($chainType), $template)
                    ->pluck('id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($affectedUserIds as $userId) {
                $this->clearUserChain($userId, $template, $chainType);
            }

            DB::table('reviewer_chain_templates')
                ->where('id', $template)
                ->delete();
        });

        return back()->with('success', 'ลบลำดับการประเมินเรียบร้อยแล้ว');
    }

    private function removeExistingAssignments(int $userId, string $chainType): void
    {
        $query = DB::table('reviewer_chain_template_assignments')
            ->where('scope_type', 'user')
            ->where('user_id', $userId);

        if (Schema::hasColumn('reviewer_chain_templates', 'chain_type')) {
            $templateIds = DB::table('reviewer_chain_templates')
                ->where('chain_type', $chainType)
                ->pluck('id');
            $query->whereIn('template_id', $templateIds);
        }

        $query->delete();
    }

    private function clearUserChain(int $userId, int $templateId, string $chainType): void
    {
        $templateColumn = $this->templateColumnForChainType($chainType);
        $updates = [
            $templateColumn => null,
            'updated_at' => now(),
        ];

        DB::table('users')
            ->where('id', $userId)
            ->where($templateColumn, $templateId)
            ->update($updates);

        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $userId);
        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $deleteQuery->where('chain_type', $chainType);
        }
        $deleteQuery->delete();
    }

    private function insertTemplateAssignment(int $templateId, int $userId): void
    {
        $now = now();

        DB::table('reviewer_chain_template_assignments')->insert([
            'template_id' => $templateId,
            'scope_type' => 'user',
            'scope_value' => null,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function applyReviewerStepsToUser(User $user, int $templateId, array $reviewerIds, string $chainType): void
    {
        $now = now();
        $updates = [
            $this->templateColumnForChainType($chainType) => $templateId,
            'updated_at' => $now,
        ];

        DB::table('users')
            ->where('id', $user->id)
            ->update($updates);

        if (! Schema::hasTable('user_reviewer_steps')) {
            return;
        }

        $deleteQuery = DB::table('user_reviewer_steps')->where('user_id', $user->id);
        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $deleteQuery->where('chain_type', $chainType);
        }
        $deleteQuery->delete();

        DB::table('user_reviewer_steps')->insert(collect($reviewerIds)
            ->values()
            ->map(function (int $reviewerId, int $index) use ($user, $now, $chainType): array {
                $row = [
                    'user_id' => $user->id,
                    'step_order' => $index + 1,
                    'reviewer_id' => $reviewerId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
                    $row['chain_type'] = $chainType;
                }

                return $row;
            })
            ->all());
    }

    private function chainTypeFromTemplate(object $template): string
    {
        return ($template->chain_type ?? 'assessment') === 'idp' ? 'idp' : 'assessment';
    }

    private function templateColumnForChainType(string $chainType): string
    {
        return $chainType === 'idp' ? 'idp_reviewer_template_id' : 'reviewer_template_id';
    }
}
