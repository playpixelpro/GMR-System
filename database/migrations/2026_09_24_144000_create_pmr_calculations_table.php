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
        Schema::create('pmr_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pile_id')->nullable()->constrained('piles')->nullOnDelete();
            $table->string('group_key', 191)->nullable()->index();
            $table->json('trial_inputs');
            $table->json('trial_recoveries');
            $table->decimal('median', 8, 4)->nullable();
            $table->decimal('lower_limit', 8, 4)->nullable();
            $table->decimal('upper_limit', 8, 4)->nullable();
            $table->json('outlier_results');
            $table->unsignedTinyInteger('valid_trial_count')->default(0);
            $table->unsignedTinyInteger('outlier_count')->default(0);
            $table->decimal('standard_deviation', 8, 4)->nullable();
            $table->decimal('coefficient_of_variation', 8, 4)->nullable();
            $table->boolean('is_outlier_valid')->default(false);
            $table->boolean('is_cv_valid')->default(false);
            $table->boolean('is_valid')->default(false);
            $table->string('status', 50)->default('INCOMPLETE');
            $table->string('status_message', 255)->nullable();
            $table->decimal('pmr_rate', 8, 2)->nullable();
            $table->json('snapshot');
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['pile_id', 'status']);
        });

        Schema::table('pmr_records', function (Blueprint $table) {
            $table->decimal('milling_recovery', 8, 2)->nullable()->after('rice_recovery_kg');
            $table->boolean('is_outlier')->default(false)->after('milling_recovery');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pmr_records', function (Blueprint $table) {
            $table->dropColumn(['milling_recovery', 'is_outlier']);
        });

        Schema::dropIfExists('pmr_calculations');
    }
};
