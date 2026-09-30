<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AMR test-milling fields need the same 6-decimal precision as PMR
     * so users can encode fractional kg values (e.g. 0.123456 kg).
     */
    public function up(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->decimal('palay_input_kg', 12, 6)->nullable()->change();
            $table->decimal('rice_recovery_kg', 12, 6)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->decimal('palay_input_kg', 12, 2)->nullable()->change();
            $table->decimal('rice_recovery_kg', 12, 2)->nullable()->change();
        });
    }
};
