<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StructureController extends Controller
{
    public function storeWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:worklines,name'],
        ]);

        DB::table('worklines')->insert([
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกสายงานเรียบร้อยแล้ว');
    }

    public function updateWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'old_name' => ['required', 'string', 'exists:worklines,name'],
            'name' => ['required', 'string', 'max:255', Rule::unique('worklines', 'name')->ignore($request->old_name, 'name')],
        ]);

        DB::table('worklines')
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        $this->syncUserWorklineName($data['old_name'], $data['name']);

        return back()->with('success', 'อัปเดตสายงานเรียบร้อยแล้ว');
    }

    public function destroyWorkline(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'exists:worklines,name'],
        ]);

        DB::table('worklines')->where('name', $data['name'])->delete();

        return back()->with('success', 'ลบสายงานเรียบร้อยแล้ว');
    }

    public function storeJobFamily(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('job_families', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
        ]);

        DB::table('job_families')->insert([
            'workline_id' => $worklineId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกกลุ่มงานเรียบร้อยแล้ว');
    }

    public function updateJobFamily(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('job_families', 'name')
                    ->where(fn ($query) => $query->where('workline_id', $worklineId))
                    ->ignore(
                        DB::table('job_families')
                            ->where('workline_id', $worklineId)
                            ->where('name', $request->old_name)
                            ->value('id')
                    ),
            ],
        ]);

        DB::table('job_families')
            ->where('workline_id', $worklineId)
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        $this->syncUserDepartmentPrefix($data['workline_name'] ?? null, $data['old_name'], $data['name']);

        return back()->with('success', 'อัปเดตกลุ่มงานเรียบร้อยแล้ว');
    }

    public function destroyJobFamily(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(fn ($query) => $query->where('workline_id', $worklineId)),
            ],
        ]);

        DB::table('job_families')
            ->where('workline_id', $worklineId)
            ->where('name', $data['name'])
            ->delete();

        return back()->with('success', 'ลบกลุ่มงานเรียบร้อยแล้ว');
    }

    public function storePosition(Request $request): RedirectResponse
    {
        if ($request->filled('unit_name')) {
            return $this->storeSupportPosition($request);
        }

        $worklineName = $request->string('workline_name')->toString() ?: null;
        $jobFamilyName = $request->string('job_family_name')->toString() ?: 'ตำแหน่งสายสนับสนุน';
        $jobFamilyId = $this->jobFamilyId($jobFamilyName, $worklineName);
        if (! $jobFamilyId && $worklineName && $this->isSupportWorklineName($worklineName)) {
            $jobFamilyId = DB::table('job_families')->insertGetId([
                'workline_id' => $this->worklineId($worklineName),
                'name' => $jobFamilyName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(
                    fn ($query) => $request->filled('workline_name')
                        ? $query->where('workline_id', $this->worklineId($request->workline_name))
                        : $query
                ),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')
                    ->where(fn ($query) => $query
                        ->where('job_family_id', $jobFamilyId)
                        ->whereNull('support_unit_id')),
            ],
        ]);

        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => null,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกตำแหน่งเรียบร้อยแล้ว');
    }

    public function updatePosition(Request $request): RedirectResponse
    {
        if ($request->filled('unit_name')) {
            return $this->updateSupportPosition($request);
        }

        $jobFamilyId = $this->jobFamilyId(
            $request->string('job_family_name')->toString(),
            $request->string('workline_name')->toString() ?: null
        );
        $positionId = DB::table('positions')
            ->where('job_family_id', $jobFamilyId)
            ->whereNull('support_unit_id')
            ->where('name', $request->old_name)
            ->value('id');

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(
                    fn ($query) => $request->filled('workline_name')
                        ? $query->where('workline_id', $this->worklineId($request->workline_name))
                        : $query
                ),
            ],
            'old_name' => ['required', 'string'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('positions', 'name')
                    ->where(fn ($query) => $query
                        ->where('job_family_id', $jobFamilyId)
                        ->whereNull('support_unit_id'))
                    ->ignore($positionId),
            ],
        ]);

        DB::table('positions')
            ->where('job_family_id', $jobFamilyId)
            ->whereNull('support_unit_id')
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        $this->syncUserPositionName(
            $data['workline_name'] ?? null,
            $data['job_family_name'],
            $data['old_name'],
            $data['name']
        );

        return back()->with('success', 'อัปเดตตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroyPosition(Request $request): RedirectResponse
    {
        if ($request->filled('unit_name')) {
            return $this->destroySupportPosition($request);
        }

        $jobFamilyId = $this->jobFamilyId(
            $request->string('job_family_name')->toString(),
            $request->string('workline_name')->toString() ?: null
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => [
                'required',
                'string',
                Rule::exists('job_families', 'name')->where(
                    fn ($query) => $request->filled('workline_name')
                        ? $query->where('workline_id', $this->worklineId($request->workline_name))
                        : $query
                ),
            ],
            'name' => ['required', 'string'],
        ]);

        DB::table('positions')
            ->where('job_family_id', $jobFamilyId)
            ->whereNull('support_unit_id')
            ->where('name', $data['name'])
            ->delete();

        return back()->with('success', 'ลบตำแหน่งเรียบร้อยแล้ว');
    }

    public function storeSupportDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:support_departments,name'],
        ]);

        DB::table('support_departments')->insert([
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกฝ่ายเรียบร้อยแล้ว');
    }

    public function updateSupportDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'old_name' => ['required', 'string', 'exists:support_departments,name'],
            'name' => ['required', 'string', 'max:255', Rule::unique('support_departments', 'name')->ignore($request->old_name, 'name')],
        ]);

        DB::table('support_departments')
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return back()->with('success', 'อัปเดตฝ่ายเรียบร้อยแล้ว');
    }

    public function destroySupportDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'exists:support_departments,name'],
        ]);

        DB::table('support_departments')->where('name', $data['name'])->delete();

        return back()->with('success', 'ลบฝ่ายเรียบร้อยแล้ว');
    }

    public function storeSupportWork(Request $request): RedirectResponse
    {
        $supportDepartmentId = $this->supportDepartmentId($request->string('division_name')->toString());

        $data = $request->validate([
            'division_name' => ['required', 'string', 'exists:support_departments,name'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_works', 'name')->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId)),
            ],
        ]);

        DB::table('support_works')->insert([
            'support_department_id' => $supportDepartmentId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกงานเรียบร้อยแล้ว');
    }

    public function updateSupportWork(Request $request): RedirectResponse
    {
        $supportDepartmentId = $this->supportDepartmentId($request->string('division_name')->toString());

        $data = $request->validate([
            'division_name' => ['required', 'string', 'exists:support_departments,name'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('support_works', 'name')->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_works', 'name')
                    ->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId))
                    ->ignore(
                        DB::table('support_works')
                            ->where('support_department_id', $supportDepartmentId)
                            ->where('name', $request->old_name)
                            ->value('id')
                    ),
            ],
        ]);

        DB::table('support_works')
            ->where('support_department_id', $supportDepartmentId)
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return back()->with('success', 'อัปเดตงานเรียบร้อยแล้ว');
    }

    public function destroySupportWork(Request $request): RedirectResponse
    {
        $supportDepartmentId = $this->supportDepartmentId($request->string('division_name')->toString());

        $data = $request->validate([
            'division_name' => ['required', 'string', 'exists:support_departments,name'],
            'name' => [
                'required',
                'string',
                Rule::exists('support_works', 'name')->where(fn ($query) => $query->where('support_department_id', $supportDepartmentId)),
            ],
        ]);

        DB::table('support_works')
            ->where('support_department_id', $supportDepartmentId)
            ->where('name', $data['name'])
            ->delete();

        return back()->with('success', 'ลบงานเรียบร้อยแล้ว');
    }

    public function storeSupportUnit(Request $request): RedirectResponse
    {
        $supportWorkId = $this->supportWorkId(
            $request->string('work_name')->toString(),
            $request->string('division_name')->toString()
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_units', 'name')->where(fn ($query) => $query->where('support_work_id', $supportWorkId)),
            ],
        ]);

        DB::table('support_units')->insert([
            'support_work_id' => $supportWorkId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกหน่วยงานเรียบร้อยแล้ว');
    }

    public function updateSupportUnit(Request $request): RedirectResponse
    {
        $supportWorkId = $this->supportWorkId(
            $request->string('work_name')->toString(),
            $request->string('division_name')->toString()
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('support_units', 'name')->where(fn ($query) => $query->where('support_work_id', $supportWorkId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('support_units', 'name')
                    ->where(fn ($query) => $query->where('support_work_id', $supportWorkId))
                    ->ignore(
                        DB::table('support_units')
                            ->where('support_work_id', $supportWorkId)
                            ->where('name', $request->old_name)
                            ->value('id')
                    ),
            ],
        ]);

        DB::table('support_units')
            ->where('support_work_id', $supportWorkId)
            ->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);

        return back()->with('success', 'อัปเดตหน่วยงานเรียบร้อยแล้ว');
    }

    public function destroySupportUnit(Request $request): RedirectResponse
    {
        $supportWorkId = $this->supportWorkId(
            $request->string('work_name')->toString(),
            $request->string('division_name')->toString()
        );

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'name' => [
                'required',
                'string',
                Rule::exists('support_units', 'name')->where(fn ($query) => $query->where('support_work_id', $supportWorkId)),
            ],
        ]);

        DB::table('support_units')
            ->where('support_work_id', $supportWorkId)
            ->where('name', $data['name'])
            ->delete();

        return back()->with('success', 'ลบหน่วยงานเรียบร้อยแล้ว');
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);
        $jobFamilyId = null;

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => ['prohibited'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'name')->where(fn ($query) => $query
                    ->where('workline_id', $worklineId)
                    ->where('job_family_id', $jobFamilyId)),
            ],
            'expected_level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        DB::table('levels')->insert([
            'workline_id' => $this->worklineId($data['workline_name'] ?? null),
            'job_family_id' => $jobFamilyId,
            'name' => $data['name'],
            'expected_level' => $data['expected_level'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function updateLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);
        $jobFamilyId = null;

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => ['prohibited'],
            'old_name' => [
                'required',
                'string',
                Rule::exists('levels', 'name')->where(fn ($query) => $query
                    ->where('workline_id', $worklineId)
                    ->where('job_family_id', $jobFamilyId)),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('levels', 'name')
                    ->where(fn ($query) => $query
                        ->where('workline_id', $worklineId)
                        ->where('job_family_id', $jobFamilyId))
                    ->ignore($request->old_name, 'name'),
            ],
            'expected_level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        DB::table('levels')
            ->where('workline_id', $this->worklineId($data['workline_name'] ?? null))
            ->where('job_family_id', $jobFamilyId)
            ->where('name', $data['old_name'])
            ->update([
                'name' => $data['name'],
                'expected_level' => $data['expected_level'] ?? null,
                'updated_at' => now(),
            ]);

        $this->syncUserLevelName(
            $data['workline_name'] ?? null,
            $data['job_family_name'] ?? null,
            $data['old_name'],
            $data['name']
        );

        return back()->with('success', 'อัปเดตระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function destroyLevel(Request $request): RedirectResponse
    {
        $worklineId = $this->worklineId($request->string('workline_name')->toString() ?: null);
        $jobFamilyId = null;

        $data = $request->validate([
            'workline_name' => ['nullable', 'string', 'exists:worklines,name'],
            'job_family_name' => ['prohibited'],
            'name' => [
                'required',
                'string',
                Rule::exists('levels', 'name')->where(fn ($query) => $query
                    ->where('workline_id', $worklineId)
                    ->where('job_family_id', $jobFamilyId)),
            ],
        ]);

        DB::table('levels')
            ->where('workline_id', $this->worklineId($data['workline_name'] ?? null))
            ->where('job_family_id', $jobFamilyId)
            ->where('name', $data['name'])
            ->delete();

        return back()->with('success', 'ลบระดับตำแหน่งเรียบร้อยแล้ว');
    }

    public function storeLearningMethod(Request $request): RedirectResponse
    {
        abort(403, 'ประเภทการเรียนรู้ถูกกำหนดตายตัวในระบบ');
    }

    public function updateLearningMethod(Request $request): RedirectResponse
    {
        abort(403, 'ประเภทการเรียนรู้ถูกกำหนดตายตัวในระบบ');
    }

    public function destroyLearningMethod(Request $request): RedirectResponse
    {
        abort(403, 'ประเภทการเรียนรู้ถูกกำหนดตายตัวในระบบ');
    }

    private function worklineId(?string $name): ?int
    {
        if (!$name) {
            return null;
        }

        return DB::table('worklines')->where('name', $name)->value('id');
    }

    private function isSupportWorklineName(string $name): bool
    {
        return in_array($name, ['สายสนับสนุน', 'สายงานสนับสนุน'], true);
    }

    private function syncUserWorklineName(string $oldName, string $newName): void
    {
        DB::table('users')
            ->where('workline', $oldName)
            ->update([
                'workline' => $newName,
                'updated_at' => now(),
            ]);
    }

    private function syncUserDepartmentPrefix(?string $worklineName, string $oldName, string $newName): void
    {
        $query = DB::table('users')
            ->select('id', 'department')
            ->where(function ($query) use ($oldName) {
                $query->where('department', $oldName)
                    ->orWhere('department', 'like', $oldName.' > %');
            });

        if ($worklineName) {
            $query->where('workline', $worklineName);
        }

        foreach ($query->get() as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'department' => $newName.substr($user->department, strlen($oldName)),
                    'updated_at' => now(),
                ]);
        }
    }

    private function syncUserPositionName(?string $worklineName, string $jobFamilyName, string $oldName, string $newName): void
    {
        $query = DB::table('users')
            ->where('position', $oldName)
            ->where(function ($query) use ($jobFamilyName) {
                $query->where('department', $jobFamilyName)
                    ->orWhere('department', 'like', $jobFamilyName.' > %');
            });

        if ($worklineName) {
            $query->where('workline', $worklineName);
        }

        $query->update([
            'position' => $newName,
            'updated_at' => now(),
        ]);
    }

    private function syncUserLevelName(?string $worklineName, ?string $jobFamilyName, string $oldName, string $newName): void
    {
        $query = DB::table('users')->where('level', $oldName);

        if ($worklineName) {
            $query->where('workline', $worklineName);
        }

        if ($jobFamilyName) {
            $query->where(function ($query) use ($jobFamilyName) {
                $query->where('department', $jobFamilyName)
                    ->orWhere('department', 'like', $jobFamilyName.' > %');
            });
        }

        $query->update([
            'level' => $newName,
            'updated_at' => now(),
        ]);
    }

    private function jobFamilyId(string $name, ?string $worklineName = null): int
    {
        $query = DB::table('job_families')->where('name', $name);

        if ($worklineName) {
            $query->where('workline_id', $this->worklineId($worklineName));
        }

        return (int) $query->value('id');
    }

    private function supportWorkId(string $name, string $departmentName): int
    {
        return (int) DB::table('support_works')
            ->where('support_department_id', $this->supportDepartmentId($departmentName))
            ->where('name', $name)
            ->value('id');
    }

    private function supportDepartmentId(string $name): int
    {
        return (int) DB::table('support_departments')->where('name', $name)->value('id');
    }

    private function storeSupportPosition(Request $request): RedirectResponse
    {
        $unitId = $this->supportUnitId($request);
        $data = $request->validate([
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'unit_name' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))],
        ]);

        $supportWorklineId = $this->worklineId('สายสนับสนุน');
        $jobFamilyId = DB::table('job_families')->where('workline_id', $supportWorklineId)->orderBy('id')->value('id');
        if (! $jobFamilyId) {
            $jobFamilyId = DB::table('job_families')->insertGetId([
                'workline_id' => $supportWorklineId,
                'name' => 'ตำแหน่งสายสนับสนุน',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => $unitId,
            'name' => $data['name'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'บันทึกตำแหน่งเรียบร้อยแล้ว');
    }

    private function updateSupportPosition(Request $request): RedirectResponse
    {
        $unitId = $this->supportUnitId($request);
        $data = $request->validate([
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'unit_name' => ['required', 'string'],
            'old_name' => ['required', 'string', Rule::exists('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))],
            'name' => ['required', 'string', 'max:255', Rule::unique('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))->ignore(DB::table('positions')->where('support_unit_id', $unitId)->where('name', $request->old_name)->value('id'))],
        ]);

        DB::table('positions')->where('support_unit_id', $unitId)->where('name', $data['old_name'])
            ->update(['name' => $data['name'], 'updated_at' => now()]);
        $this->syncUserSupportPositionName(
            $data['division_name'],
            $data['work_name'],
            $data['unit_name'],
            $data['old_name'],
            $data['name'],
        );

        return back()->with('success', 'อัปเดตตำแหน่งเรียบร้อยแล้ว');
    }

    private function syncUserSupportPositionName(
        string $divisionName,
        string $workName,
        string $unitName,
        string $oldName,
        string $newName,
    ): void {
        DB::table('users')
            ->whereIn('workline', ['สายสนับสนุน', 'สายงานสนับสนุน'])
            ->where('department', implode(' > ', [$divisionName, $workName, $unitName]))
            ->where('position', $oldName)
            ->update([
                'position' => $newName,
                'updated_at' => now(),
            ]);
    }

    private function destroySupportPosition(Request $request): RedirectResponse
    {
        $unitId = $this->supportUnitId($request);
        $data = $request->validate([
            'division_name' => ['required', 'string'],
            'work_name' => ['required', 'string'],
            'unit_name' => ['required', 'string'],
            'name' => ['required', 'string', Rule::exists('positions', 'name')->where(fn ($query) => $query->where('support_unit_id', $unitId))],
        ]);
        DB::table('positions')->where('support_unit_id', $unitId)->where('name', $data['name'])->delete();

        return back()->with('success', 'ลบตำแหน่งเรียบร้อยแล้ว');
    }

    private function supportUnitId(Request $request): int
    {
        return (int) DB::table('support_units')
            ->join('support_works', 'support_units.support_work_id', '=', 'support_works.id')
            ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
            ->where('support_departments.name', $request->string('division_name')->toString())
            ->where('support_works.name', $request->string('work_name')->toString())
            ->where('support_units.name', $request->string('unit_name')->toString())
            ->value('support_units.id');
    }
}
