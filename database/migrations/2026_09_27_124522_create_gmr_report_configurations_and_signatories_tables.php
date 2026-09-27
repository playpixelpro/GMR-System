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
        Schema::create('gmr_report_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('REPORT ON PRE-MILLING ACTIVITY');
            $table->string('subtitle')->default('QUALITY AND QUANTITY, AMR, PMR AND EMR/GMR');
            $table->string('region_text')->default('Region XII');
            $table->string('branch_text')->nullable();
            $table->string('paper_size', 50)->default('Long Bond');
            $table->decimal('custom_width', 8, 2)->nullable();
            $table->decimal('custom_height', 8, 2)->nullable();
            $table->string('custom_unit', 10)->default('in');
            $table->string('orientation', 20)->default('portrait');
            $table->decimal('margin_top', 8, 2)->default(0.5);
            $table->decimal('margin_right', 8, 2)->default(0.5);
            $table->decimal('margin_bottom', 8, 2)->default(0.5);
            $table->decimal('margin_left', 8, 2)->default(0.5);
            $table->string('margin_unit', 10)->default('in');
            $table->timestamps();
        });

        Schema::create('gmr_report_signatories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('position');
            $table->string('role_group')->nullable()->default('member');
            $table->integer('display_order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert initial default configuration based on reference report
        $now = now();
        DB::table('gmr_report_configurations')->insert([
            'title' => 'REPORT ON PRE-MILLING ACTIVITY',
            'subtitle' => 'QUALITY AND QUANTITY, AMR, PMR AND EMR/GMR',
            'region_text' => 'Region XII',
            'branch_text' => 'North Cotabato Branch',
            'paper_size' => 'Long Bond',
            'custom_width' => null,
            'custom_height' => null,
            'custom_unit' => 'in',
            'orientation' => 'portrait',
            'margin_top' => 0.50,
            'margin_right' => 0.50,
            'margin_bottom' => 0.50,
            'margin_left' => 0.50,
            'margin_unit' => 'in',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert default signatories based on reference report
        $defaultSignatories = [
            [
                'name' => 'DINDO O. QUITOR',
                'position' => 'Regional Economist',
                'role_group' => 'member',
                'display_order' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'MARIETES E. DISTOR',
                'position' => "Reg'l Standards & Quality Assurance Officer",
                'role_group' => 'member',
                'display_order' => 2,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'KRYZL G. FLORES',
                'position' => 'Regional Engineer',
                'role_group' => 'member',
                'display_order' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'JOANNE G. LISAY',
                'position' => 'Regional Accountant',
                'role_group' => 'member',
                'display_order' => 4,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ANTONIO ROSARIO D. LAGRIMAS II',
                'position' => 'Assistant Regional Manager/Chairperson',
                'role_group' => 'chairperson',
                'display_order' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Commission on Audit Representative',
                'position' => 'COA Representative',
                'role_group' => 'coa',
                'display_order' => 6,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'ANGELICA M. PELLIEN',
                'position' => 'Acting Regional Manager II',
                'role_group' => 'reviewer',
                'display_order' => 7,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('gmr_report_signatories')->insert($defaultSignatories);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gmr_report_signatories');
        Schema::dropIfExists('gmr_report_configurations');
    }
};
