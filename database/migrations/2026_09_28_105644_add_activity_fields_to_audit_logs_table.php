<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('role', 20)->nullable()->after('user_id');
            $table->foreignId('branch_id')->nullable()->after('role')
                ->constrained('branches')->nullOnDelete();
            $table->string('branch_name')->nullable()->after('branch_id');
            $table->string('module', 50)->nullable()->after('branch_name');
            $table->string('description')->nullable()->after('action');
            $table->string('ip_address', 45)->nullable()->after('description');

            $table->index('created_at');
            $table->index('module');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['module']);
            $table->dropForeign(['branch_id']);
            $table->dropColumn([
                'role',
                'branch_id',
                'branch_name',
                'module',
                'description',
                'ip_address',
            ]);
        });
    }
};
