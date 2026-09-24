<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50);
            $table->timestamps();

            $table->unique(['warehouse_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piles');
    }
};
