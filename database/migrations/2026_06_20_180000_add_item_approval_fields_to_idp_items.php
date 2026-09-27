<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('reject_comment')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['submitted_at', 'approved_at', 'rejected_at', 'reject_comment']);
        });
    }
};
