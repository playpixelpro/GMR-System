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
            $table->string('establishment_type', 30)->default('test_milling')->after('rice_millers');
            $table->decimal('pmr_rate', 8, 2)->nullable()->after('establishment_type');
            $table->decimal('mri_rate', 8, 2)->nullable()->after('pmr_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->dropColumn(['establishment_type', 'pmr_rate', 'mri_rate']);
        });
    }
};
