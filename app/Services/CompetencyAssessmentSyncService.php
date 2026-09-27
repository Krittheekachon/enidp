<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CompetencyAssessmentSyncService
{
    public function __construct(private NotificationService $notifications)
    {
    }

    public function syncUser(User $user): void
    {
        $competencyIds = $this->competencyIdsForUser($user);
        $this->syncUserAssessments($user->id, $competencyIds);
    }

    public function syncScope(int $roundId, int $jobFamilyId, int $levelId): void
    {
        $competencyIds = DB::table('hr_expectations')
            ->where('assessment_round_id', $roundId)
            ->where('job_family_id', $jobFamilyId)
            ->where('level_id', $levelId)
            ->whereNull('position_id')
            ->pluck('competency_id')
            ->unique()
            ->values();

        $jobFamily = DB::table('job_families')
            ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('job_families.id', $jobFamilyId)
            ->select('job_families.name', 'worklines.name as workline_name')
            ->first();

        $levelName = DB::table('levels')->where('id', $levelId)->value('name');

        if (! $jobFamily || ! $levelName) {
            return;
        }

        $users = User::query()
            ->where('is_active', true)
            ->where('workline', $jobFamily->workline_name)
            ->where(function ($query) use ($jobFamily) {
                $query->where('department', $jobFamily->name)
                    ->orWhere('department', 'like', $jobFamily->name.' > %')
                    ->orWhere('position', $jobFamily->name)
                    ->orWhere('level', $jobFamily->name);
            })
            ->where(function ($query) use ($levelId, $levelName) {
                $query->where('level_id', $levelId)
                    ->orWhere('level', $levelName)
                    ->orWhere('position', $levelName);
            })
            ->get();

        foreach ($users as $user) {
            $this->syncUserAssessments($user->id, $competencyIds);
        }
    }

    public function syncAllActiveUsers(): void
    {
        User::query()
            ->where('is_active', true)
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $this->syncUser($user);
                }
            });
    }

    private function syncUserAssessments(int $userId, Collection $competencyIds): void
    {
        $competencyIds = $competencyIds->filter()->unique()->values();
        $now = now();
        $assessmentRoundId = Schema::hasColumn('assessments', 'assessment_round_id')
            ? $this->activeAssessmentRoundId()
            : null;

        if (Schema::hasColumn('assessments', 'assessment_round_id') && ! $assessmentRoundId) {
            return;
        }

        foreach ($competencyIds as $competencyId) {
            $attributes = [
                    'user_id' => $userId,
                    'competency_id' => $competencyId,
                    'assessment_round_id' => $assessmentRoundId,
                    'status' => 'draft',
                    'score' => 0,
                    'note' => '',
                    'created_at' => $now,
                    'updated_at' => $now,
            ];

            DB::table('assessments')->insertOrIgnore($attributes);
        }

        DB::table('assessments')
            ->where('user_id', $userId)
            ->when($assessmentRoundId, fn ($query) => $query->where('assessment_round_id', $assessmentRoundId))
            ->when($competencyIds->isNotEmpty(), fn ($query) => $query->whereNotIn('competency_id', $competencyIds))
            ->where('status', 'draft')
            ->where(function ($query) {
                $query->whereNull('score')->orWhere('score', 0);
            })
            ->where(function ($query) {
                $query->whereNull('note')->orWhere('note', '');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('assessment_indicator_results')
                    ->whereColumn('assessment_indicator_results.assessment_id', 'assessments.id');
            })
            ->delete();
    }

    private function activeAssessmentRoundId(): ?int
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        return $roundId ? (int) $roundId : null;
    }

    private function competencyIdsForUser(User $user): Collection
    {
        $roundId = $this->activeAssessmentRoundId();
        if (! $roundId) {
            return collect();
        }

        $levelIds = $this->levelIdsForUser($user);
        $positionIds = $this->positionIdsForUser($user);

        if ($levelIds->isEmpty() && $positionIds->isEmpty()) {
            return collect();
        }

        $jobFamilyIds = $this->jobFamilyIdsForUser($user);

        $expectationIds = ($levelIds->isEmpty() || $jobFamilyIds->isEmpty())
            ? collect()
            : DB::table('hr_expectations')
                ->where('assessment_round_id', $roundId)
                ->whereIn('level_id', $levelIds)
                ->whereIn('job_family_id', $jobFamilyIds)
                ->pluck('competency_id');

        $mappedPositionIds = $positionIds->isEmpty()
            ? collect()
            : DB::table('position_competencies')
                ->where('assessment_round_id', $roundId)
                ->whereIn('position_id', $positionIds)
                ->pluck('position_id')
                ->unique()
                ->values();

        $unmappedPositionIds = $positionIds->diff($mappedPositionIds)->values();

        if ($unmappedPositionIds->isNotEmpty()) {
            $this->notifyUnmappedPositions($user, $unmappedPositionIds);
            $this->notifications->notifyHrUserWithUnmappedPosition($user);
        }

        $positionCompetencyIds = $positionIds->isEmpty()
            ? collect()
            : DB::table('position_competencies')
                ->where('assessment_round_id', $roundId)
                ->whereIn('position_id', $positionIds)
                ->pluck('competency_id');

        return $expectationIds
            ->merge($positionCompetencyIds)
            ->unique()
            ->values();
    }

    private function notifyUnmappedPositions(User $user, Collection $positionIds): void
    {
        $names = DB::table('positions')
            ->whereIn('id', $positionIds)
            ->pluck('name')
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty() && $user->position) {
            $names->push($user->position);
        }

        $names->each(fn (string $name) => $this->notifications->notifyHrUnmappedPosition($name));
    }

    private function levelIdsForUser(User $user): Collection
    {
        $levelIds = collect();
        $worklineId = $user->workline
            ? DB::table('worklines')->where('name', $user->workline)->value('id')
            : null;

        if (! $worklineId) {
            return $levelIds;
        }

        if ($user->level_id) {
            $levelIds = $levelIds->merge(DB::table('levels')
                ->where('id', $user->level_id)
                ->where('workline_id', $worklineId)
                ->whereNull('job_family_id')
                ->pluck('id'));
        }

        if ($user->level) {
            $levelIds = $levelIds->merge(DB::table('levels')
                ->where('workline_id', $worklineId)
                ->whereNull('job_family_id')
                ->where('name', $user->level)
                ->pluck('id'));
        }

        return $levelIds->filter()->unique()->values();
    }

    private function jobFamilyIdsForUser(User $user): Collection
    {
        $jobFamilyIds = collect();

        if ($user->position_id) {
            $jobFamilyIds->push(DB::table('positions')->where('id', $user->position_id)->value('job_family_id'));
        }

        if ($user->workline) {
            $worklineId = DB::table('worklines')->where('name', $user->workline)->value('id');

            if ($worklineId) {
                $names = collect([$user->department, $user->position, $user->level])
                    ->filter()
                    ->flatMap(fn (string $value) => collect(explode(' > ', $value))->map(fn (string $part) => trim($part)))
                    ->filter()
                    ->unique()
                    ->values();

                if ($names->isNotEmpty()) {
                    $jobFamilyIds = $jobFamilyIds->merge(
                        DB::table('job_families')
                            ->where('workline_id', $worklineId)
                            ->whereIn('name', $names)
                            ->pluck('id')
                    );
                }
            }
        }

        return $jobFamilyIds->filter()->unique()->values();
    }

    private function positionIdsForUser(User $user): Collection
    {
        $positionIds = collect();

        if ($user->position_id) {
            $positionIds->push($user->position_id);
        }

        if (! $user->position) {
            return $positionIds->filter()->unique()->values();
        }

        $worklineId = $user->workline
            ? DB::table('worklines')->where('name', $user->workline)->value('id')
            : null;

        $matchedIds = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->where('positions.name', $user->position)
            ->when($worklineId, fn ($query) => $query->where('job_families.workline_id', $worklineId))
            ->when($user->department, function ($query) use ($user) {
                $departmentRoot = trim(explode(' > ', $user->department)[0] ?? $user->department);

                $query->where(function ($nested) use ($user, $departmentRoot) {
                    $nested->where('job_families.name', $user->department)
                        ->orWhere('job_families.name', $departmentRoot);
                });
            })
            ->pluck('positions.id');

        return $positionIds->merge($matchedIds)->filter()->unique()->values();
    }
}
