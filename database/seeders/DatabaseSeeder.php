<?php

namespace Database\Seeders;

use App\Models\CompetencyType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoUserSeeder::class);
        }

        $competencyTypes = [
            ['code' => 'CC', 'full_name' => 'Core Competency', 'description' => 'สมรรถนะหลักที่บุคลากรทุกตำแหน่งควรมีร่วมกัน'],
            ['code' => 'MC', 'full_name' => 'Managerial Competency', 'description' => 'สมรรถนะด้านการบริหารและภาวะผู้นำสำหรับตำแหน่งบริหารหรือหัวหน้าหน่วย'],
            ['code' => 'FC1', 'full_name' => 'Functional Competency 1', 'description' => 'สมรรถนะเฉพาะตามสายงานหรือกลุ่มงานระดับที่ 1'],
            ['code' => 'FC2', 'full_name' => 'Functional Competency 2', 'description' => 'สมรรถนะเฉพาะตามสายงานหรือกลุ่มงานระดับที่ 2'],
        ];

        foreach ($competencyTypes as $type) {
            CompetencyType::updateOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }
}
