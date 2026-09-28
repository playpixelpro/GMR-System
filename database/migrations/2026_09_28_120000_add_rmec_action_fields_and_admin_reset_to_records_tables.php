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
        Schema::table('amr_records', function (Blueprint $table) {
            $table->text('action_remarks')->nullable()->after('actioned_at');
            $table->foreignId('reset_by')->nullable()->after('action_remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('reset_at')->nullable()->after('reset_by');
            $table->text('reset_reason')->nullable()->after('reset_at');
            $table->string('previous_action', 50)->nullable()->after('reset_reason');
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->text('action_remarks')->nullable()->after('actioned_at');
            $table->foreignId('reset_by')->nullable()->after('action_remarks')->constrained('users')->nullOnDelete();
            $table->timestamp('reset_at')->nullable()->after('reset_by');
            $table->text('reset_reason')->nullable()->after('reset_at');
            $table->string('previous_action', 50)->nullable()->after('reset_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->dropForeign(['reset_by']);
            $table->dropColumn([
                'action_remarks',
                'reset_by',
                'reset_at',
                'reset_reason',
                'previous_action',
            ]);
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->dropForeign(['reset_by']);
            $table->dropColumn([
                'action_remarks',
                'reset_by',
                'reset_at',
                'reset_reason',
                'previous_action',
            ]);
        });
    }
};
