<?php

namespace Tests\Feature;

use App\Mail\AdminDailyDigestMail;
use App\Mail\DailyIncompleteUserDigestMail;
use App\Mail\DailyMissingExpectationDigestMail;
use App\Mail\HrDailyDigestMail;
use App\Mail\PendingAssessmentDigestMail;
use App\Mail\UnmappedPositionDigestMail;
use App\Mail\UnmappedPositionUserDigestMail;
use App\Models\User;
use App\Services\NotificationDigestService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationDigestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_switch_stops_immediate_and_digest_notifications(): void
    {
        Mail::fake();
        Cache::flush();
        config(['mail.notifications_enabled' => false]);

        $user = User::factory()->create();
        $digest = app(NotificationDigestService::class);

        $digest->queueIncompleteUser($user);
        $digest->sendHourlyDigest();

        $this->assertFalse(app(NotificationService::class)->isNotificationEnabled());
        $this->assertFalse(Cache::has('digest_new_users'));
        Mail::assertNothingSent();
    }

    public function test_daily_digest_sends_one_combined_email_per_role(): void
    {
        Mail::fake();
        Cache::flush();

        DB::table('assessment_rounds')->insert([
            'name' => 'Current Round',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create([
            'email' => 'admin@example.test',
            'role_id' => $this->roleId('admin'),
            'role_key' => 'admin',
            'is_active' => true,
        ]);
        $hr = User::factory()->create([
            'email' => 'hr@example.test',
            'role_id' => $this->roleId('hr'),
            'role_key' => 'hr',
            'is_active' => true,
        ]);
        $pendingEmployee = User::factory()->create([
            'email' => 'pending@example.test',
            'role_id' => $this->roleId('employee'),
            'role_key' => 'employee',
            'is_active' => true,
            'position_id' => null,
        ]);
        $unmappedPositionUser = User::factory()->create([
            'email' => 'unmapped@example.test',
            'role_id' => $this->roleId('employee'),
            'role_key' => 'employee',
            'is_active' => true,
            'position_id' => $this->unmappedPositionId(),
            'position' => 'Unmapped Position',
        ]);

        DB::table('levels')->insert([
            'name' => 'Missing Expected Level',
            'expected_level' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(NotificationDigestService::class)->queueUnmappedPosition('Unmapped Position');
        app(NotificationDigestService::class)->sendDailyDigest();

        Mail::assertSent(AdminDailyDigestMail::class, 1);
        Mail::assertSent(HrDailyDigestMail::class, 1);

        Mail::assertSent(AdminDailyDigestMail::class, function (AdminDailyDigestMail $mail) use ($pendingEmployee) {
            return collect($mail->sections['incompleteUsers'] ?? [])->contains('id', $pendingEmployee->id)
                && count($mail->sections['missingExpectations'] ?? []) === 1;
        });
        Mail::assertSent(HrDailyDigestMail::class, function (HrDailyDigestMail $mail) use ($unmappedPositionUser) {
            return collect($mail->sections['pendingAssessmentUsers'] ?? [])->contains('id', $unmappedPositionUser->id)
                && collect($mail->sections['unmappedPositionUsers'] ?? [])->contains('id', $unmappedPositionUser->id)
                && in_array('Unmapped Position', $mail->sections['unmappedPositions'] ?? [], true);
        });

        Mail::assertNotSent(DailyIncompleteUserDigestMail::class);
        Mail::assertNotSent(DailyMissingExpectationDigestMail::class);
        Mail::assertNotSent(PendingAssessmentDigestMail::class);
        Mail::assertNotSent(UnmappedPositionDigestMail::class);
        Mail::assertNotSent(UnmappedPositionUserDigestMail::class);

        $this->assertNotNull($admin->id);
        $this->assertNotNull($hr->id);
    }

    public function test_daily_digest_does_not_send_email_when_no_sections_have_content(): void
    {
        Mail::fake();
        Cache::flush();
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'Current Round',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $mappedPositionId = $this->mappedPositionId();

        User::factory()->create([
            'email' => 'admin@example.test',
            'role_id' => $this->roleId('admin'),
            'role_key' => 'admin',
            'is_active' => true,
            'position_id' => $mappedPositionId,
        ]);
        $hr = User::factory()->create([
            'email' => 'hr@example.test',
            'role_id' => $this->roleId('hr'),
            'role_key' => 'hr',
            'is_active' => true,
            'position_id' => $mappedPositionId,
        ]);
        $assessedEmployee = User::factory()->create([
            'email' => 'assessed@example.test',
            'role_id' => $this->roleId('employee'),
            'role_key' => 'employee',
            'is_active' => true,
            'position_id' => $mappedPositionId,
        ]);

        DB::table('assessments')->insert([
            [
                'assessment_round_id' => $roundId,
                'user_id' => $hr->id,
                'status' => 'self_submitted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'assessment_round_id' => $roundId,
                'user_id' => $assessedEmployee->id,
                'status' => 'self_submitted',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(NotificationDigestService::class)->sendDailyDigest();

        Mail::assertNothingSent();
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }

    private function unmappedPositionId(): int
    {
        return $this->positionId('Unmapped Position');
    }

    private function mappedPositionId(): int
    {
        $positionId = $this->positionId('Mapped Position');
        $competencyTypeId = DB::table('competency_types')->insertGetId([
            'code' => 'TEST',
            'full_name' => 'Test Competency Type',
            'description' => 'Test competency type',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $competencyTypeId,
            'code' => 'TEST-COMP-'.uniqid(),
            'name' => 'Test Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('position_competencies')->insertOrIgnore([
            'assessment_round_id' => DB::table('assessment_rounds')
                ->where('is_active', true)
                ->value('id'),
            'position_id' => $positionId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $positionId;
    }

    private function positionId(string $name): int
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'Test Workline '.$name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'Test Job Family '.$name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
