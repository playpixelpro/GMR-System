<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Time-series accomplishment entries logged for a milling (per pile).
     */
    public function up(): void
    {
        Schema::create('milling_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('milling_id')->constrained('millings')->cascadeOnDelete();
            $table->foreignId('pile_id')->constrained('piles')->cascadeOnDelete();

            $table->date('progress_date');
            $table->decimal('palay_input_kg', 12, 3)->nullable();
            $table->decimal('milled_rice_kg', 12, 3)->nullable();
            $table->decimal('recovery_percentage', 8, 2)->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['milling_id', 'progress_date']);
            $table->index('pile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milling_progress');
    }
};
