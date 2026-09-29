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
        Schema::create('millers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('category', 20)->nullable();
            $table->decimal('capacity_12h_bags', 12, 3)->nullable();
            $table->timestamps();
        });

        $this->backfillFromExistingRecords();
    }

    /**
     * Reverse the migrations.
     *
     * Dropping the table also removes the backfilled master list of millers.
     */
    public function down(): void
    {
        Schema::dropIfExists('millers');
    }

    /**
     * Seed the master list with miller names that already exist on historical
     * records so the combobox is useful immediately after deploying.
     *
     * Category and capacity are unknown for historical millers, so they stay
     * null until a profile is completed.
     */
    private function backfillFromExistingRecords(): void
    {
        $sources = [
            ['amr_records', 'rice_millers'],
            ['pmr_records', 'rice_millers'],
            ['millings', 'miller'],
        ];

        /** @var array<string, string> $names Lowercase key => original spelling. */
        $names = [];

        foreach ($sources as [$table, $column]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->pluck($column) as $value) {
                $name = trim((string) $value);
                if ($name === '') {
                    continue;
                }
                $names[mb_strtolower($name)] ??= $name;
            }
        }

        $now = now();
        foreach ($names as $name) {
            DB::table('millers')->insertOrIgnore([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
