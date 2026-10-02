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
        Schema::table('milling_progress', function (Blueprint $table) {
            $table->string('batch_number', 100)->nullable()->after('progress_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milling_progress', function (Blueprint $table) {
            $table->dropColumn('batch_number');
        });
    }
};
