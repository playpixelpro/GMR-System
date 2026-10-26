<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLockedPileVolumeCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $staffUser;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['name' => 'North Cotabato Test']);
        $this->warehouse = Warehouse::firstOrCreate([
            'branch_id' => $this->branch->id,
            'name' => 'GID 5 KIDAPAWAN TEST',
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'administrator',
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $this->staffUser = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $this->branch->id,
            'must_change_password' => false,
            'is_active' => true,
        ]);
    }

    public function test_staff_cannot_update_pile_details_when_test_milling_is_locked(): void
    {
        $pile = Pile::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => 'PILE 2A',
            'number' => 'PILE 2A',
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged_months' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume_kg' => 10030,
            'amr_status' => 'recommended',
        ]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 1,
            'status' => 'RECOMMENDED',
            'is_locked' => true,
            'volume_kg' => 10030,
        ]);

        $response = $this->actingAs($this->staffUser)->patchJson(route('piles.details.update', $pile), [
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume' => 10500,
        ]);

        $response->assertForbidden();
        $this->assertSame(10030.0, (float) $pile->fresh()->volume_kg);
    }

    public function test_admin_can_update_pile_details_when_test_milling_is_locked_within_same_volume_tier(): void
    {
        $pile = Pile::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => 'PILE 2A',
            'number' => 'PILE 2A',
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged_months' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume_kg' => 10030,
            'amr_status' => 'recommended',
        ]);

        $amrRecord = AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 1,
            'status' => 'RECOMMENDED',
            'is_locked' => true,
            'volume_kg' => 10030,
            'mri_rate' => 2.5,
            'milling_recovery' => 65.5,
        ]);

        $response = $this->actingAs($this->adminUser)->patchJson(route('piles.details.update', $pile), [
            'variety' => 'PD1-A-CORRECTED',
            'purity' => 96.0,
            'aged' => 6,
            'mc' => 12.0,
            'quality' => 'good',
            'volume' => 12500,
        ]);

        $response->assertOk();
        $freshPile = $pile->fresh();
        $this->assertSame('PD1-A-CORRECTED', $freshPile->variety);
        $this->assertSame(12500.0, (float) $freshPile->volume_kg);

        // Child record volume is synchronized
        $freshRecord = $amrRecord->fresh();
        $this->assertSame(12500.0, (float) $freshRecord->volume_kg);

        // Test milling results and status remain intact
        $this->assertTrue($freshRecord->is_locked);
        $this->assertSame('RECOMMENDED', $freshRecord->status);
        $this->assertSame(2.5, (float) $freshRecord->mri_rate);
    }

    public function test_admin_blocked_from_crossing_50000_threshold_upward_when_test_is_locked(): void
    {
        $pile = Pile::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => 'PILE 2A',
            'number' => 'PILE 2A',
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged_months' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume_kg' => 10030,
            'amr_status' => 'recommended',
        ]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 1,
            'status' => 'RECOMMENDED',
            'is_locked' => true,
            'volume_kg' => 10030,
        ]);

        // Attempting to change from 10,030 kg (< 50k) to 60,000 kg (>= 50k)
        $response = $this->actingAs($this->adminUser)->patchJson(route('piles.details.update', $pile), [
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume' => 60000,
            'test_milling_volume' => 500,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('volume');
        $this->assertStringContainsString('50,000 kg threshold', $response->json('message'));
        $this->assertSame(10030.0, (float) $pile->fresh()->volume_kg);
    }

    public function test_admin_blocked_from_crossing_50000_threshold_downward_when_test_is_locked(): void
    {
        $pile = Pile::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => 'PILE BIG',
            'number' => 'PILE BIG',
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged_months' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume_kg' => 75000,
            'test_milling_volume_kg' => 1000,
            'amr_status' => 'recommended',
        ]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 1,
            'status' => 'RECOMMENDED',
            'is_locked' => true,
            'volume_kg' => 75000,
        ]);

        // Attempting to change from 75,000 kg (>= 50k) to 25,000 kg (< 50k)
        $response = $this->actingAs($this->adminUser)->patchJson(route('piles.details.update', $pile), [
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume' => 25000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('volume');
        $this->assertStringContainsString('50,000 kg threshold', $response->json('message'));
        $this->assertSame(75000.0, (float) $pile->fresh()->volume_kg);
    }

    public function test_admin_can_update_volume_when_volume_remains_above_50000_kg(): void
    {
        $pile = Pile::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'pile_number' => 'PILE BIG',
            'number' => 'PILE BIG',
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged_months' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume_kg' => 75000,
            'test_milling_volume_kg' => 1000,
            'amr_status' => 'recommended',
        ]);

        AmrRecord::factory()->create([
            'pile_id' => $pile->id,
            'conduct_number' => 1,
            'trial_number' => 1,
            'status' => 'RECOMMENDED',
            'is_locked' => true,
            'volume_kg' => 75000,
        ]);

        $response = $this->actingAs($this->adminUser)->patchJson(route('piles.details.update', $pile), [
            'variety' => 'PD1-A',
            'purity' => 95.20,
            'aged' => 5,
            'mc' => 12.50,
            'quality' => 'good',
            'volume' => 80000,
            'test_milling_volume' => 1200,
        ]);

        $response->assertOk();
        $this->assertSame(80000.0, (float) $pile->fresh()->volume_kg);
        $this->assertSame(1200.0, (float) $pile->fresh()->test_milling_volume_kg);
    }
}
