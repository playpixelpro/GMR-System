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
        Schema::table('millings', function (Blueprint $table): void {
            $table->foreignId('miller_id')
                ->nullable()
                ->after('miller')
                ->constrained('millers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('millings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('miller_id');
        });
    }
};
