<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idp_learning_methods', function (Blueprint $table) {
            $table->id();
            $table->string('focus_type', 30);
            $table->string('title');
            $table->string('template_file_path')->nullable();
            $table->string('template_file_name')->nullable();
            $table->string('template_mime_type')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['focus_type', 'sort_order']);
        });

        DB::table('idp_learning_methods')->insert([
            [
                'focus_type' => 'experiential',
                'title' => 'การมอบหมายงานโครงการ / งานพิเศษ (Project Assignment)',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'focus_type' => 'experiential',
                'title' => 'การเรียนรู้จากการปฏิบัติงานจริง (On the Job Training/Learning: OJT)',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'focus_type' => 'social',
                'title' => 'การสอนงาน (Coaching)',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'focus_type' => 'social',
                'title' => 'การเป็นพี่เลี้ยง (Mentoring)',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'focus_type' => 'social',
                'title' => 'การเรียนรู้แบบกระบวนการกลุ่ม (Group Activity)',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'focus_type' => 'social',
                'title' => 'การเรียนรู้ผ่านการให้ข้อมูลป้อนกลับ (Feedback)',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'focus_type' => 'social',
                'title' => 'การเรียนรู้นอกสถานที่ (Field Trip)',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'focus_type' => 'social',
                'title' => 'ชุมชนนักปฏิบัติ (Community of Practice: CoPs)',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_learning_methods');
    }
};
