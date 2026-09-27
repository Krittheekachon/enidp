<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CompetencyAssessmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PositionCompetencyController extends Controller
{
    public function __construct(private CompetencyAssessmentSyncService $competencyAssessmentSync)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'assessment_round_id' => ['required', 'integer', 'exists:assessment_rounds,id'],
            'competency_id' => ['nullable', 'integer', 'exists:competencies,id'],
            'competency_ids' => ['nullable', 'array'],
            'competency_ids.*' => ['integer', 'exists:competencies,id'],
        ]);

        $competencyIds = collect($data['competency_ids'] ?? [])
            ->push($data['competency_id'] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($competencyIds->isEmpty()) {
            return back()->withErrors(['competency_id' => 'กรุณาเลือกสมรรถนะอย่างน้อย 1 รายการ']);
        }

        $now = now();

        $rows = $competencyIds->map(fn (int $competencyId) => [
            'assessment_round_id' => $data['assessment_round_id'],
            'position_id' => $data['position_id'],
            'competency_id' => $competencyId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('position_competencies')->insertOrIgnore($rows);
        $this->syncUsersForPositionIfActive((int) $data['position_id'], (int) $data['assessment_round_id']);

        return back()->with('success', 'ผูกสมรรถนะกับตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'assessment_round_id' => ['required', 'integer', 'exists:assessment_rounds,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
        ]);

        DB::table('position_competencies')
            ->where('position_id', $data['position_id'])
            ->where('competency_id', $data['competency_id'])
            ->where('assessment_round_id', $data['assessment_round_id'])
            ->delete();
        $this->syncUsersForPositionIfActive((int) $data['position_id'], (int) $data['assessment_round_id']);

        return back()->with('success', 'ลบสมรรถนะออกจากตำแหน่งเรียบร้อยแล้ว');
    }

    public function updateFcSelectionRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'assessment_round_id' => ['required', 'integer', 'exists:assessment_rounds,id'],
            'required_fc_count' => ['required', 'integer', 'min:0'],
        ]);

        $availableFcCount = DB::table('position_competencies')
            ->join('competencies', 'position_competencies.competency_id', '=', 'competencies.id')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('position_competencies.position_id', $data['position_id'])
            ->where('position_competencies.assessment_round_id', $data['assessment_round_id'])
            ->whereIn('competency_types.code', ['FC', 'FC1', 'FC2'])
            ->count();

        if ((int) $data['required_fc_count'] > $availableFcCount) {
            return back()->withErrors([
                'required_fc_count' => 'จำนวน FC ที่ต้องเลือกมากกว่าจำนวน FC ที่ผูกกับตำแหน่งนี้',
            ]);
        }

        DB::table('position_fc_selection_rules')->updateOrInsert(
            [
                'assessment_round_id' => $data['assessment_round_id'],
                'position_id' => $data['position_id'],
            ],
            [
                'required_fc_count' => (int) $data['required_fc_count'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with('success', 'บันทึกจำนวน FC ที่ต้องเลือกเรียบร้อยแล้ว');
    }

    public function copyFromRound(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_round_id' => ['required', 'integer', 'exists:assessment_rounds,id', 'different:target_round_id'],
            'target_round_id' => ['required', 'integer', 'exists:assessment_rounds,id'],
        ]);

        $now = now();
        $sourceRows = DB::table('position_competencies')
            ->where('assessment_round_id', $data['source_round_id'])
            ->get(['position_id', 'competency_id']);
        $sourceRules = DB::table('position_fc_selection_rules')
            ->where('assessment_round_id', $data['source_round_id'])
            ->get(['position_id', 'required_fc_count']);

        DB::transaction(function () use ($data, $sourceRows, $sourceRules, $now): void {
            DB::table('position_competencies')
                ->where('assessment_round_id', $data['target_round_id'])
                ->delete();
            DB::table('position_fc_selection_rules')
                ->where('assessment_round_id', $data['target_round_id'])
                ->delete();

            if ($sourceRows->isNotEmpty()) {
                DB::table('position_competencies')->insert($sourceRows->map(fn (object $row): array => [
                    'assessment_round_id' => $data['target_round_id'],
                    'position_id' => $row->position_id,
                    'competency_id' => $row->competency_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }

            foreach ($sourceRules as $rule) {
                DB::table('position_fc_selection_rules')->insert([
                    'assessment_round_id' => $data['target_round_id'],
                    'position_id' => $rule->position_id,
                    'required_fc_count' => $rule->required_fc_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        if ($this->isActiveRound((int) $data['target_round_id'])) {
            $this->competencyAssessmentSync->syncAllActiveUsers();
        }

        return back()->with('success', "นำเข้าสมรรถนะประจำตำแหน่ง {$sourceRows->count()} รายการเรียบร้อยแล้ว");
    }

    private function syncUsersForPositionIfActive(int $positionId, int $roundId): void
    {
        if ($this->isActiveRound($roundId)) {
            $this->syncUsersForPosition($positionId);
        }
    }

    private function isActiveRound(int $roundId): bool
    {
        return DB::table('assessment_rounds')->where('id', $roundId)->where('is_active', true)->exists();
    }

    private function syncUsersForPosition(int $positionId): void
    {
        $position = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->join('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('positions.id', $positionId)
            ->select(
                'positions.id',
                'positions.name',
                'job_families.name as job_family_name',
                'worklines.name as workline_name',
            )
            ->first();

        if (! $position) {
            return;
        }

        User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($position) {
                $query->where('position_id', $position->id)
                    ->orWhere(function ($nested) use ($position) {
                        $nested->where('position', $position->name)
                            ->where('workline', $position->workline_name)
                            ->where(function ($department) use ($position) {
                                $department->where('department', $position->job_family_name)
                                    ->orWhere('department', 'like', $position->job_family_name.' > %');
                            });
                    });
            })
            ->get()
            ->each(fn (User $user) => $this->competencyAssessmentSync->syncUser($user));
    }
}
