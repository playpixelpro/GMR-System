<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table
                ->foreignId('pile_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table
                ->foreignId('pile_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->dropForeign(['pile_id']);
            $table->dropColumn('pile_id');
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->dropForeign(['pile_id']);
            $table->dropColumn('pile_id');
        });
    }
};
