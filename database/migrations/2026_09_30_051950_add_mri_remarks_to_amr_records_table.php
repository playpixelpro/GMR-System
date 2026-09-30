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
        Schema::table('amr_records', function (Blueprint $table) {
            $table->text('mri_remarks')->nullable()->after('mri_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->dropColumn('mri_remarks');
        });
    }
};
