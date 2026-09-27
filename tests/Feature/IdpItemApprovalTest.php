<?php

namespace Tests\Feature;

use App\Mail\IdpStatusUpdateMail;
use App\Mail\IdpSubmittedMail;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdpItemApprovalTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_NOTIFICATION_RECIPIENT = 'krittheekachon.s@kkumail.com';

    public function test_schema_supports_sequential_idp_review_history(): void
    {
        $this->assertTrue(Schema::hasTable('idp_item_reviews'));
        $this->assertTrue(Schema::hasColumns('idp_items', [
            'submission_version',
            'current_review_step',
        ]));
    }

    public function test_assigned_supervisor_can_approve_one_submitted_competency_plan(): void
    {
        [$supervisor, $itemId] = $this->submittedItem();

        $this->actingAs($supervisor)
            ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_items', [
            'id' => $itemId,
            'status' => 'approved',
            'approved_by' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('idp_item_reviews', [
            'idp_item_id' => $itemId,
            'submission_version' => 1,
            'review_step' => 1,
            'reviewer_id' => $supervisor->id,
            'decision' => 'approved',
        ]);
    }

    public function test_unassigned_user_cannot_approve_competency_plan(): void
    {
        [, $itemId] = $this->submittedItem();
        $otherUser = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);

        $this->actingAs($otherUser)
            ->post(route('idp-items.approve'), ['idpItemId' => $itemId])
            ->assertSessionHasErrors('idpItemId');

        $this->assertDatabaseHas('idp_items', [
            'id' => $itemId,
            'status' => 'review_step_1',
        ]);
    }

    public function test_assigned_idp_reviewer_can_load_latest_activity_form_details(): void
    {
        [$supervisor, $itemId] = $this->submittedItem();
        $activityId = DB::table('idp_activities')->insertGetId([
            'idp_item_id' => $itemId,
            'activity_name' => '03 · การสอนงาน (Coaching)',
            'form_code' => 'form_5_coaching',
            'form_details' => json_encode([
                'detail' => ['coachType' => 'ผู้บังคับบัญชา'],
                'planRows' => [['topic' => 'ฝึกการให้บริการ', 'sessionCount' => 4]],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($supervisor)
            ->getJson(route('idp-activities.review-detail', ['activity' => $activityId]))
            ->assertOk()
            ->assertJsonPath('formCode', 'form_5_coaching')
            ->assertJsonPath('formDetails.detail.coachType', 'ผู้บังคับบัญชา')
            ->assertJsonPath('formDetails.planRows.0.topic', 'ฝึกการให้บริการ');
    }

    public function test_unassigned_user_cannot_load_activity_form_details(): void
    {
        [, $itemId] = $this->submittedItem();
        $activityId = DB::table('idp_activities')->insertGetId([
            'idp_item_id' => $itemId,
            'activity_name' => 'กิจกรรมทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherUser = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);

        $this->actingAs($otherUser)
            ->getJson(route('idp-activities.review-detail', ['activity' => $activityId]))
            ->assertForbidden();
    }

    public function test_assigned_supervisor_must_include_comment_when_rejecting(): void
    {
        [$supervisor, $itemId] = $this->submittedItem();

        $this->actingAs($supervisor)
            ->post(route('idp-items.reject'), [
                'idpItemId' => $itemId,
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');
    }

    public function test_approval_advances_to_next_configured_reviewer(): void
    {
        Mail::fake();

        $reviewer1 = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $reviewer3 = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        [, $itemId] = $this->submittedItem($reviewer1, [
            'supervisor_id_1' => $reviewer1->id,
            'supervisor_id_3' => $reviewer3->id,
        ]);
        $employee = $this->employeeForItem($itemId);

        $this->actingAs($reviewer1)
            ->post(route('idp-items.approve'), [
                'idpItemId' => $itemId,
                'comment' => 'ผ่านลำดับแรก',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_items', [
            'id' => $itemId,
            'status' => 'review_step_3',
            'current_review_step' => 3,
        ]);
        Mail::assertSent(IdpStatusUpdateMail::class, function (IdpStatusUpdateMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && $mail->status === 'review_step_3';
        });
        Mail::assertSent(IdpSubmittedMail::class, function (IdpSubmittedMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee);
        });
    }

    public function test_rejection_records_history_and_returns_item_to_employee(): void
    {
        Mail::fake();

        [$supervisor, $itemId] = $this->submittedItem();
        $employee = $this->employeeForItem($itemId);
        $supervisor->forceFill([
            'title' => 'นาย',
            'name' => 'หัวหน้าทดสอบ',
        ])->save();

        $this->actingAs($supervisor)
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
            'reviewer_id' => $supervisor->id,
            'decision' => 'rejected',
        ]);
        Mail::assertSent(IdpStatusUpdateMail::class, function (IdpStatusUpdateMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && $mail->status === 'revision_required'
                && $mail->rejectComment === 'แก้ช่วงเวลาดำเนินการ';
        });

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserIdp.items.0.status', 'revision_required')
                ->where('currentUserIdp.items.0.rejectComment', 'แก้ช่วงเวลาดำเนินการ')
                ->where('currentUserIdp.items.0.rejectReviewerName', 'นายหัวหน้าทดสอบ'));
    }

    public function test_migration_backfills_submitted_item_to_first_configured_reviewer_slot(): void
    {
        $this->withIsolatedMigrationDatabase(function (): void {
            $itemId = $this->createMigrationTestItem('submitted', [
                'supervisor_id_2' => 20,
                'supervisor_id_3' => 30,
            ]);

            $this->migration()->up();

            $this->assertDatabaseHas('idp_items', [
                'id' => $itemId,
                'status' => 'review_step_2',
                'submission_version' => 1,
                'current_review_step' => 2,
                'reject_comment' => null,
            ], 'idp_migration_test');
        });
    }

    public function test_migration_backfills_approved_item_as_version_one_without_current_step(): void
    {
        $this->withIsolatedMigrationDatabase(function (): void {
            $itemId = $this->createMigrationTestItem('approved', [
                'supervisor_id_1' => 10,
            ]);

            $this->migration()->up();

            $this->assertDatabaseHas('idp_items', [
                'id' => $itemId,
                'status' => 'approved',
                'submission_version' => 1,
                'current_review_step' => null,
            ], 'idp_migration_test');
        });
    }

    public function test_migration_returns_submitted_item_without_reviewer_for_revision(): void
    {
        $this->withIsolatedMigrationDatabase(function (): void {
            $itemId = $this->createMigrationTestItem('submitted');

            $this->migration()->up();

            $this->assertDatabaseHas('idp_items', [
                'id' => $itemId,
                'status' => 'revision_required',
                'submission_version' => 1,
                'current_review_step' => null,
                'reject_comment' => 'ยังไม่ได้กำหนดผู้อนุมัติแผน IDP',
            ], 'idp_migration_test');
        });
    }

    public function test_migration_rollback_restores_only_migration_generated_fallbacks(): void
    {
        $this->withIsolatedMigrationDatabase(function (): void {
            $reviewItemId = $this->createMigrationTestItem('submitted', [
                'supervisor_id_3' => 30,
            ]);
            $fallbackItemId = $this->createMigrationTestItem('submitted');
            $realRevisionItemId = $this->createMigrationTestItem(
                'revision_required',
                [],
                'กรุณาปรับปรุงเป้าหมาย'
            );

            $migration = $this->migration();
            $migration->up();
            $migration->down();

            $this->assertDatabaseHas('idp_items', [
                'id' => $reviewItemId,
                'status' => 'submitted',
            ], 'idp_migration_test');
            $this->assertDatabaseHas('idp_items', [
                'id' => $fallbackItemId,
                'status' => 'submitted',
                'reject_comment' => null,
            ], 'idp_migration_test');
            $this->assertDatabaseHas('idp_items', [
                'id' => $realRevisionItemId,
                'status' => 'revision_required',
                'reject_comment' => 'กรุณาปรับปรุงเป้าหมาย',
            ], 'idp_migration_test');
            $this->assertFalse(Schema::hasTable('idp_item_reviews'));
            $this->assertFalse(Schema::hasColumn('idp_items', 'submission_version'));
            $this->assertFalse(Schema::hasColumn('idp_items', 'current_review_step'));
        });
    }

    private function submittedItem(?User $supervisor = null, array $ownerOverrides = []): array
    {
        $supervisor ??= User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $reviewerIds = [
            1 => (int) ($ownerOverrides['supervisor_id_1'] ?? $supervisor->id),
            2 => (int) ($ownerOverrides['supervisor_id_2'] ?? 0),
            3 => (int) ($ownerOverrides['supervisor_id_3'] ?? 0),
        ];
        foreach ($reviewerIds as $step => $reviewerId) {
            if ($reviewerId <= 0) {
                continue;
            }

            DB::table('user_reviewer_steps')->insert([
                'user_id' => $employee->id,
                'reviewer_id' => $reviewerId,
                'step_order' => $step,
                'chain_type' => 'idp',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id,
            'year' => 2569,
            'status' => 'partially_submitted',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId,
            'goal' => 'พัฒนาสมรรถนะ',
            'success_criteria' => 'ผ่านตามเกณฑ์',
            'status' => 'review_step_1',
            'submission_version' => 1,
            'current_review_step' => 1,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$supervisor, $itemId];
    }

    private function employeeForItem(int $itemId): User
    {
        return User::query()->findOrFail((int) DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->where('idp_items.id', $itemId)
            ->value('idps.user_id'));
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }

    private function createMigrationTestItem(
        string $status,
        array $supervisors = [],
        ?string $rejectComment = null
    ): int {
        static $nextUserId = 100;

        $userId = $nextUserId++;

        DB::table('users')->insert([
            'id' => $userId,
            'supervisor_id_1' => $supervisors['supervisor_id_1'] ?? null,
            'supervisor_id_2' => $supervisors['supervisor_id_2'] ?? null,
            'supervisor_id_3' => $supervisors['supervisor_id_3'] ?? null,
        ]);

        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $userId,
        ]);

        return DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId,
            'status' => $status,
            'reject_comment' => $rejectComment,
        ]);
    }

    private function withIsolatedMigrationDatabase(callable $callback): void
    {
        $originalConnection = DB::getDefaultConnection();
        $originalConfiguredConnection = config('database.default');

        config()->set('database.connections.idp_migration_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', 'idp_migration_test');

        DB::purge('idp_migration_test');
        DB::setDefaultConnection('idp_migration_test');

        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('supervisor_id_1')->nullable();
                $table->foreignId('supervisor_id_2')->nullable();
                $table->foreignId('supervisor_id_3')->nullable();
            });
            Schema::create('idps', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id');
            });
            Schema::create('idp_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('idp_id');
                $table->string('status')->default('draft');
                $table->text('reject_comment')->nullable();
            });

            $callback();
        } finally {
            DB::purge('idp_migration_test');
            config()->set('database.default', $originalConfiguredConnection);
            DB::setDefaultConnection($originalConnection);
        }
    }

    private function migration(): Migration
    {
        return require dirname(__DIR__, 2)
            .'/database/migrations/2026_06_21_000000_create_idp_item_reviews_table.php';
    }
}
