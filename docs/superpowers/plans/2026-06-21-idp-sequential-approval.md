# IDP Sequential Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Send each competency IDP item through the configured reviewer chain, preserve every decision, restart from the first reviewer after rejection, and unlock activity progress only after final approval.

**Architecture:** Add append-only `idp_item_reviews` history plus current workflow fields on `idp_items`. Centralize reviewer-step resolution and parent-status synchronization in one service used by employee submission, reviewer decisions, and dashboard queries. Keep each competency item independent and expose current step/history through Inertia props to the existing employee and reviewer Vue screens.

**Tech Stack:** PHP 8.3, Laravel 13, PostgreSQL, Inertia Laravel 2, Vue 3, TypeScript, Vite 8, PHPUnit feature tests.

---

## File Map

- Create `database/migrations/2026_06_21_000000_create_idp_item_reviews_table.php`: add workflow fields and append-only review history.
- Create `app/Services/IdpItemReviewWorkflow.php`: resolve configured steps, validate current reviewer, advance/reject items, and sync parent status.
- Modify `app/Http/Controllers/Employee/IdpController.php`: start the first configured review step and increment `submission_version` only after rejection.
- Modify `app/Http/Controllers/IdpApprovalController.php`: write review history and advance sequentially.
- Modify `app/Http/Controllers/DashboardController.php`: expose current step, reviewer labels, review history, and approved activities.
- Create `app/Http/Controllers/Employee/IdpActivityUpdateController.php`: persist progress only for finally approved items.
- Modify `routes/web.php`: add the progress-update route.
- Modify `resources/js/Pages/Employee/EmployeeIDP.vue`: display `review_step_1/2/3` and keep all review-step items locked.
- Modify `resources/js/Pages/Head/IdpItemApproval.vue`: optional approval comments, required rejection comments, current step, and history.
- Modify `resources/js/Pages/Employee/EmployeeProgress.vue`: list approved activities and save progress updates.
- Modify `resources/js/Pages/Employee/Dashboard.vue`: pass approved activity data to the progress page.
- Modify `resources/js/Pages/Head/Dashboard.vue`: pass the signed-in user's approved activities to the shared progress page.
- Modify `resources/js/Pages/Executive/Dashboard.vue`: pass the signed-in user's approved activities to the shared progress page.
- Modify `resources/js/Pages/HR/Dashboard.vue`: pass the signed-in user's approved activities to the shared progress page.
- Modify `.codex/skills/competency-idp-system/SKILL.md`: replace the single-supervisor IDP rule with the implemented sequential workflow.
- Modify `tests/Feature/IdpItemApprovalTest.php`: sequential-review and history coverage.
- Modify `tests/Feature/EmployeeIdpPlanTest.php`: submission-version and auto-save coverage.
- Create `tests/Unit/IdpItemReviewWorkflowTest.php`: reviewer-slot resolution coverage.
- Create `tests/Feature/IdpActivityUpdateTest.php`: final-approval progress authorization.

### Task 1: Add Sequential Review Schema

**Files:**
- Create: `database/migrations/2026_06_21_000000_create_idp_item_reviews_table.php`
- Test: `tests/Feature/IdpItemApprovalTest.php`

- [ ] **Step 1: Add a failing schema test**

Add this test to `IdpItemApprovalTest`:

```php
public function test_schema_supports_sequential_idp_review_history(): void
{
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('idp_item_reviews'));
    $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumns('idp_items', [
        'submission_version',
        'current_review_step',
    ]));
}
```

- [ ] **Step 2: Run the test and verify the missing-table failure**

Run:

```bash
php artisan test tests/Feature/IdpItemApprovalTest.php --filter=review_history_records
```

Expected: FAIL because `idp_item_reviews` and the workflow columns do not exist.

- [ ] **Step 3: Create the migration**

Create the migration with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            $table->unsignedInteger('submission_version')->default(0)->after('status');
            $table->unsignedTinyInteger('current_review_step')->nullable()->after('submission_version');
        });

        Schema::create('idp_item_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('idp_item_id')->constrained('idp_items')->cascadeOnDelete();
            $table->unsignedInteger('submission_version');
            $table->unsignedTinyInteger('review_step');
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision', 20);
            $table->text('comment')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(
                ['idp_item_id', 'submission_version', 'review_step'],
                'idp_item_reviews_item_version_step_unique'
            );
            $table->index(['reviewer_id', 'review_step', 'decision']);
        });

        DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->join('users', 'idps.user_id', '=', 'users.id')
            ->whereIn('idp_items.status', ['submitted', 'approved'])
            ->select(
                'idp_items.id',
                'idp_items.status',
                'users.supervisor_id_1',
                'users.supervisor_id_2',
                'users.supervisor_id_3'
            )
            ->orderBy('idp_items.id')
            ->each(function (object $item): void {
                if ($item->status === 'approved') {
                    DB::table('idp_items')->where('id', $item->id)->update([
                        'submission_version' => 1,
                        'current_review_step' => null,
                    ]);

                    return;
                }

                $firstStep = collect([1, 2, 3])->first(
                    fn (int $step): bool => filled($item->{'supervisor_id_'.$step})
                );

                DB::table('idp_items')->where('id', $item->id)->update([
                    'status' => $firstStep ? 'review_step_'.$firstStep : 'revision_required',
                    'submission_version' => 1,
                    'current_review_step' => $firstStep,
                    'reject_comment' => $firstStep
                        ? null
                        : 'ยังไม่ได้กำหนดผู้อนุมัติแผน IDP',
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_item_reviews');

        Schema::table('idp_items', function (Blueprint $table): void {
            $table->dropColumn(['submission_version', 'current_review_step']);
        });
    }
};
```

- [ ] **Step 4: Apply and inspect the migration**

Run:

```bash
php artisan migrate
php artisan migrate:status
```

Expected: `2026_06_21_000000_create_idp_item_reviews_table` is `Ran`.

- [ ] **Step 5: Run the schema test**

Run:

```bash
php artisan test tests/Feature/IdpItemApprovalTest.php --filter=schema_supports
```

Expected: PASS.

- [ ] **Step 6: Commit the schema**

```bash
git add database/migrations/2026_06_21_000000_create_idp_item_reviews_table.php tests/Feature/IdpItemApprovalTest.php
git commit -m "feat: เพิ่มประวัติการอนุมัติ IDP ตามลำดับ"
```

### Task 2: Centralize the IDP Review Workflow

**Files:**
- Create: `app/Services/IdpItemReviewWorkflow.php`
- Create: `tests/Unit/IdpItemReviewWorkflowTest.php`

- [ ] **Step 1: Add failing unit tests for sequential steps and skipped slots**

Create `tests/Unit/IdpItemReviewWorkflowTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\IdpItemReviewWorkflow;
use PHPUnit\Framework\TestCase;

class IdpItemReviewWorkflowTest extends TestCase
{
    public function test_it_returns_configured_steps_in_order(): void
    {
        $owner = (object) [
            'supervisor_id_1' => 11,
            'supervisor_id_2' => 22,
            'supervisor_id_3' => 33,
        ];

        $workflow = new IdpItemReviewWorkflow();

        $this->assertSame([1, 2, 3], $workflow->configuredSteps($owner)->all());
        $this->assertSame(1, $workflow->firstStep($owner));
        $this->assertSame(2, $workflow->nextStep($owner, 1));
        $this->assertSame(3, $workflow->nextStep($owner, 2));
        $this->assertNull($workflow->nextStep($owner, 3));
    }

    public function test_it_skips_empty_steps(): void
    {
        $owner = (object) [
            'supervisor_id_1' => 11,
            'supervisor_id_2' => null,
            'supervisor_id_3' => 33,
        ];

        $workflow = new IdpItemReviewWorkflow();

        $this->assertSame([1, 3], $workflow->configuredSteps($owner)->all());
        $this->assertSame(3, $workflow->nextStep($owner, 1));
    }
}
```

- [ ] **Step 2: Run the tests and verify the missing-service failure**

Run:

```bash
php artisan test tests/Unit/IdpItemReviewWorkflowTest.php
```

Expected: FAIL because `IdpItemReviewWorkflow` does not exist.

- [ ] **Step 3: Create `IdpItemReviewWorkflow`**

Implement these public methods:

```php
<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpItemReviewWorkflow
{
    public function configuredSteps(object $owner): Collection
    {
        return collect([1, 2, 3])
            ->filter(fn (int $step): bool => filled($owner->{'supervisor_id_'.$step} ?? null))
            ->values();
    }

    public function firstStep(object $owner): int
    {
        $step = $this->configuredSteps($owner)->first();

        if (! $step) {
            throw ValidationException::withMessages([
                'item' => 'ยังไม่ได้กำหนดผู้อนุมัติแผน IDP',
            ]);
        }

        return (int) $step;
    }

    public function nextStep(object $owner, int $currentStep): ?int
    {
        $step = $this->configuredSteps($owner)
            ->first(fn (int $step): bool => $step > $currentStep);

        return $step ? (int) $step : null;
    }

    public function reviewerIdForStep(object $owner, int $step): ?int
    {
        $id = $owner->{'supervisor_id_'.$step} ?? null;

        return $id ? (int) $id : null;
    }

    public function statusForStep(int $step): string
    {
        return 'review_step_'.$step;
    }

    public function assertCurrentReviewer(object $item, int $reviewerId): void
    {
        $step = (int) ($item->current_review_step ?? 0);
        $expectedReviewerId = $this->reviewerIdForStep($item, $step);

        if ($step < 1
            || $item->status !== $this->statusForStep($step)
            || $expectedReviewerId !== $reviewerId) {
            throw ValidationException::withMessages([
                'idpItemId' => 'คุณไม่มีสิทธิ์ตรวจแผนสมรรถนะนี้ หรือแผนไม่ได้อยู่ในขั้นตอนของคุณ',
            ]);
        }
    }

    public function syncParentStatus(int $idpId): void
    {
        $statuses = DB::table('idp_items')->where('idp_id', $idpId)->pluck('status');
        $underReview = $statuses->contains(
            fn (string $status): bool => str_starts_with($status, 'review_step_')
        );

        $status = match (true) {
            $statuses->isNotEmpty()
                && $statuses->every(fn (string $status): bool => $status === 'approved') => 'approved',
            $underReview => 'partially_submitted',
            $statuses->contains('revision_required') => 'revision_required',
            $statuses->contains('approved') => 'in_progress',
            default => 'draft',
        };

        DB::table('idps')->where('id', $idpId)->update([
            'status' => $status,
            'submitted_at' => $underReview ? now() : null,
            'updated_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Run the workflow unit tests**

Run:

```bash
php artisan test tests/Unit/IdpItemReviewWorkflowTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit the workflow service**

```bash
git add app/Services/IdpItemReviewWorkflow.php tests/Unit/IdpItemReviewWorkflowTest.php
git commit -m "feat: เพิ่มตัวจัดการลำดับอนุมัติ IDP"
```

### Task 3: Start and Restart Review from Employee Submission

**Files:**
- Modify: `app/Http/Controllers/Employee/IdpController.php`
- Modify: `tests/Feature/EmployeeIdpPlanTest.php`

- [ ] **Step 1: Add failing submission and resubmission tests**

Add:

```php
public function test_employee_submission_starts_at_first_configured_reviewer(): void
{
    $reviewer2 = User::factory()->create(['role_id' => $this->roleId('dept_head')]);
    $employee = User::factory()->create([
        'role_id' => $this->roleId('employee'),
        'supervisor_id_1' => null,
        'supervisor_id_2' => $reviewer2->id,
    ]);
    $competencyId = $this->competencyId('CC-STEP-TWO');
    $gapId = $this->approvedGap($employee, $competencyId);
    DB::table('learning_method_types')->insert([
        'key' => 'experiential-learning',
        'label' => 'Experiential Learning',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $toolId = DB::table('idp_learning_methods')->insertGetId([
        'code' => 'EXP-STEP',
        'focus_type' => 'experiential',
        'title' => 'Project Assignment',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $payload = $this->completePlanPayload($gapId, $toolId);

    $this->actingAs($employee)
        ->post(route('employee.idp.submit-item'), ['item' => $payload])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('idp_items', [
        'competency_gap_id' => $gapId,
        'status' => 'review_step_2',
        'submission_version' => 1,
        'current_review_step' => 2,
    ]);
}

public function test_resubmission_after_rejection_increments_version_and_restarts_first_step(): void
{
    $reviewer1 = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
    $reviewer2 = User::factory()->create(['role_id' => $this->roleId('dept_head')]);
    $employee = User::factory()->create([
        'role_id' => $this->roleId('employee'),
        'supervisor_id_1' => $reviewer1->id,
        'supervisor_id_2' => $reviewer2->id,
    ]);
    $competencyId = $this->competencyId('CC-RESUBMIT');
    $gapId = $this->approvedGap($employee, $competencyId);
    DB::table('learning_method_types')->insert([
        'key' => 'experiential-learning',
        'label' => 'Experiential Learning',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $toolId = DB::table('idp_learning_methods')->insertGetId([
        'code' => 'EXP-RESUBMIT',
        'focus_type' => 'experiential',
        'title' => 'Project Assignment',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $payload = $this->completePlanPayload($gapId, $toolId);

    $this->actingAs($employee)
        ->post(route('employee.idp.submit-item'), ['item' => $payload])
        ->assertSessionHasNoErrors();

    $itemId = (int) DB::table('idp_items')
        ->where('competency_gap_id', $gapId)
        ->value('id');
    DB::table('idp_items')->where('id', $itemId)->update([
        'status' => 'revision_required',
        'current_review_step' => null,
        'rejected_by' => $reviewer2->id,
        'rejected_at' => now(),
        'reject_comment' => 'แก้ไขแผน',
        'updated_at' => now(),
    ]);

    $this->actingAs($employee)
        ->post(route('employee.idp.submit-item'), [
            'item' => $payload,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('idp_items', [
        'id' => $itemId,
        'status' => 'review_step_1',
        'submission_version' => 2,
        'current_review_step' => 1,
        'reject_comment' => null,
    ]);
}
```

- [ ] **Step 2: Run the focused employee tests**

Run:

```bash
php artisan test tests/Feature/EmployeeIdpPlanTest.php --filter='submission_starts|resubmission_after_rejection'
```

Expected: FAIL because submission still writes `submitted`.

- [ ] **Step 3: Inject and use `IdpItemReviewWorkflow`**

Add constructor injection:

```php
public function __construct(
    private readonly \App\Services\IdpItemReviewWorkflow $reviewWorkflow
) {
}
```

Before the transaction, load the authenticated owner:

```php
$owner = DB::table('users')
    ->where('id', auth()->id())
    ->first(['id', 'supervisor_id_1', 'supervisor_id_2', 'supervisor_id_3']);
```

Inside `persist()`, replace `submitted` assignment with:

```php
$isSubmission = $status === 'submitted';
$firstReviewStep = $isSubmission
    ? $this->reviewWorkflow->firstStep($owner)
    : null;
$nextSubmissionVersion = $isSubmission
    ? ((int) ($existing->submission_version ?? 0) + 1)
    : (int) ($existing->submission_version ?? 0);
$itemStatus = $isSubmission
    ? $this->reviewWorkflow->statusForStep($firstReviewStep)
    : 'draft';

$values = [
    'behavior_key' => 'competency-gap:'.$item['competencyGapId'],
    'behavior_description' => null,
    'goal' => $item['goal'] ?? null,
    'success_criteria' => $item['successCriteria'] ?? null,
    'status' => $itemStatus,
    'submission_version' => $nextSubmissionVersion,
    'current_review_step' => $firstReviewStep,
    'submitted_at' => $isSubmission ? now() : ($existing->submitted_at ?? null),
    'approved_by' => $isSubmission ? null : ($existing->approved_by ?? null),
    'approved_at' => $isSubmission ? null : ($existing->approved_at ?? null),
    'rejected_by' => $isSubmission ? null : ($existing->rejected_by ?? null),
    'rejected_at' => $isSubmission ? null : ($existing->rejected_at ?? null),
    'reject_comment' => $isSubmission ? null : ($existing->reject_comment ?? null),
    'updated_at' => now(),
];
```

Treat all review steps as locked:

```php
private function isLockedStatus(string $status): bool
{
    return $status === 'approved' || str_starts_with($status, 'review_step_');
}
```

Use it instead of checking `['submitted', 'approved']`.

- [ ] **Step 4: Delegate parent synchronization to the service**

Replace the controller’s `syncIdpStatus()` body/calls with:

```php
$this->reviewWorkflow->syncParentStatus($idpId);
```

Remove the duplicate private method after all calls are replaced.

- [ ] **Step 5: Run employee IDP tests**

Run:

```bash
php artisan test tests/Feature/EmployeeIdpPlanTest.php
```

Expected: PASS, including auto-save not overwriting any `review_step_N` item.

- [ ] **Step 6: Commit employee submission**

```bash
git add app/Http/Controllers/Employee/IdpController.php tests/Feature/EmployeeIdpPlanTest.php
git commit -m "feat: ส่งแผน IDP เข้าลำดับอนุมัติแรก"
```

### Task 4: Record and Advance Reviewer Decisions

**Files:**
- Modify: `app/Http/Controllers/IdpApprovalController.php`
- Modify: `tests/Feature/IdpItemApprovalTest.php`

- [ ] **Step 1: Add failing sequential, history, rejection, and authorization tests**

Add tests for:

```php
public function test_approval_advances_through_configured_reviewers(): void
{
    [$employee, $reviewer1, $reviewer2, $reviewer3, $itemId] =
        $this->submittedItemWithReviewers([1, 2, 3]);

    $this->actingAs($reviewer1)
        ->post(route('idp-items.approve'), [
            'idpItemId' => $itemId,
            'comment' => 'ผ่านลำดับแรก',
        ])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('idp_items', [
        'id' => $itemId,
        'status' => 'review_step_2',
        'current_review_step' => 2,
    ]);
    $this->assertDatabaseHas('idp_item_reviews', [
        'idp_item_id' => $itemId,
        'submission_version' => 1,
        'review_step' => 1,
        'reviewer_id' => $reviewer1->id,
        'decision' => 'approved',
        'comment' => 'ผ่านลำดับแรก',
    ]);

    $this->actingAs($reviewer2)
        ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('idp_items', [
        'id' => $itemId,
        'status' => 'review_step_3',
        'current_review_step' => 3,
    ]);

    $this->actingAs($reviewer3)
        ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
        ->assertSessionHasNoErrors();
    $this->assertDatabaseHas('idp_items', [
        'id' => $itemId,
        'status' => 'approved',
        'current_review_step' => null,
    ]);
}

public function test_approval_skips_empty_reviewer_slots(): void
{
    [$employee, $reviewer1, , $reviewer3, $itemId] =
        $this->submittedItemWithReviewers([1, 3]);

    $this->actingAs($reviewer1)
        ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('idp_items', [
        'id' => $itemId,
        'status' => 'review_step_3',
        'current_review_step' => 3,
    ]);
}

public function test_rejection_records_history_and_returns_item_to_employee(): void
{
    [$employee, $reviewer1, , , $itemId] = $this->submittedItemWithReviewers([1]);

    $this->actingAs($reviewer1)
        ->post(route('idp-items.reject'), [
            'idpItemId' => $itemId,
            'comment' => 'แก้ช่วงเวลาดำเนินการ',
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('idp_items', [
        'id' => $itemId,
        'status' => 'revision_required',
        'current_review_step' => null,
        'reject_comment' => 'แก้ช่วงเวลาดำเนินการ',
    ]);
    $this->assertDatabaseHas('idp_item_reviews', [
        'idp_item_id' => $itemId,
        'submission_version' => 1,
        'review_step' => 1,
        'reviewer_id' => $reviewer1->id,
        'decision' => 'rejected',
    ]);
}

public function test_previous_reviewer_cannot_decide_after_item_advances(): void
{
    [$employee, $reviewer1, $reviewer2, , $itemId] = $this->submittedItemWithReviewers([1, 2]);

    $this->actingAs($reviewer1)
        ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
        ->assertSessionHasNoErrors();

    $this->actingAs($reviewer1)
        ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
        ->assertSessionHasErrors('idpItemId');
}
```

Replace the old `submittedItem()` helper with:

```php
private function submittedItemWithReviewers(array $steps): array
{
    $roleByStep = [
        1 => 'supervisor',
        2 => 'dept_head',
        3 => 'dean',
    ];
    $reviewers = [1 => null, 2 => null, 3 => null];

    foreach ($steps as $step) {
        $reviewers[$step] = User::factory()->create([
            'role_id' => $this->roleId($roleByStep[$step]),
        ]);
    }

    $employee = User::factory()->create([
        'role_id' => $this->roleId('employee'),
        'supervisor_id_1' => $reviewers[1]?->id,
        'supervisor_id_2' => $reviewers[2]?->id,
        'supervisor_id_3' => $reviewers[3]?->id,
    ]);
    $idpId = DB::table('idps')->insertGetId([
        'user_id' => $employee->id,
        'year' => 2569,
        'status' => 'partially_submitted',
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $firstStep = min($steps);
    $itemId = DB::table('idp_items')->insertGetId([
        'idp_id' => $idpId,
        'goal' => 'พัฒนาสมรรถนะ',
        'success_criteria' => 'ผ่านตามเกณฑ์',
        'status' => 'review_step_'.$firstStep,
        'submission_version' => 1,
        'current_review_step' => $firstStep,
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [
        $employee,
        $reviewers[1],
        $reviewers[2],
        $reviewers[3],
        $itemId,
    ];
}
```

- [ ] **Step 2: Run the controller tests and verify failures**

Run:

```bash
php artisan test tests/Feature/IdpItemApprovalTest.php
```

Expected: FAIL because history and next-step transitions are not implemented.

- [ ] **Step 3: Replace `IdpApprovalController` decision logic**

Inject `IdpItemReviewWorkflow`, validate optional approval comments, and load all evaluator IDs:

```php
private function reviewableItem(int $itemId): object
{
    $item = DB::table('idp_items')
        ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
        ->join('users', 'idps.user_id', '=', 'users.id')
        ->where('idp_items.id', $itemId)
        ->select(
            'idp_items.id',
            'idp_items.idp_id',
            'idp_items.status',
            'idp_items.submission_version',
            'idp_items.current_review_step',
            'users.supervisor_id_1',
            'users.supervisor_id_2',
            'users.supervisor_id_3'
        )
        ->lockForUpdate()
        ->first();

    if (! $item) {
        throw ValidationException::withMessages([
            'idpItemId' => 'ไม่พบแผนสมรรถนะ',
        ]);
    }

    $this->reviewWorkflow->assertCurrentReviewer($item, (int) auth()->id());

    return $item;
}
```

Use one transaction for approval:

```php
$validated = $request->validate([
    'idpItemId' => ['required', 'integer'],
    'comment' => ['nullable', 'string'],
]);

DB::transaction(function () use ($validated): void {
    $item = $this->reviewableItem((int) $validated['idpItemId']);
    $step = (int) $item->current_review_step;
    $now = now();

    DB::table('idp_item_reviews')->insert([
        'idp_item_id' => $item->id,
        'submission_version' => $item->submission_version,
        'review_step' => $step,
        'reviewer_id' => auth()->id(),
        'decision' => 'approved',
        'comment' => filled($validated['comment'] ?? null)
            ? trim($validated['comment'])
            : null,
        'decided_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $nextStep = $this->reviewWorkflow->nextStep($item, $step);
    DB::table('idp_items')->where('id', $item->id)->update($nextStep
        ? [
            'status' => $this->reviewWorkflow->statusForStep($nextStep),
            'current_review_step' => $nextStep,
            'updated_at' => $now,
        ]
        : [
            'status' => 'approved',
            'current_review_step' => null,
            'approved_by' => auth()->id(),
            'approved_at' => $now,
            'reject_comment' => null,
            'updated_at' => $now,
        ]);

    $this->reviewWorkflow->syncParentStatus((int) $item->idp_id);
});
```

Implement rejection with this transaction:

```php
$validated = $request->validate([
    'idpItemId' => ['required', 'integer'],
    'comment' => ['required', 'string'],
]);

DB::transaction(function () use ($validated): void {
    $item = $this->reviewableItem((int) $validated['idpItemId']);
    $step = (int) $item->current_review_step;
    $comment = trim($validated['comment']);
    $now = now();

    DB::table('idp_item_reviews')->insert([
        'idp_item_id' => $item->id,
        'submission_version' => $item->submission_version,
        'review_step' => $step,
        'reviewer_id' => auth()->id(),
        'decision' => 'rejected',
        'comment' => $comment,
        'decided_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('idp_items')->where('id', $item->id)->update([
        'status' => 'revision_required',
        'current_review_step' => null,
        'approved_by' => null,
        'approved_at' => null,
        'rejected_by' => auth()->id(),
        'rejected_at' => $now,
        'reject_comment' => $comment,
        'updated_at' => $now,
    ]);

    $this->reviewWorkflow->syncParentStatus((int) $item->idp_id);
});
```

- [ ] **Step 4: Run all IDP review tests**

Run:

```bash
php artisan test tests/Feature/IdpItemApprovalTest.php tests/Feature/EmployeeIdpPlanTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit reviewer decisions**

```bash
git add app/Http/Controllers/IdpApprovalController.php tests/Feature/IdpItemApprovalTest.php
git commit -m "feat: อนุมัติและส่งกลับ IDP ตามลำดับหัวหน้า"
```

### Task 5: Expose Current Reviewer and Review History

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `tests/Feature/IdpItemApprovalTest.php`

- [ ] **Step 1: Add failing dashboard payload assertions**

Add a test that requests the reviewer dashboard:

```php
public function test_dashboard_only_returns_items_waiting_for_current_reviewer_with_history(): void
{
    [$employee, $reviewer1, $reviewer2, , $itemId] = $this->submittedItemWithReviewers([1, 2]);
    $this->actingAs($reviewer1)
        ->post(route('idp-items.approve'), [
            'idpItemId' => $itemId,
            'comment' => 'ผ่านลำดับแรก',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($reviewer2)
        ->get('/dashboard?page=dh-idp')
        ->assertOk()
        ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->where('idpReviewItems.0.id', $itemId)
            ->where('idpReviewItems.0.currentReviewStep', 2)
            ->where('idpReviewItems.0.reviewHistory.0.reviewStep', 1)
            ->where('idpReviewItems.0.reviewHistory.0.decision', 'approved')
        );
}
```

- [ ] **Step 2: Run the test and verify payload failure**

Run:

```bash
php artisan test tests/Feature/IdpItemApprovalTest.php --filter=dashboard_only_returns
```

Expected: FAIL because the dashboard query only supports `supervisor_id_1` and `submitted`.

- [ ] **Step 3: Update `currentUserIdpPayload()`**

Select and return:

```php
'idp_items.submission_version',
'idp_items.current_review_step',
```

Map:

```php
'submissionVersion' => (int) ($first->submission_version ?? 0),
'currentReviewStep' => $first->current_review_step
    ? (int) $first->current_review_step
    : null,
```

- [ ] **Step 4: Update `idpReviewItemsForReviewer()`**

Filter dynamically:

```php
->where(function ($query) use ($reviewer): void {
    foreach ([1, 2, 3] as $step) {
        $query->{$step === 1 ? 'where' : 'orWhere'}(function ($query) use ($reviewer, $step): void {
            $query->where("users.supervisor_id_{$step}", $reviewer->id)
                ->where('idp_items.current_review_step', $step)
                ->where('idp_items.status', "review_step_{$step}");
        });
    }
})
```

Load review history grouped by item:

```php
$history = DB::table('idp_item_reviews')
    ->join('users', 'idp_item_reviews.reviewer_id', '=', 'users.id')
    ->whereIn('idp_item_reviews.idp_item_id', $items->pluck('id'))
    ->select(
        'idp_item_reviews.idp_item_id',
        'idp_item_reviews.submission_version',
        'idp_item_reviews.review_step',
        'idp_item_reviews.decision',
        'idp_item_reviews.comment',
        'idp_item_reviews.decided_at',
        'users.name as reviewer_name',
        'users.title as reviewer_title'
    )
    ->orderByDesc('idp_item_reviews.submission_version')
    ->orderBy('idp_item_reviews.review_step')
    ->get()
    ->groupBy('idp_item_id');
```

Return `currentReviewStep`, `submissionVersion`, and `reviewHistory` in each item payload.

- [ ] **Step 5: Run dashboard and query-performance tests**

Run:

```bash
php artisan test tests/Feature/IdpItemApprovalTest.php tests/Feature/DashboardQueryPerformanceTest.php
```

Expected: PASS without introducing per-item query loops.

- [ ] **Step 6: Commit dashboard data**

```bash
git add app/Http/Controllers/DashboardController.php tests/Feature/IdpItemApprovalTest.php
git commit -m "feat: แสดงแผน IDP ตามผู้อนุมัติปัจจุบัน"
```

### Task 6: Update Employee and Reviewer Interfaces

**Files:**
- Modify: `resources/js/Pages/Employee/EmployeeIDP.vue`
- Modify: `resources/js/Pages/Head/IdpItemApproval.vue`

- [ ] **Step 1: Extend employee plan types and status labels**

Add fields:

```ts
type Plan = {
  competencyGapId: number;
  goal: string;
  successCriteria: string;
  status: string;
  submissionVersion: number;
  currentReviewStep: number | null;
  rejectComment: string;
  activities: Activity[];
};
```

Lock all review steps:

```ts
const isReviewStatus = (status: string) => /^review_step_[123]$/.test(status);
const isPlanLocked = (plan: Plan | null) =>
  plan?.status === 'approved' || isReviewStatus(plan?.status || '');
```

Map labels:

```ts
const planStatusLabel = (plan: Plan | null) => ({
  review_step_1: 'รอผู้อนุมัติลำดับ 1',
  review_step_2: 'รอผู้อนุมัติลำดับ 2',
  review_step_3: 'รอผู้อนุมัติลำดับ 3',
  approved: 'อนุมัติครบทุกลำดับแล้ว',
  revision_required: 'ส่งกลับให้แก้ไข',
}[plan?.status || ''] || 'ร่าง');
```

- [ ] **Step 2: Preserve the current employee interaction**

Keep:

- auto-save delay at one second;
- independent submit button for selected competency;
- rejection alert;
- all other draft items editable.

Change button/footnote copy to mention that resubmission restarts from the first configured reviewer.

- [ ] **Step 3: Add optional approval comment and history to reviewer UI**

Use one textarea for both decisions:

```vue
<textarea
    v-model="comments[item.id]"
    rows="2"
    placeholder="ความคิดเห็นเพิ่มเติม (ไม่บังคับสำหรับอนุมัติ)"
/>
```

Keep rejection validation:

```js
if (!comment) {
    window.alert('กรุณาระบุเหตุผลที่ส่งกลับ');
    return;
}
```

Send optional approval comment:

```js
router.post(route('idp-items.approve'), {
    idpItemId: item.id,
    comment: String(comments.value[item.id] || '').trim() || null,
}, options);
```

Render current step and history:

```vue
<span class="review-step">รออนุมัติลำดับ {{ item.currentReviewStep }}</span>

<details v-if="item.reviewHistory?.length" class="review-history">
    <summary>ประวัติการพิจารณา {{ item.reviewHistory.length }} รายการ</summary>
    <div v-for="review in item.reviewHistory" :key="`${review.submissionVersion}-${review.reviewStep}`">
        ครั้งที่ {{ review.submissionVersion }} · ลำดับ {{ review.reviewStep }}
        · {{ review.decision === 'approved' ? 'อนุมัติ' : 'ส่งกลับ' }}
        · {{ review.reviewerName }}
        <p v-if="review.comment">{{ review.comment }}</p>
    </div>
</details>
```

- [ ] **Step 4: Build the frontend**

Run:

```bash
npm run build
```

Expected: Vite exits 0 with no Vue/TypeScript errors.

- [ ] **Step 5: Commit the sequential-review UI**

```bash
git add resources/js/Pages/Employee/EmployeeIDP.vue resources/js/Pages/Head/IdpItemApproval.vue
git commit -m "feat: แสดงสถานะและประวัติอนุมัติ IDP"
```

### Task 7: Enable Progress Updates Only After Final Approval

**Files:**
- Create: `app/Http/Controllers/Employee/IdpActivityUpdateController.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Employee/Dashboard.vue`
- Modify: `resources/js/Pages/Head/Dashboard.vue`
- Modify: `resources/js/Pages/Executive/Dashboard.vue`
- Modify: `resources/js/Pages/HR/Dashboard.vue`
- Modify: `resources/js/Pages/Employee/EmployeeProgress.vue`
- Create: `tests/Feature/IdpActivityUpdateTest.php`

- [ ] **Step 1: Write failing authorization and persistence tests**

Create:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdpActivityUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_update_activity_after_final_approval(): void
    {
        [$employee, $activityId] = $this->activityForStatus('approved');

        $this->actingAs($employee)
            ->post(route('employee.idp-activities.update-progress'), [
                'activityId' => $activityId,
                'progressNote' => 'ดำเนินการแล้วครึ่งหนึ่ง',
                'percentComplete' => 50,
                'evidenceUrl' => 'https://example.test/evidence',
                'evidenceDescription' => 'หลักฐานการดำเนินงาน',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_activity_updates', [
            'activity_id' => $activityId,
            'updated_by' => $employee->id,
            'percent_complete' => 50,
        ]);
    }

    public function test_employee_cannot_update_activity_before_final_approval(): void
    {
        [$employee, $activityId] = $this->activityForStatus('review_step_2');

        $this->actingAs($employee)
            ->post(route('employee.idp-activities.update-progress'), [
                'activityId' => $activityId,
                'progressNote' => 'ยังไม่ควรบันทึกได้',
                'percentComplete' => 10,
            ])
            ->assertSessionHasErrors('activityId');
    }

    private function activityForStatus(string $status): array
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id,
            'year' => 2569,
            'status' => $status === 'approved' ? 'approved' : 'partially_submitted',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId,
            'goal' => 'พัฒนาสมรรถนะ',
            'success_criteria' => 'ผ่านตามเกณฑ์',
            'status' => $status,
            'submission_version' => 1,
            'current_review_step' => str_starts_with($status, 'review_step_')
                ? (int) str_replace('review_step_', '', $status)
                : null,
            'submitted_at' => now(),
            'approved_at' => $status === 'approved' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activityId = DB::table('idp_activities')->insertGetId([
            'idp_item_id' => $itemId,
            'activity_name' => 'Project Assignment',
            'weight_percent' => 100,
            'start_date' => '2026-06-21',
            'end_date' => '2026-07-21',
            'status' => 'planned',
            'result' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$employee, $activityId];
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}
```

- [ ] **Step 2: Run the tests and verify route/controller failure**

Run:

```bash
php artisan test tests/Feature/IdpActivityUpdateTest.php
```

Expected: FAIL because the route does not exist.

- [ ] **Step 3: Create the progress controller**

Implement:

```php
<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpActivityUpdateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'activityId' => ['required', 'integer'],
            'progressNote' => ['nullable', 'string'],
            'percentComplete' => ['required', 'integer', 'min:0', 'max:100'],
            'evidenceUrl' => ['nullable', 'url', 'max:2048'],
            'evidenceDescription' => ['nullable', 'string'],
        ]);

        $activity = DB::table('idp_activities')
            ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->where('idp_activities.id', $data['activityId'])
            ->where('idps.user_id', $request->user()->id)
            ->select('idp_activities.id', 'idp_items.status')
            ->first();

        if (! $activity || $activity->status !== 'approved') {
            throw ValidationException::withMessages([
                'activityId' => 'สามารถอัปเดตความก้าวหน้าได้หลังแผนผ่านการอนุมัติครบทุกลำดับแล้ว',
            ]);
        }

        DB::table('idp_activity_updates')->insert([
            'activity_id' => $activity->id,
            'progress_note' => $data['progressNote'] ?? null,
            'percent_complete' => $data['percentComplete'],
            'evidence_url' => $data['evidenceUrl'] ?? null,
            'evidence_description' => $data['evidenceDescription'] ?? null,
            'updated_by' => $request->user()->id,
            'status' => 'saved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกความก้าวหน้ากิจกรรมแล้ว');
    }
}
```

Add the route:

```php
Route::post(
    '/employee/idp-activities/progress',
    [IdpActivityUpdateController::class, 'store']
)->name('employee.idp-activities.update-progress');
```

- [ ] **Step 4: Expose approved activities from `DashboardController`**

Add:

```php
private function currentUserApprovedIdpActivities(User $user): array
{
    $latestUpdateIds = DB::table('idp_activity_updates')
        ->selectRaw('MAX(id) as id, activity_id')
        ->groupBy('activity_id');

    return DB::table('idp_activities')
        ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
        ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
        ->join('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
        ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
        ->leftJoinSub($latestUpdateIds, 'latest_update_ids', function ($join): void {
            $join->on('idp_activities.id', '=', 'latest_update_ids.activity_id');
        })
        ->leftJoin(
            'idp_activity_updates',
            'latest_update_ids.id',
            '=',
            'idp_activity_updates.id'
        )
        ->where('idps.user_id', $user->id)
        ->where('idp_items.status', 'approved')
        ->select(
            'idp_activities.id',
            'idp_activities.activity_name',
            'idp_activities.start_date',
            'idp_activities.end_date',
            'competencies.code as competency_code',
            'competencies.name as competency_name',
            'idp_activity_updates.progress_note',
            'idp_activity_updates.percent_complete',
            'idp_activity_updates.evidence_url',
            'idp_activity_updates.evidence_description'
        )
        ->orderBy('competencies.code')
        ->orderBy('idp_activities.id')
        ->get()
        ->map(fn (object $activity): array => [
            'id' => (int) $activity->id,
            'competencyCode' => $activity->competency_code,
            'competencyName' => $activity->competency_name,
            'name' => $activity->activity_name,
            'startDate' => $activity->start_date,
            'endDate' => $activity->end_date,
            'latestProgressNote' => $activity->progress_note ?? '',
            'latestPercentComplete' => (int) ($activity->percent_complete ?? 0),
            'latestEvidenceUrl' => $activity->evidence_url ?? '',
            'latestEvidenceDescription' => $activity->evidence_description ?? '',
        ])
        ->values()
        ->all();
}
```

Pass this prop in each relevant `Inertia::render()` payload:

```php
'currentUserApprovedIdpActivities' => $this->currentUserApprovedIdpActivities(auth()->user()),
```

Add it to employee, supervisor, department-head, HR, and executive dashboards that render the employee progress page.

- [ ] **Step 5: Replace the employee progress placeholder**

Accept:

```ts
const props = defineProps<{
  activities?: Array<{
    id: number;
    competencyCode: string;
    competencyName: string;
    name: string;
    startDate: string;
    endDate: string;
    latestProgressNote: string;
    latestPercentComplete: number;
    latestEvidenceUrl: string;
    latestEvidenceDescription: string;
  }>;
}>();
```

Create local form state:

```ts
import { reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const forms = reactive<Record<number, {
  progressNote: string;
  percentComplete: number;
  evidenceUrl: string;
  evidenceDescription: string;
}>>({});
const processingId = ref<number | null>(null);

watch(() => props.activities, (activities) => {
  for (const activity of activities || []) {
    forms[activity.id] = {
      progressNote: activity.latestProgressNote || '',
      percentComplete: activity.latestPercentComplete || 0,
      evidenceUrl: activity.latestEvidenceUrl || '',
      evidenceDescription: activity.latestEvidenceDescription || '',
    };
  }
}, { immediate: true, deep: true });

const saveProgress = (activityId: number) => {
  processingId.value = activityId;
  router.post(route('employee.idp-activities.update-progress'), {
    activityId,
    ...forms[activityId],
  }, {
    preserveScroll: true,
    onFinish: () => { processingId.value = null; },
  });
};
```

Render one compact activity row with:

```vue
<article v-for="activity in activities" :key="activity.id" class="progress-item">
  <header>
    <span>{{ activity.competencyCode }}</span>
    <strong>{{ activity.competencyName }}</strong>
    <small>{{ activity.name }} · {{ activity.startDate }} - {{ activity.endDate }}</small>
  </header>
  <textarea v-model="forms[activity.id].progressNote" rows="3" />
  <input v-model.number="forms[activity.id].percentComplete" type="number" min="0" max="100" />
  <input v-model="forms[activity.id].evidenceUrl" type="url" />
  <textarea v-model="forms[activity.id].evidenceDescription" rows="2" />
  <button
    type="button"
    :disabled="processingId === activity.id"
    @click="saveProgress(activity.id)"
  >
    บันทึกความก้าวหน้า
  </button>
</article>
```

Update `Employee/Dashboard.vue`:

```vue
<EmployeeProgress
    v-else-if="activePage === 'emp-progress'"
    :activities="page.props.currentUserApprovedIdpActivities || []"
/>
```

Apply the same prop to the shared `EmployeeProgress` usage in:

- `resources/js/Pages/Head/Dashboard.vue`
- `resources/js/Pages/Executive/Dashboard.vue`
- `resources/js/Pages/HR/Dashboard.vue`

- [ ] **Step 6: Run progress tests and frontend build**

Run:

```bash
php artisan test tests/Feature/IdpActivityUpdateTest.php
npm run build
```

Expected: all tests pass and Vite exits 0.

- [ ] **Step 7: Commit progress updates**

```bash
git add app/Http/Controllers/Employee/IdpActivityUpdateController.php app/Http/Controllers/DashboardController.php routes/web.php resources/js/Pages/Employee/Dashboard.vue resources/js/Pages/Head/Dashboard.vue resources/js/Pages/Executive/Dashboard.vue resources/js/Pages/HR/Dashboard.vue resources/js/Pages/Employee/EmployeeProgress.vue tests/Feature/IdpActivityUpdateTest.php
git commit -m "feat: เปิดอัปเดตความก้าวหน้าหลังอนุมัติ IDP"
```

### Task 8: Update the Project Skill

**Files:**
- Modify: `.codex/skills/competency-idp-system/SKILL.md`

- [ ] **Step 1: Update the IDP approval summary**

Replace the single-reviewer wording with:

```text
idp_items.status
  draft / revision_required
  -> review_step_1
  -> review_step_2
  -> review_step_3
  -> approved
```

Document these rules:

- skip unconfigured reviewer slots;
- derive reviewers from `users.supervisor_id_1/2/3`;
- keep one `idp_item_reviews` row per submitted version and review step;
- require rejection comments;
- restart at the first configured reviewer after resubmission;
- unlock activity progress only after final approval;
- treat `submission_version` as an IDP revision number, not an assessment cycle.

- [ ] **Step 2: Check the documentation diff**

Run:

```bash
git diff --check
rg -n "review_step|submission_version|idp_item_reviews|progress" .codex/skills/competency-idp-system/SKILL.md
```

Expected: the sequential workflow and table ownership are discoverable in the skill.

- [ ] **Step 3: Commit the skill update**

```bash
git add .codex/skills/competency-idp-system/SKILL.md
git commit -m "docs: อัปเดตขั้นตอนอนุมัติ IDP ตามลำดับ"
```

### Task 9: Final Regression Verification

**Files:**
- Modify only if verification exposes a defect.

- [ ] **Step 1: Run all focused workflow tests**

```bash
php artisan test \
  tests/Feature/EmployeeIdpPlanTest.php \
  tests/Feature/IdpItemApprovalTest.php \
  tests/Feature/IdpActivityUpdateTest.php \
  tests/Feature/AssessmentReviewerChainTest.php \
  tests/Feature/DashboardQueryPerformanceTest.php
```

Expected: PASS.

- [ ] **Step 2: Run migration and route checks**

```bash
php artisan migrate:status
php artisan route:list --name=idp
```

Expected:

- review-history migration is `Ran`;
- employee submit, reviewer approve/reject, and progress routes are present.

- [ ] **Step 3: Run PHP syntax and frontend build**

```bash
php -l app/Services/IdpItemReviewWorkflow.php
php -l app/Http/Controllers/Employee/IdpController.php
php -l app/Http/Controllers/IdpApprovalController.php
php -l app/Http/Controllers/Employee/IdpActivityUpdateController.php
npm run build
git diff --check
```

Expected: all commands exit 0.

- [ ] **Step 4: Verify through mock SSO**

Use one employee with reviewers in slots 1, 2, and 3:

1. Submit competency A while competency B remains draft.
2. Confirm only reviewer 1 sees competency A.
3. Approve at step 1 and confirm only reviewer 2 sees it.
4. Reject at step 2 and confirm the employee sees the comment.
5. Resubmit and confirm the item restarts at reviewer 1 with submission version 2.
6. Approve through all configured reviewers.
7. Confirm the activity appears on the employee progress page only after final approval.

- [ ] **Step 5: Commit any verification-only fixes**

If verification required code changes:

```bash
git add <verified-files>
git commit -m "fix: แก้ regression ขั้นตอนอนุมัติ IDP"
```

Otherwise leave the existing task commits unchanged.
