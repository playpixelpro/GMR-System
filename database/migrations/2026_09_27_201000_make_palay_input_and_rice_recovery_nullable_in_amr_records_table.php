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
        Schema::table('amr_records', function (Blueprint $table): void {
            $table->decimal('palay_input_kg', 12, 2)->nullable()->change();
            $table->decimal('rice_recovery_kg', 12, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table): void {
            $table->decimal('palay_input_kg', 12, 2)->nullable(false)->change();
            $table->decimal('rice_recovery_kg', 12, 2)->nullable(false)->change();
        });
    }
};
