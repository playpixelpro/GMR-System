<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add nullable GMR-approval / lock columns to the piles table.
     *
     * All columns are nullable so existing piles remain "unlocked" and
     * every existing controller/service behaves identically.
     */
    public function up(): void
    {
        Schema::table('piles', function (Blueprint $table) {
            $table->string('gmr_status', 20)->nullable()->after('pmr_status');
            $table->foreignId('gmr_approval_pile_id')
                ->nullable()
                ->constrained('gmr_approval_piles')
                ->cascadeOnDelete()
                ->after('gmr_status');
            $table->timestamp('gmr_locked_at')->nullable()->after('gmr_approval_pile_id');
        });
    }

    public function down(): void
    {
        Schema::table('piles', function (Blueprint $table) {
            $table->dropForeign(['gmr_approval_pile_id']);
            $table->dropColumn(['gmr_status', 'gmr_approval_pile_id', 'gmr_locked_at']);
        });
    }
};
