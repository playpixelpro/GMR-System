<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rice-milling assignments. One milling covers a single pile; a pile may
     * have many millings over time. Only the pile's frozen volume is carried in.
     */
    public function up(): void
    {
        Schema::create('millings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('pile_id')->constrained('piles')->cascadeOnDelete();

            $table->string('miller', 191)->nullable();
            $table->string('reference_number')->nullable();
            $table->string('status', 20)->default('assigned'); // assigned | ongoing | completed | cancelled

            // Frozen from pile volume_kg at assignment (the only pile data we want)
            $table->decimal('target_volume_kg', 12, 3)->nullable();
            $table->decimal('target_volume_bags', 12, 3)->nullable();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['pile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('millings');
    }
};
