<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('piles', function (Blueprint $table) {
            $table->string('amr_status', 20)->nullable()->after('number');
            $table->string('pmr_status', 20)->nullable()->after('amr_status');
        });
    }

    public function down(): void
    {
        Schema::table('piles', function (Blueprint $table) {
            $table->dropColumn(['amr_status', 'pmr_status']);
        });
    }
};
