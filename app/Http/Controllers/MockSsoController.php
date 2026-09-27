<?php

namespace App\Http\Controllers;

use App\Mail\AssessmentStatusUpdateMail;
use App\Mail\AssessmentSubmittedMail;
use App\Mail\ReminderAssessMail;
use App\Mail\RoleNotificationMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class MockSsoController extends Controller
{
    public function showLogin()
    {
        $users = User::with('role')
            ->orderBy('role_id')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (User $user) => sprintf(
                '%02d|%04d|%s',
                $this->mockSsoUserPriority($user),
                (int) $user->role_id,
                $user->name
            ))
            ->values()
            ->map(fn (User $user) => [
                'db_id' => $user->id,
                'name' => ($user->title ?? '') . $user->name,
                'role' => $this->roleKeyForUser($user),
                'workline' => $user->workline ?? '',
                'division' => $user->division ?? '',
                'position' => $user->position ?? '',
            ]);

        return view('mock-sso.login', ['mockUsers' => $users]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail((int) $data['user_id']);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function testNotification(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in([
                'assessment_submitted',
                'reminder_assess',
                'admin_new_user',
                'admin_incomplete_user',
                'admin_missing_expectation',
                'hr_unmapped_competency',
                'hr_remind_pending',
                'status_pending',
                'status_revision',
                'status_approved',
            ])],
        ]);

        $user = User::with('role')->findOrFail((int) $data['user_id']);
        $actionUrl = route('dashboard');
        $recipient = $this->testRecipientForUser($user);
        $groupName = $user->position ?: 'กลุ่มงานตัวอย่าง';

        $mailable = match ($data['type']) {
            'assessment_submitted' => new AssessmentSubmittedMail($user, 'จืบเก่ง', $actionUrl),
            'reminder_assess', 'hr_remind_pending' => new ReminderAssessMail($user, $actionUrl),
            'status_pending' => new AssessmentStatusUpdateMail($user, 'self_submitted', $actionUrl),
            'status_revision' => new AssessmentStatusUpdateMail($user, 'revision_required', $actionUrl),
            'status_approved' => new AssessmentStatusUpdateMail($user, 'dept_evaluated', $actionUrl),
            'admin_new_user' => new RoleNotificationMail(
                'มีผู้ใช้งานใหม่ในระบบ',
                'มีผู้ใช้งานใหม่',
                "{$user->name} ถูกเพิ่มเข้าสู่ระบบแล้ว กรุณาตรวจสอบข้อมูลผู้ใช้งานและสิทธิ์การใช้งานให้ถูกต้อง",
                'เปิดหน้าจัดการผู้ใช้งาน',
                $actionUrl,
            ),
            'admin_incomplete_user' => new RoleNotificationMail(
                'ผู้ใช้งานยังรอตรวจสอบข้อมูล',
                'ผู้ใช้งานยังติดสถานะตรวจสอบข้อมูล',
                "{$user->name} ยังมีข้อมูลโครงสร้างตำแหน่งหรือหน่วยงานไม่ครบ กรุณาตรวจสอบและปรับปรุงข้อมูลผู้ใช้งาน",
                'ตรวจสอบข้อมูลผู้ใช้งาน',
                $actionUrl,
            ),
            'admin_missing_expectation' => new RoleNotificationMail(
                'มีกลุ่มงานที่ยังไม่ได้กำหนดค่าความคาดหวัง',
                'กลุ่มงานยังไม่ได้กำหนดค่าความคาดหวัง',
                "กลุ่มงาน {$groupName} ยังไม่มีการกำหนดค่าความคาดหวัง กรุณาตรวจสอบการตั้งค่าก่อนเปิดรอบการประเมิน",
                'เปิดหน้าตั้งค่า',
                $actionUrl,
            ),
            'hr_unmapped_competency' => new RoleNotificationMail(
                'มีกลุ่มงานที่ยังไม่ได้กำหนดสมรรถนะ',
                'กลุ่มงานยังไม่ได้กำหนดสมรรถนะ',
                "กลุ่มงาน {$groupName} ยังไม่มีการกำหนดสมรรถนะ กรุณาตรวจสอบและผูกสมรรถนะให้ครบถ้วน",
                'เปิดหน้ากำหนดสมรรถนะ',
                $actionUrl,
            ),
        };

        Mail::to($recipient)->send($mailable);

        return back()->with('mail_status', "ส่งอีเมลทดสอบไปที่ {$recipient} แล้ว");
    }

    public function resetAssessmentFlow(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail((int) $data['user_id']);

        if (! $this->canResetAssessmentFlow($user)) {
            return back()->withErrors([
                'assessment_reset' => 'ปุ่มนี้ใช้ได้เฉพาะนายซีอิ๊วขาว เด็กสมบูรณ์เท่านั้น',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $assessmentIds = DB::table('assessments')
                ->where('user_id', $user->id)
                ->pluck('id');

            $gapIds = DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->pluck('id');

            $idpIds = DB::table('idps')
                ->where('user_id', $user->id)
                ->pluck('id');

            $itemIds = DB::table('idp_items')
                ->whereIn('idp_id', $idpIds)
                ->orWhereIn('competency_gap_id', $gapIds)
                ->pluck('id');

            $activityIds = DB::table('idp_activities')
                ->whereIn('idp_item_id', $itemIds)
                ->pluck('id');

            DB::table('idp_item_reviews')->whereIn('idp_item_id', $itemIds)->delete();
            DB::table('idp_activity_updates')->whereIn('activity_id', $activityIds)->delete();
            DB::table('idp_activities')->whereIn('idp_item_id', $itemIds)->delete();
            DB::table('idp_items')->whereIn('id', $itemIds)->delete();
            DB::table('idps')->whereIn('id', $idpIds)->delete();
            DB::table('assessment_indicator_results')->whereIn('assessment_id', $assessmentIds)->delete();
            DB::table('assessment_evidences')->whereIn('assessment_id', $assessmentIds)->delete();
            DB::table('competency_gaps')->whereIn('assessment_id', $assessmentIds)->delete();
            DB::table('scores')->whereIn('assessment_id', $assessmentIds)->delete();
            DB::table('assessments')->whereIn('id', $assessmentIds)->delete();
        });

        return back()->with('mail_status', "ล้างข้อมูลการประเมินของ {$user->name} เรียบร้อยแล้ว");
    }

    private function roleKeyForUser(User $user): string
    {
        if (Schema::hasColumn('users', 'role_key') && $user->role_key) {
            return $user->role_key;
        }

        return $user->role?->role_key ?? $user->role?->key ?? 'employee';
    }

    private function testRecipientForUser(User $user): string
    {
        return match ($this->roleKeyForUser($user)) {
            'supervisor' => 'krittheekachon.s@kkumail.com',
            'dept_head', 'division_head', 'academic_department_head', 'dean' => 'chin172755@gmail.com',
            default => 'krittheekachon.s@kkumail.com',
        };
    }

    private function mockSsoUserPriority(User $user): int
    {
        return match (true) {
            str_contains($user->name, 'ซีอิ๊วขาว') => 0,
            str_contains($user->name, 'อนุทิน') => 1,
            str_contains($user->name, 'จงเหลียวหลัง') => 2,
            default => 10,
        };
    }

    private function canResetAssessmentFlow(User $user): bool
    {
        return (int) $user->id === 36
            || (string) $user->sso === '61662126318'
            || $user->name === 'ซีอิ๊วขาว เด็กสมบูรณ์';
    }
}
