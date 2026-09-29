<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laboratory (PMR) test-milling samples can be very small — e.g. a
     * 0.063 kg palay input — so the AMR-scale 2-decimal precision is not
     * enough. Widen the PMR weight columns to six decimal places.
     */
    public function up(): void
    {
        Schema::table('pmr_records', function (Blueprint $table) {
            $table->decimal('palay_input_kg', 12, 6)->nullable()->change();
            $table->decimal('rice_recovery_kg', 12, 6)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pmr_records', function (Blueprint $table) {
            $table->decimal('palay_input_kg', 12, 2)->nullable()->change();
            $table->decimal('rice_recovery_kg', 12, 2)->nullable()->change();
        });
    }
};
