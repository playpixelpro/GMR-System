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
        Schema::table('pmr_records', function (Blueprint $table) {
            $table
                ->unsignedInteger('conduct_number')
                ->default(1)
                ->after('trial_number');
            $table
                ->string('status', 20)
                ->default('PENDING')
                ->after('conduct_number');
            $table
                ->boolean('included_in_computation')
                ->default(false)
                ->after('status');
            $table
                ->boolean('is_locked')
                ->default(false)
                ->after('included_in_computation');
            $table
                ->foreignId('created_by')
                ->nullable()
                ->after('is_locked')
                ->constrained('users')
                ->nullOnDelete();
            $table
                ->foreignId('confirmed_by')
                ->nullable()
                ->after('is_locked')
                ->constrained('users')
                ->nullOnDelete();
            $table
                ->foreignId('actioned_by')
                ->nullable()
                ->after('confirmed_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('actioned_by');
            $table->timestamp('actioned_at')->nullable()->after('confirmed_at');
            $table->index(['pile_id', 'conduct_number', 'status']);
        });

        DB::table('pmr_records')->update([
            'status' => 'RECOMMENDED',
            'included_in_computation' => true,
            'is_locked' => false,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pmr_records', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['confirmed_by']);
            $table->dropForeign(['actioned_by']);
            $table->dropIndex(
                'pmr_records_pile_id_conduct_number_status_index',
            );
            $table->dropColumn([
                'conduct_number',
                'status',
                'included_in_computation',
                'is_locked',
                'created_by',
                'confirmed_by',
                'actioned_by',
                'confirmed_at',
                'actioned_at',
            ]);
        });
    }
};
