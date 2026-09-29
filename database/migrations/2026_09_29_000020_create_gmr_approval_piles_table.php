<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot of piles included in a GMR submission, with the frozen
     * AMR/PMR/EMR/GMR/volume snapshot at the time of submission.
     */
    public function up(): void
    {
        Schema::create('gmr_approval_piles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gmr_approval_id')->constrained('gmr_approvals')->cascadeOnDelete();
            $table->foreignId('pile_id')->constrained('piles')->cascadeOnDelete();

            // Frozen computation snapshot
            $table->decimal('amr', 8, 2)->nullable();
            $table->decimal('pmr', 8, 2)->nullable();
            $table->string('emr_display')->nullable();
            $table->decimal('gmr', 8, 2)->nullable();

            // Frozen pile details (the only pile data carried forward)
            $table->decimal('volume_kg', 12, 3)->nullable();
            $table->decimal('volume_bags', 12, 3)->nullable();
            $table->string('quality', 50)->nullable();
            $table->string('variety', 100)->nullable();

            $table->timestamps();

            $table->unique(['gmr_approval_id', 'pile_id']);
            $table->index('pile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gmr_approval_piles');
    }
};
