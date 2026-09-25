<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piles', function (Blueprint $table): void {
            $table->renameColumn('volume_bags', 'volume_kg');
        });

        Schema::table('amr_records', function (Blueprint $table): void {
            $table->renameColumn('volume_bags', 'volume_kg');
        });

        Schema::table('pmr_records', function (Blueprint $table): void {
            $table->renameColumn('volume_bags', 'volume_kg');
        });
    }

    public function down(): void
    {
        Schema::table('piles', function (Blueprint $table): void {
            $table->renameColumn('volume_kg', 'volume_bags');
        });

        Schema::table('amr_records', function (Blueprint $table): void {
            $table->renameColumn('volume_kg', 'volume_bags');
        });

        Schema::table('pmr_records', function (Blueprint $table): void {
            $table->renameColumn('volume_kg', 'volume_bags');
        });
    }
};
