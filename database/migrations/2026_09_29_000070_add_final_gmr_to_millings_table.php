<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record the official Final GMR used as the milling/recovery basis.
     *
     * The Final GMR is frozen at milling-assignment time from the pile's
     * approved-GMR record: it is the Central-Office approved GMR when one
     * exists, otherwise the system-recommended GMR.
     */
    public function up(): void
    {
        Schema::table('millings', function (Blueprint $table) {
            $table->decimal('final_gmr', 8, 2)->nullable()->after('target_volume_bags');
            $table->string('final_gmr_source', 20)->nullable()->after('final_gmr'); // recommended | co_approved
        });
    }

    public function down(): void
    {
        Schema::table('millings', function (Blueprint $table) {
            $table->dropColumn(['final_gmr', 'final_gmr_source']);
        });
    }
};
