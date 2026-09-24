<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('branches')->insertOrIgnore([
            ['name' => 'North Cotabato', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'South Cotabato', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sultan Kudarat', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('branches')
            ->whereIn('name', ['North Cotabato', 'South Cotabato', 'Sultan Kudarat'])
            ->delete();
    }
};
