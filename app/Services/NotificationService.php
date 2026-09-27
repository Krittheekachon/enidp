<?php

namespace App\Services;

use App\Mail\AssessmentStatusUpdateMail;
use App\Mail\AssessmentSubmittedMail;
use App\Mail\FcTopicSelectionStatusUpdateMail;
use App\Mail\FcTopicSelectionSubmittedMail;
use App\Mail\IdpProgressApprovedMail;
use App\Mail\IdpProgressReturnedMail;
use App\Mail\IdpProgressSubmittedMail;
use App\Mail\IdpStatusUpdateMail;
use App\Mail\IdpSubmittedMail;
use App\Mail\ReminderAssessMail;
use App\Mail\RoleNotificationMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationService
{
    private const DEFAULT_TEST_RECIPIENT = 'krittheekachon.s@kkumail.com';

    private const REVIEWER_1_RECIPIENT = 'krittheekachon.s@kkumail.com';

    private const REVIEWER_2_3_RECIPIENT = 'krittheekachon.s@kkumail.com';

    private const EMPLOYEE_REVISION_RECIPIENT = 'krittheekachon.s@kkumail.com';

    public function __construct(
        private NotificationDigestService $digest,
        private ReviewerChainResolver $reviewerChainResolver,
        private NotificationRecipientResolver $recipientResolver,
    ) {}

    public function notifyFirstReviewerOnSubmit(User $employee, string $competencyName): void
    {
        $reviewer = $this->reviewerForAssessmentStep($employee, 1);

        $this->sendToUser(
            $reviewer,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
            $this->recipientForReviewer($reviewer),
        );
    }

    public function notifyNextReviewerForAssessment(User $employee, string $competencyName, string $pendingStatus): void
    {
        $reviewer = $this->reviewerForAssessmentStatus($employee, $pendingStatus);

        $this->sendToUser(
            $reviewer,
            new AssessmentSubmittedMail($employee, $competencyName, $this->dashboardUrl()),
            $this->recipientForReviewer($reviewer),
        );
    }

    private function reviewerForAssessmentStatus(User $employee, string $pendingStatus): ?User
    {
        $step = match (true) {
            $pendingStatus === 'self_submitted' => 1,
            $pendingStatus === 'unit_evaluated' => 2,
            $pendingStatus === 'dept_evaluated' => 3,
            (bool) preg_match('/^review_step_(\d+)$/', $pendingStatus, $matches) => (int) $matches[1],
            default => null,
        };

        return $step ? $this->reviewerForAssessmentStep($employee, $step) : null;
    }

    private function reviewerForAssessmentStep(User $employee, int $step): ?User
    {
        $reviewerId = collect($this->reviewerChainResolver->stepsForUser($employee))
            ->first(fn (array $item): bool => (int) $item['step'] === $step)['reviewer_id'] ?? null;

        return $reviewerId ? User::find((int) $reviewerId) : null;
    }

    private function reviewerForIdpStep(User $employee, int $step): ?User
    {
        $reviewerId = collect($this->reviewerChainResolver->stepsForUser($employee, 'idp'))
            ->first(fn (array $item): bool => (int) $item['step'] === $step)['reviewer_id'] ?? null;

        return $reviewerId ? User::find((int) $reviewerId) : null;
    }

    private function idpItemNotificationContext(int $idpItemId): ?object
    {
        return DB::table('idp_items')
            ->leftJoin('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->leftJoin('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->where('idp_items.id', $idpItemId)
            ->select(
                'idp_items.id',
                'idp_items.current_review_step',
                'idp_items.status',
                'competencies.name as competency_name',
            )
            ->first();
    }

    private function fcTopicSelectionNotificationContext(int $selectionId): ?object
    {
        $selection = DB::table('fc_topic_selections')
            ->where('id', $selectionId)
            ->first(['id', 'submitted_to', 'status']);

        if (! $selection) {
            return null;
        }

        $selection->topic_names = DB::table('fc_topic_selection_items')
            ->join('competencies', 'fc_topic_selection_items.competency_id', '=', 'competencies.id')
            ->where('fc_topic_selection_items.fc_topic_selection_id', $selectionId)
            ->orderBy('competencies.code')
            ->pluck('competencies.name')
            ->filter()
            ->values()
            ->all();

        return $selection;
    }

    public function notifyAdminIncompleteUser(User $user): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $this->digest->queueIncompleteUser($user);
    }

    public function notifyAdminNewUser(User $user): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $this->digest->queueIncompleteUser($user);

        if ($this->hasUnmappedPositionCompetency($user)) {
            $this->digest->queueUserWithUnmappedPosition($user);
        }

        return;

        $this->sendToUsers(
            $this->usersWithRole('admin')->get(),
            fn () => new RoleNotificationMail(
                'มีผู้ใช้งานใหม่ในระบบ',
                'มีผู้ใช้งานใหม่',
                "{$user->name} ถูกเพิ่มเข้าสู่ระบบแล้ว กรุณาตรวจสอบข้อมูลผู้ใช้งานและสิทธิ์การใช้งานให้ถูกต้อง",
                'เปิดหน้าจัดการผู้ใช้งาน',
                $this->dashboardUrl(),
            ),
        );
    }

    public function notifyAdminMissingExpectation(string $groupName): void
    {
        $this->sendToUsers(
            $this->usersWithRole('admin')->get(),
            fn () => new RoleNotificationMail(
                'มีกลุ่มงานที่ยังไม่ได้กำหนดค่าความคาดหวัง',
                'กลุ่มงานยังไม่ได้กำหนดค่าความคาดหวัง',
                "กลุ่มงาน {$groupName} ยังไม่มีการกำหนดค่าความคาดหวัง กรุณาตรวจสอบการตั้งค่าก่อนเปิดรอบการประเมิน",
                'เปิดหน้าตั้งค่า',
                $this->dashboardUrl(),
            ),
        );
    }

    public function notifyHrUnmappedPosition(string $positionName): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $this->digest->queueUnmappedPosition($positionName);
    }

    public function notifyHrUserWithUnmappedPosition(User $user): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $this->digest->queueUserWithUnmappedPosition($user);
    }

    public function remindPendingEmployees(): void
    {
        $employees = $this->usersWithAnyRole(['employee', 'hr', 'supervisor', 'dept_head', 'division_head', 'academic_department_head'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('assessments')
                    ->whereColumn('assessments.user_id', 'users.id')
                    ->where('assessments.status', '!=', 'draft');
            })
            ->get();

        $this->sendToUsers(
            $employees,
            fn (User $employee) => new ReminderAssessMail($employee, $this->dashboardUrl()),
        );
    }

    public function notifyEmployeeStatusUpdate(User $employee, string $status, string $rejectComment = ''): void
    {
        $this->sendToUser(
            $employee,
            new AssessmentStatusUpdateMail($employee, $status, $this->dashboardUrl(), $rejectComment),
            $this->recipientForEmployeeStatus($status),
        );
    }

    public function notifyFcTopicSelectionSubmitted(User $employee, int $selectionId): void
    {
        $context = $this->fcTopicSelectionNotificationContext($selectionId);
        $reviewer = $context?->submitted_to ? User::find((int) $context->submitted_to) : null;

        $this->sendToUser(
            $reviewer,
            new FcTopicSelectionSubmittedMail(
                $employee,
                $context?->topic_names ?? [],
                $this->dashboardUrl(),
            ),
            $this->recipientForReviewer($reviewer),
        );
    }

    public function notifyEmployeeFcTopicSelectionStatusUpdate(User $employee, int $selectionId, string $status, string $comment = ''): void
    {
        $context = $this->fcTopicSelectionNotificationContext($selectionId);

        $this->sendToUser(
            $employee,
            new FcTopicSelectionStatusUpdateMail(
                $employee,
                $context?->topic_names ?? [],
                $status,
                $this->dashboardUrl(),
                $comment,
            ),
            $this->recipientForEmployeeStatus($status),
        );
    }

    public function notifyIdpReviewerForItem(User $employee, int $idpItemId, ?int $reviewStep = null): void
    {
        $item = $this->idpItemNotificationContext($idpItemId);
        $step = $reviewStep ?? (int) ($item?->current_review_step ?? 0);

        if (! $item || $step < 1) {
            return;
        }

        $reviewer = $this->reviewerForIdpStep($employee, $step);

        $this->sendToUser(
            $reviewer,
            new IdpSubmittedMail($employee, $item->competency_name ?: 'แผน IDP', $this->dashboardUrl()),
            $this->recipientForReviewer($reviewer),
        );
    }

    public function notifyIdpReviewerOfProgressSubmission(User $employee, int $idpItemId): void
    {
        $item = DB::table('idp_items')
            ->leftJoin('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->leftJoin('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->where('idp_items.id', $idpItemId)
            ->select(
                'competencies.name as competency_name',
            )
            ->first();

        if (! $item) {
            return;
        }

        $reviewer = $this->reviewerForIdpStep($employee, 1);
        $this->sendToUser(
            $reviewer,
            new IdpProgressSubmittedMail(
                $employee,
                $item->competency_name ?: 'แผน IDP',
                $this->dashboardUrl(),
            ),
            $this->recipientForReviewer($reviewer),
        );
    }

    public function notifyEmployeeIdpProgressReturned(User $employee, int $idpItemId, string $comment): void
    {
        $item = $this->idpItemNotificationContext($idpItemId);

        $this->sendToUser(
            $employee,
            new IdpProgressReturnedMail(
                $employee,
                $item?->competency_name ?: 'แผน IDP',
                $comment,
                $this->dashboardUrl(),
            ),
            $this->recipientForEmployeeStatus('revision_required'),
        );
    }

    public function notifyEmployeeIdpProgressApproved(
        User $employee,
        int $idpItemId,
        string $achievementStatus,
        string $comment = '',
    ): void {
        $item = $this->idpItemNotificationContext($idpItemId);

        $this->sendToUser(
            $employee,
            new IdpProgressApprovedMail(
                $employee,
                $item?->competency_name ?: 'แผน IDP',
                $achievementStatus,
                $comment,
                $this->dashboardUrl(),
            ),
            $this->recipientForEmployeeStatus('approved'),
        );
    }

    public function notifyEmployeeIdpStatusUpdate(User $employee, int $idpItemId, string $status, string $rejectComment = ''): void
    {
        $item = $this->idpItemNotificationContext($idpItemId);

        $this->sendToUser(
            $employee,
            new IdpStatusUpdateMail(
                $employee,
                $item?->competency_name ?: 'แผน IDP',
                $status,
                $this->dashboardUrl(),
                $rejectComment,
            ),
            $this->recipientForEmployeeStatus($status),
        );
    }

    public function isNotificationEnabled(): bool
    {
        return ! app()->environment('local') || Cache::get('dev_notifications_enabled', true) !== false;
    }

    private function dashboardUrl(): string
    {
        return route('dashboard');
    }

    private function usersWithRole(string $roleKey): Builder
    {
        return User::query()->where(function (Builder $query) use ($roleKey): void {
            if (Schema::hasColumn('users', 'role_key')) {
                $query->where('role_key', $roleKey);
            }

            $query->orWhereHas('role', function (Builder $roleQuery) use ($roleKey): void {
                $roleQuery->where(function (Builder $columnQuery) use ($roleKey): void {
                    if (Schema::hasColumn('roles', 'key')) {
                        $columnQuery->orWhere('key', $roleKey);
                    }

                    if (Schema::hasColumn('roles', 'role_key')) {
                        $columnQuery->orWhere('role_key', $roleKey);
                    }
                });
            });
        });
    }

    private function usersWithAnyRole(array $roleKeys): Builder
    {
        return User::query()->where(function (Builder $query) use ($roleKeys): void {
            if (Schema::hasColumn('users', 'role_key')) {
                $query->whereIn('role_key', $roleKeys);
            }

            $query->orWhereHas('role', function (Builder $roleQuery) use ($roleKeys): void {
                $roleQuery->where(function (Builder $columnQuery) use ($roleKeys): void {
                    if (Schema::hasColumn('roles', 'key')) {
                        $columnQuery->orWhereIn('key', $roleKeys);
                    }

                    if (Schema::hasColumn('roles', 'role_key')) {
                        $columnQuery->orWhereIn('role_key', $roleKeys);
                    }
                });
            });
        });
    }

    private function hasUnmappedPositionCompetency(User $user): bool
    {
        if (! Schema::hasTable('position_competencies')) {
            return false;
        }

        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        if (! $roundId) {
            return false;
        }

        if (Schema::hasColumn('users', 'position_id') && $user->position_id) {
            return ! DB::table('position_competencies')
                ->where('assessment_round_id', $roundId)
                ->where('position_id', $user->position_id)
                ->exists();
        }

        if (! $user->position || ! Schema::hasTable('positions')) {
            return false;
        }

        $positionId = DB::table('positions')
            ->where('name', $user->position)
            ->value('id');

        return $positionId
            ? ! DB::table('position_competencies')
                ->where('assessment_round_id', $roundId)
                ->where('position_id', $positionId)
                ->exists()
            : false;
    }

    private function sendToUsers(Collection $users, callable $mailableFactory): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        $users->each(function (User $user) use ($mailableFactory): void {
            $this->sendToUser($user, $mailableFactory($user));
        });
    }

    private function sendToUser(?User $user, $mailable, ?string $recipient = null): void
    {
        if (! $this->isNotificationEnabled()) {
            return;
        }

        if (! $user) {
            Log::info('Skipped notification email because recipient user is missing.', [
                'mail' => $mailable::class,
            ]);

            return;
        }

        $resolvedRecipient = $this->recipientResolver->recipientsFor($user);
        if (! $resolvedRecipient) {
            Log::info('Skipped notification email because recipient email is missing.', [
                'user_id' => $user->id,
                'intended_recipient' => $user->email,
                'mail' => $mailable::class,
            ]);

            return;
        }

        try {
            Mail::to($resolvedRecipient)->send($mailable);
            Log::info('Sent notification email.', [
                'user_id' => $user->id,
                'recipient' => $this->recipientResolver->recipientLabelFor($user),
                'intended_recipient' => $user->email,
                'mail' => $mailable::class,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Unable to send notification email.', [
                'user_id' => $user->id,
                'recipient' => $this->recipientResolver->recipientLabelFor($user),
                'intended_recipient' => $user->email,
                'mail' => $mailable::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function recipientForReviewer(?User $reviewer): string
    {
        $roleKey = $this->normalizeRoleKey($reviewer?->role?->key ?? $reviewer?->role_key ?? '');

        return match ($roleKey) {
            'supervisor' => self::REVIEWER_1_RECIPIENT,
            'dept_head', 'division_head', 'academic_department_head', 'dean' => self::REVIEWER_2_3_RECIPIENT,
            default => self::DEFAULT_TEST_RECIPIENT,
        };
    }

    private function recipientForEmployeeStatus(string $status): string
    {
        return $status === 'revision_required'
            ? self::EMPLOYEE_REVISION_RECIPIENT
            : self::DEFAULT_TEST_RECIPIENT;
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }
}
