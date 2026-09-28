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
        Schema::table('piles', function (Blueprint $table) {
            $table->decimal('aged_months', 8, 2)->nullable()->change();
        });

        Schema::table('amr_records', function (Blueprint $table) {
            $table->decimal('aged_months', 8, 2)->nullable()->change();
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->decimal('aged_months', 8, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piles', function (Blueprint $table) {
            $table->unsignedInteger('aged_months')->nullable()->change();
        });

        Schema::table('amr_records', function (Blueprint $table) {
            $table->unsignedInteger('aged_months')->change();
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->unsignedInteger('aged_months')->change();
        });
    }
};
