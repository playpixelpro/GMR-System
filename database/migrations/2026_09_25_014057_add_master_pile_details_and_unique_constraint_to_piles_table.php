<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('piles', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->cascadeOnDelete();
            $table->string('pile_number', 50)->nullable()->after('warehouse_id');
            $table->string('variety', 100)->nullable()->after('pile_number');
            $table->decimal('purity', 5, 2)->nullable()->after('variety');
            $table->unsignedInteger('aged_months')->nullable()->after('purity');
            $table->decimal('mc', 5, 2)->nullable()->after('aged_months');
            $table->string('quality', 50)->nullable()->after('mc');
            $table->decimal('volume_bags', 12, 3)->nullable()->after('quality');
        });

        // Backfill data for existing piles
        $piles = DB::table('piles')->get();
        foreach ($piles as $pile) {
            $warehouse = DB::table('warehouses')->where('id', $pile->warehouse_id)->first();
            $branchId = $warehouse?->branch_id;
            $pileNumber = $pile->number;

            $amr = DB::table('amr_records')->where('pile_id', $pile->id)->first();
            $pmr = DB::table('pmr_records')->where('pile_id', $pile->id)->first();
            $source = $amr ?? $pmr;

            DB::table('piles')->where('id', $pile->id)->update([
                'branch_id' => $branchId,
                'pile_number' => $pileNumber,
                'variety' => $source?->variety,
                'purity' => $source?->purity,
                'aged_months' => $source?->aged_months,
                'mc' => $source?->mc,
                'quality' => $source?->quality,
                'volume_bags' => $source?->volume_bags,
            ]);
        }

        Schema::table('piles', function (Blueprint $table) {
            $table->unique(['branch_id', 'warehouse_id', 'pile_number'], 'piles_branch_warehouse_pile_unique');
        });

        Schema::table('amr_records', function (Blueprint $table) {
            $table->string('warehouse_name', 100)->nullable()->change();
            $table->string('pile_number', 50)->nullable()->change();
            $table->string('variety', 100)->nullable()->change();
            $table->unsignedInteger('aged_months')->nullable()->change();
            $table->decimal('volume_bags', 12, 3)->nullable()->change();
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->string('warehouse_name', 100)->nullable()->change();
            $table->string('pile_number', 50)->nullable()->change();
            $table->string('variety', 100)->nullable()->change();
            $table->unsignedInteger('aged_months')->nullable()->change();
            $table->decimal('volume_bags', 12, 3)->nullable()->change();
            $table->decimal('purity', 5, 2)->nullable()->change();
            $table->decimal('mc', 5, 2)->nullable()->change();
            $table->string('quality', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('piles', function (Blueprint $table) {
            $table->dropUnique('piles_branch_warehouse_pile_unique');
            $table->dropForeign(['branch_id']);
            $table->dropColumn([
                'branch_id',
                'pile_number',
                'variety',
                'purity',
                'aged_months',
                'mc',
                'quality',
                'volume_bags',
            ]);
        });
    }
};
