<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetencyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompetencyTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        CompetencyType::create($this->validatedData($request));

        return back()->with('success', 'บันทึกประเภทสมรรถนะเรียบร้อยแล้ว');
    }

    public function update(Request $request, CompetencyType $competencyType): RedirectResponse
    {
        $competencyType->update($this->validatedData($request, $competencyType));

        return back()->with('success', 'อัปเดตประเภทสมรรถนะเรียบร้อยแล้ว');
    }

    public function destroy(CompetencyType $competencyType): RedirectResponse
    {
        $competencyType->delete();

        return back()->with('success', 'ลบประเภทสมรรถนะเรียบร้อยแล้ว');
    }

    private function validatedData(Request $request, ?CompetencyType $competencyType = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('competency_types', 'code')->ignore($competencyType?->id),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
        ]);
    }
}
