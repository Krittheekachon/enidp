<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id')
            ?: DB::table('assessment_rounds')->orderByDesc('year')->orderByDesc('id')->value('id');

        $hasLegacyRows = collect(['position_competencies', 'position_fc_selection_rules', 'fc_topic_selections'])
            ->contains(fn (string $table): bool => Schema::hasTable($table) && DB::table($table)->exists());

        if (! $roundId && $hasLegacyRows) {
            $roundId = DB::table('assessment_rounds')->insertGetId([
                'name' => 'ข้อมูลการกำหนดสมรรถนะเดิม',
                'year' => (int) now()->format('Y') + 543,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (['position_competencies', 'position_fc_selection_rules', 'fc_topic_selections'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'assessment_round_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->foreignId('assessment_round_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('assessment_rounds')
                        ->cascadeOnDelete();
                });
            }

            if ($roundId && Schema::hasTable($table)) {
                DB::table($table)->whereNull('assessment_round_id')->update(['assessment_round_id' => $roundId]);
            }
        }

        Schema::table('position_competencies', function (Blueprint $table): void {
            $table->dropUnique(['position_id', 'competency_id']);
        });
        Schema::table('position_fc_selection_rules', function (Blueprint $table): void {
            $table->dropUnique(['position_id']);
        });
        Schema::table('fc_topic_selections', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'position_id']);
        });
        DB::statement('CREATE UNIQUE INDEX position_competencies_round_position_competency_unique ON position_competencies (assessment_round_id, position_id, competency_id)');
        DB::statement('CREATE UNIQUE INDEX position_fc_rules_round_position_unique ON position_fc_selection_rules (assessment_round_id, position_id)');
        DB::statement('CREATE UNIQUE INDEX fc_topic_selections_round_user_position_unique ON fc_topic_selections (assessment_round_id, user_id, position_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS position_competencies_round_position_competency_unique');
        DB::statement('DROP INDEX IF EXISTS position_fc_rules_round_position_unique');
        DB::statement('DROP INDEX IF EXISTS fc_topic_selections_round_user_position_unique');

        foreach ([
            ['position_competencies', ['position_id', 'competency_id'], 'position_competencies_position_id_competency_id_unique'],
            ['position_fc_selection_rules', ['position_id'], 'position_fc_selection_rules_position_id_unique'],
            ['fc_topic_selections', ['user_id', 'position_id'], 'fc_topic_selections_user_id_position_id_unique'],
        ] as [$table, $partition, $index]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = implode(', ', $partition);
            DB::statement("DELETE FROM {$table} WHERE id IN (SELECT id FROM (SELECT id, ROW_NUMBER() OVER (PARTITION BY {$columns} ORDER BY updated_at DESC, id DESC) AS row_number FROM {$table}) duplicates WHERE row_number > 1)");
            DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} ({$columns})");

            if (Schema::hasColumn($table, 'assessment_round_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropConstrainedForeignId('assessment_round_id');
                });
            }
        }
    }
};
