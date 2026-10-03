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
        Schema::table('piles', function (Blueprint $table): void {
            $table->decimal('test_milling_volume_kg', 12, 3)->nullable()->after('volume_kg');
        });

        Schema::table('amr_records', function (Blueprint $table): void {
            $table->decimal('test_milling_volume_kg', 12, 3)->nullable()->after('volume_kg');
        });

        Schema::table('pmr_records', function (Blueprint $table): void {
            $table->decimal('test_milling_volume_kg', 12, 3)->nullable()->after('volume_kg');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piles', function (Blueprint $table): void {
            $table->dropColumn('test_milling_volume_kg');
        });

        Schema::table('amr_records', function (Blueprint $table): void {
            $table->dropColumn('test_milling_volume_kg');
        });

        Schema::table('pmr_records', function (Blueprint $table): void {
            $table->dropColumn('test_milling_volume_kg');
        });
    }
};
