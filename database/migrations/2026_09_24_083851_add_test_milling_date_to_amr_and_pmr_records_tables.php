<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->date('test_milling_date')->nullable()->after('trial_number');
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->date('test_milling_date')->nullable()->after('trial_number');
        });
    }

    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->dropColumn('test_milling_date');
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->dropColumn('test_milling_date');
        });
    }
};
