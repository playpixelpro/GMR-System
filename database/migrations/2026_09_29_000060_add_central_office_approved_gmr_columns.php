<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Central-Office approved GMR columns.
     *
     * - gmr_approvals.co_approval_memo_no: the memorandum reference issued by
     *   the Central Office when approving the submission.
     * - gmr_approval_piles.co_approved_gmr: the per-pile GMR value officially
     *   approved by the Central Office. When present it takes precedence over
     *   the system-recommended GMR (stored in `gmr`) as the Final GMR.
     */
    public function up(): void
    {
        Schema::table('gmr_approvals', function (Blueprint $table) {
            $table->string('co_approval_memo_no')->nullable()->after('reference_number');
        });

        Schema::table('gmr_approval_piles', function (Blueprint $table) {
            $table->decimal('co_approved_gmr', 8, 2)->nullable()->after('gmr');
        });
    }

    public function down(): void
    {
        Schema::table('gmr_approval_piles', function (Blueprint $table) {
            $table->dropColumn('co_approved_gmr');
        });

        Schema::table('gmr_approvals', function (Blueprint $table) {
            $table->dropColumn('co_approval_memo_no');
        });
    }
};
