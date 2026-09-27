<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_method_types')) {
            return;
        }

        $now = now();
        $methods = [
            ['key' => 'experiential-learning', 'label' => 'Experiential Learning', 'sort_order' => 1],
            ['key' => 'social-learning', 'label' => 'Social Learning', 'sort_order' => 2],
            ['key' => 'formal-learning', 'label' => 'Formal Learning', 'sort_order' => 3],
        ];

        if (DB::table('learning_method_types')->exists()) {
            foreach ([
                'learning-by-doing' => 'experiential-learning',
                'experiential' => 'experiential-learning',
                'non-study' => 'social-learning',
                'social' => 'social-learning',
                'formal' => 'formal-learning',
            ] as $legacyKey => $canonicalKey) {
                $legacyId = DB::table('learning_method_types')->where('key', $legacyKey)->value('id');
                if (! $legacyId) {
                    continue;
                }

                $canonicalId = DB::table('learning_method_types')->where('key', $canonicalKey)->value('id');
                if (! $canonicalId) {
                    DB::table('learning_method_types')->where('id', $legacyId)->update([
                        'key' => $canonicalKey,
                        'updated_at' => $now,
                    ]);
                    continue;
                }

                foreach (['learning_catalogs', 'idp_activities'] as $table) {
                    if (Schema::hasTable($table) && Schema::hasColumn($table, 'method_type_id')) {
                        DB::table($table)->where('method_type_id', $legacyId)->update(['method_type_id' => $canonicalId]);
                    }
                }
                DB::table('learning_method_types')->where('id', $legacyId)->delete();
            }

            foreach ($methods as $method) {
                DB::table('learning_method_types')->updateOrInsert(
                    ['key' => $method['key']],
                    [
                        'label' => $method['label'],
                        'is_active' => true,
                        'sort_order' => $method['sort_order'],
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        }

        if (! Schema::hasTable('idp_learning_methods')
            || ! Schema::hasColumn('idp_learning_methods', 'form_code')) {
            return;
        }

        foreach ([
            ['focus_type' => 'experiential', 'sort_order' => 1, 'form_code' => 'form_3_project_assignment'],
            ['focus_type' => 'experiential', 'sort_order' => 2, 'form_code' => 'form_4_ojt'],
            ['focus_type' => 'social', 'sort_order' => 1, 'form_code' => 'form_5_coaching'],
            ['focus_type' => 'social', 'sort_order' => 2, 'form_code' => 'form_6_mentoring'],
            ['focus_type' => 'social', 'sort_order' => 3, 'form_code' => 'form_7_group_activity'],
            ['focus_type' => 'social', 'sort_order' => 4, 'form_code' => 'form_8_feedback'],
            ['focus_type' => 'social', 'sort_order' => 5, 'form_code' => 'form_9_field_trip'],
            ['focus_type' => 'social', 'sort_order' => 6, 'form_code' => null],
        ] as $method) {
            DB::table('idp_learning_methods')
                ->where('focus_type', $method['focus_type'])
                ->where('sort_order', $method['sort_order'])
                ->update([
                    'form_code' => $method['form_code'],
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // This migration repairs shared master data and must not remove rows already used by IDP activities.
    }
};
