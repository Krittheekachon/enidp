<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class IdpLearningMethodController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        DB::transaction(function () use ($data): void {
            $values = [
                'focus_type' => $data['focus_type'],
                'title' => $data['title'],
                'sort_order' => (int) DB::table('idp_learning_methods')
                    ->where('focus_type', $data['focus_type'])
                    ->max('sort_order') + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('idp_learning_methods', 'form_code')) {
                $values['form_code'] = $data['form_code'] ?? null;
            }

            DB::table('idp_learning_methods')->insert($values);
        });

        return back()->with('success', 'เพิ่มหัวข้อแนวทาง IDP เรียบร้อยแล้ว');
    }

    public function update(Request $request, int $method): RedirectResponse
    {
        $data = $this->validatedData($request);

        $values = [
            'focus_type' => $data['focus_type'],
            'title' => $data['title'],
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('idp_learning_methods', 'form_code')) {
            $values['form_code'] = $data['form_code'] ?? null;
        }

        DB::table('idp_learning_methods')
            ->where('id', $method)
            ->update($values);

        return back()->with('success', 'อัปเดตหัวข้อแนวทาง IDP เรียบร้อยแล้ว');
    }

    public function destroy(int $method): RedirectResponse
    {
        DB::table('idp_learning_methods')->where('id', $method)->delete();

        return back()->with('success', 'ลบหัวข้อแนวทาง IDP เรียบร้อยแล้ว');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'focus_type' => ['required', Rule::in(['experiential', 'social'])],
            'title' => ['required', 'string', 'max:255'],
            'form_code' => ['nullable', 'string', 'max:80', Rule::in([
                'form_3_project_assignment',
                'form_4_ojt',
                'form_5_coaching',
                'form_6_mentoring',
                'form_7_group_activity',
                'form_8_feedback',
                'form_9_field_trip',
            ])],
        ]);
    }
}
