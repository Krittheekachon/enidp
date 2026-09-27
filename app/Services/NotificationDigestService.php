<?php

namespace App\Services;

use App\Mail\AdminDailyDigestMail;
use App\Mail\HrDailyDigestMail;
use App\Mail\NewUserDigestMail;
use App\Mail\UnmappedPositionDigestMail;
use App\Mail\UnmappedPositionUserDigestMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationDigestService
{
    private const NEW_USERS_KEY = 'digest_new_users';
    private const UNMAPPED_POSITIONS_KEY = 'digest_unmapped_positions';
    private const UNMAPPED_POSITION_USERS_KEY = 'digest_unmapped_position_users';
    private const DAILY_SENT_KEY = 'digest_daily_sent_on';

    public function __construct(private NotificationRecipientResolver $recipientResolver)
    {
    }

    public function queueIncompleteUser(User $user): void
    {
        $users = Cache::get(self::NEW_USERS_KEY, []);
        $users[$user->id] = $this->userSummary($user);

        Cache::put(self::NEW_USERS_KEY, $users);

        Log::info('Queued user for notification digest.', [
            'user_id' => $user->id,
            'digest_count' => count($users),
        ]);
    }

    public function queueUnmappedPosition(string $name): void
    {
        $positions = Cache::get(self::UNMAPPED_POSITIONS_KEY, []);
        $positions[$name] = $name;

        Cache::put(self::UNMAPPED_POSITIONS_KEY, $positions);

        Log::info('Queued unmapped position for notification digest.', [
            'position' => $name,
            'digest_count' => count($positions),
        ]);
    }

    public function queueUserWithUnmappedPosition(User $user): void
    {
        $users = Cache::get(self::UNMAPPED_POSITION_USERS_KEY, []);
        $users[$user->id] = $this->userSummary($user);

        Cache::put(self::UNMAPPED_POSITION_USERS_KEY, $users);

        Log::info('Queued user with unmapped position competency for HR digest.', [
            'user_id' => $user->id,
            'position' => $users[$user->id]['position'] ?? null,
            'digest_count' => count($users),
        ]);
    }

    public function sendHourlyDigest(): void
    {
        if (! $this->isNotificationEnabled()) {
            Log::info('Skipped hourly notification digest because notifications are disabled.');

            return;
        }

        $users = array_values(Cache::pull(self::NEW_USERS_KEY, []));
        $positions = array_values(Cache::get(self::UNMAPPED_POSITIONS_KEY, []));
        $unmappedPositionUsers = array_values(Cache::pull(self::UNMAPPED_POSITION_USERS_KEY, []));
        $admins = $this->usersWithRole('admin')->get();
        $hrs = $this->usersWithRole('hr')->get();

        Log::info('Running hourly notification digest.', [
            'new_users' => count($users),
            'unmapped_positions' => count($positions),
            'unmapped_position_users' => count($unmappedPositionUsers),
            'admin_recipients' => $admins->whereNotNull('email')->count(),
            'hr_recipients' => $hrs->whereNotNull('email')->count(),
        ]);

        if ($users !== []) {
            $this->sendToUsers($admins, new NewUserDigestMail($users));
        }

        if ($unmappedPositionUsers !== []) {
            $this->sendToUsers($hrs, new UnmappedPositionUserDigestMail($unmappedPositionUsers, 'hourly'));
        }

        $this->sendDailyDigestIfMissed();
    }

    public function sendDailyDigest(): void
    {
        if (! $this->isNotificationEnabled()) {
            Log::info('Skipped daily notification digest because notifications are disabled.');

            return;
        }

        $incompleteUsers = $this->incompleteStructureUsers()
            ->get()
            ->map(fn (User $user) => $this->userSummary($user))
            ->all();
        $admins = $this->usersWithRole('admin')->get();
        $hrs = $this->usersWithRole('hr')->get();
        $positions = array_values(Cache::pull(self::UNMAPPED_POSITIONS_KEY, []));
        $missingExpectations = $this->missingExpectationLevels();
        $unmappedPositionUsers = $this->usersWithUnmappedPositionCompetencies()
            ->get()
            ->map(fn (User $user) => $this->userSummary($user))
            ->all();

        $pendingAssessmentUsers = $this->staffUsers()
            ->whereDoesntHave('assessments', fn (Builder $query) => $query->where('status', '!=', 'draft'))
            ->get()
            ->map(fn (User $user) => $this->userSummary($user))
            ->all();

        $adminSections = [
            'incompleteUsers' => $incompleteUsers,
            'missingExpectations' => $missingExpectations,
        ];
        $hrSections = [
            'pendingAssessmentUsers' => $pendingAssessmentUsers,
            'unmappedPositionUsers' => $unmappedPositionUsers,
            'unmappedPositions' => $positions,
        ];

        if ($this->hasDigestContent($adminSections)) {
            $this->sendToUsers($admins, new AdminDailyDigestMail($adminSections));
        }

        if ($this->hasDigestContent($hrSections)) {
            $this->sendToUsers($hrs, new HrDailyDigestMail($hrSections));
        }

        Log::info('Ran daily notification digest.', [
            'incomplete_users' => count($incompleteUsers),
            'missing_expectation_levels' => count($missingExpectations),
            'pending_assessment_users' => count($pendingAssessmentUsers),
            'unmapped_position_users' => count($unmappedPositionUsers),
            'unmapped_positions' => count($positions),
            'admin_recipients' => $admins->whereNotNull('email')->count(),
            'hr_recipients' => $hrs->whereNotNull('email')->count(),
        ]);

        Cache::put(self::DAILY_SENT_KEY, now('Asia/Bangkok')->toDateString(), now('Asia/Bangkok')->endOfDay());
    }

    private function sendDailyDigestIfMissed(): void
    {
        $now = now('Asia/Bangkok');
        $today = $now->toDateString();

        if ($now->lt($now->copy()->setTime(9, 0))) {
            return;
        }

        if (Cache::get(self::DAILY_SENT_KEY) === $today) {
            return;
        }

        Log::info('Running missed daily notification digest from hourly fallback.', [
            'date' => $today,
        ]);

        $this->sendDailyDigest();
    }

    private function incompleteStructureUsers(): Builder
    {
        $hasDivision = Schema::hasColumn('users', 'division_id');
        $hasJobFamily = Schema::hasColumn('users', 'job_family_id');
        $hasPosition = Schema::hasColumn('users', 'position_id');

        return User::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($hasDivision, $hasJobFamily, $hasPosition): void {
                if (! $hasDivision && ! $hasJobFamily && ! $hasPosition) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                if ($hasDivision) {
                    $query->orWhereNull('division_id');
                }

                if ($hasJobFamily) {
                    $query->orWhereNull('job_family_id');
                } elseif ($hasPosition) {
                    $query->orWhereNull('position_id');
                }
            });
    }

    private function missingExpectationLevels(): array
    {
        return DB::table('levels')
            ->leftJoin('worklines', 'levels.workline_id', '=', 'worklines.id')
            ->leftJoin('job_families', 'levels.job_family_id', '=', 'job_families.id')
            ->whereNull('levels.expected_level')
            ->orderBy('worklines.name')
            ->orderBy('job_families.name')
            ->orderBy('levels.name')
            ->select([
                'levels.name as level',
                'worklines.name as workline',
                'job_families.name as job_family',
            ])
            ->get()
            ->map(fn ($level) => [
                'level' => $level->level,
                'workline' => $level->workline,
                'job_family' => $level->job_family,
            ])
            ->all();
    }

    private function staffUsers(): Builder
    {
        return $this->usersWithAnyRole(['employee', 'hr', 'supervisor', 'dept_head', 'division_head', 'academic_department_head']);
    }

    private function usersWithUnmappedPositionCompetencies(): Builder
    {
        if (! Schema::hasTable('position_competencies') || ! Schema::hasColumn('users', 'position_id')) {
            return User::query()->whereRaw('1 = 0');
        }

        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        if (! $roundId) {
            return User::query()->whereRaw('1 = 0');
        }

        return User::query()
            ->where('is_active', true)
            ->whereNotNull('position_id')
            ->whereNotExists(function ($query) use ($roundId): void {
                $query->selectRaw('1')
                    ->from('position_competencies')
                    ->where('position_competencies.assessment_round_id', $roundId)
                    ->whereColumn('position_competencies.position_id', 'users.position_id');
            });
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

    private function sendToUsers($users, $mailable): void
    {
        $users->each(function (User $user) use ($mailable): void {
            $recipient = $this->recipientResolver->recipientsFor($user);
            if (! $recipient) {
                return;
            }

            try {
                Mail::to($recipient)->send(clone $mailable);
            } catch (Throwable $exception) {
                Log::warning('Unable to send digest notification email.', [
                    'user_id' => $user->id,
                    'recipient' => $this->recipientResolver->recipientLabelFor($user),
                    'intended_recipient' => $user->email,
                    'mail' => $mailable::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    private function hasDigestContent(array $sections): bool
    {
        foreach ($sections as $items) {
            if ($items !== []) {
                return true;
            }
        }

        return false;
    }

    private function isNotificationEnabled(): bool
    {
        return ! app()->environment('local') || Cache::get('dev_notifications_enabled', true) !== false;
    }

    private function userSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'workline' => $user->workline,
            'department' => $user->department,
            'position' => $user->position,
        ];
    }
}
