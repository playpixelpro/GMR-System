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
        Schema::create('amr_records', function (Blueprint $table) {
            $table->id();
            $table->string('warehouse_name', 100);
            $table->string('pile_number', 50);
            $table->string('variety', 100);
            $table->unsignedInteger('aged_months');
            $table->decimal('volume_bags', 12, 3);
            $table->string('rice_millers', 191);
            $table->unsignedInteger('trial_number');
            $table->decimal('palay_input_kg', 12, 2);
            $table->decimal('rice_recovery_kg', 12, 2);
            $table->timestamps();

            $table->index(['warehouse_name', 'pile_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amr_records');
    }
};
