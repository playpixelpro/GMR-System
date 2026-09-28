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
        Schema::table('users', function (Blueprint $table) {
            $table
                ->timestamp('registration_expires_at')
                ->nullable()
                ->after('activated_at');
            $table
                ->timestamp('registration_confirmed_at')
                ->nullable()
                ->after('registration_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'registration_expires_at',
                'registration_confirmed_at',
            ]);
        });
    }
};
