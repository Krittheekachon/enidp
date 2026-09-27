<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class IdpDeliveryTypeSettingController extends Controller
{
    private const FORM_CODES = [
        'form_3_project_assignment',
        'form_4_ojt',
        'form_5_coaching',
        'form_6_mentoring',
        'form_7_group_activity',
        'form_8_feedback',
        'form_9_field_trip',
        'form_10_training',
    ];

    private const DELIVERY_TYPES = [
        'e_learning' => [
            'name_th' => 'การฝึกอบรมออนไลน์',
            'name_en' => 'e-Learning',
            'sort_order' => 1,
        ],
        'in_class' => [
            'name_th' => 'การฝึกอบรมในห้องเรียน',
            'name_en' => 'In Class Training',
            'sort_order' => 2,
        ],
    ];

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'delivery_types' => ['required', 'array'],
            'delivery_types.e_learning' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'delivery_types.in_class' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
            'delivery_forms' => ['required', 'array'],
            'delivery_forms.e_learning' => ['required', 'string', Rule::in(self::FORM_CODES)],
            'delivery_forms.in_class' => ['required', 'string', Rule::in(self::FORM_CODES)],
        ], [
            'delivery_types.e_learning.required' => 'กรุณากรอกรหัสรูปแบบ e-Learning',
            'delivery_types.e_learning.regex' => 'รหัสรูปแบบ e-Learning ต้องเป็นตัวเลข',
            'delivery_types.in_class.required' => 'กรุณากรอกรหัสรูปแบบ In Class Training',
            'delivery_types.in_class.regex' => 'รหัสรูปแบบ In Class Training ต้องเป็นตัวเลข',
            'delivery_forms.e_learning.required' => 'กรุณาเลือกแบบฟอร์มของ e-Learning',
            'delivery_forms.in_class.required' => 'กรุณาเลือกแบบฟอร์มของ In Class Training',
        ]);

        foreach (self::DELIVERY_TYPES as $deliveryType => $meta) {
            $exists = DB::table('learning_catalog_delivery_types')
                ->where('key', $deliveryType)
                ->exists();

            $payload = [
                'code' => $validated['delivery_types'][$deliveryType],
                'name_th' => $meta['name_th'],
                'name_en' => $meta['name_en'],
                'sort_order' => $meta['sort_order'],
                'is_active' => true,
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('learning_catalog_delivery_types', 'form_code')) {
                $payload['form_code'] = $validated['delivery_forms'][$deliveryType];
            }

            if (!$exists) {
                $payload['created_at'] = now();
            }

            DB::table('learning_catalog_delivery_types')->updateOrInsert(
                ['key' => $deliveryType],
                $payload,
            );
        }

        return back()->with('success', 'บันทึกรหัสรูปแบบเรียบร้อยแล้ว');
    }
}
