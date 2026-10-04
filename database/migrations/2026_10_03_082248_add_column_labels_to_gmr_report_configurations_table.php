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
        Schema::table('gmr_report_configurations', function (Blueprint $table) {
            $table->json('column_labels')->nullable()->after('visible_columns');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gmr_report_configurations', function (Blueprint $table) {
            $table->dropColumn('column_labels');
        });
    }
};
