<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->decimal('purity', 5, 2)->nullable()->after('variety');
            $table->decimal('mc', 5, 2)->nullable()->after('purity');
            $table->string('quality', 50)->nullable()->after('mc');
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->string('rice_millers', 191)->nullable()->after('variety');
        });
    }

    public function down(): void
    {
        Schema::table('amr_records', function (Blueprint $table) {
            $table->dropColumn(['purity', 'mc', 'quality']);
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->dropColumn('rice_millers');
        });
    }
};
