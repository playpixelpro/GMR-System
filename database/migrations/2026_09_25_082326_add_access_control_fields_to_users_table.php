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
            $table->string('role', 20)->default('STAFF')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table
                ->boolean('must_change_password')
                ->default(false)
                ->after('is_active');
            $table
                ->timestamp('temporary_password_expires_at')
                ->nullable()
                ->after('must_change_password');
            $table
                ->timestamp('activated_at')
                ->nullable()
                ->after('temporary_password_expires_at');
            $table->timestamp('disabled_at')->nullable()->after('activated_at');
            $table->index(['role', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_is_active_index');
            $table->dropColumn([
                'role',
                'is_active',
                'must_change_password',
                'temporary_password_expires_at',
                'activated_at',
                'disabled_at',
            ]);
        });
    }
};
