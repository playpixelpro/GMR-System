<?php

namespace Tests\Feature;

use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class EmrDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_calculates_ranges_and_aggregates_from_filtered_piles(): void
    {
        [$branch, $warehouse, $otherWarehouse] = $this->createHierarchy();
        $this->createPile($warehouse, '1', 11522, 61.53, 62.22);
        $this->createPile($warehouse, '2', 12259, 62.66, 62.77);
        $this->createPile($otherWarehouse, '1', 7517, 63.25, 63.64);

        $response = $this->get(
            route('emr.index', [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
            ]),
        );

        $response
            ->assertOk()
            ->assertSee('Total Piles')
            ->assertSee('2', false)
            ->assertSee('475.620')
            ->assertSee('61.53% – 62.77%')
            ->assertDontSee('AMR ↕')
            ->assertDontSee('PMR ↕')
            ->assertDontSee('Expected Milling Recovery by Pile')
            ->assertDontSee('GID#4, MLANG BS - Pile 1');
    }

    public function test_questionable_values_are_visible_without_being_swapped(): void
    {
        [, $warehouse] = $this->createHierarchy();
        $this->createPile($warehouse, '9', 1000, 63.5, 62.9);

        $response = $this->get(route('emr.index'));

        $response
            ->assertOk()
            ->assertSee('63.50% – 62.90%')
            ->assertSee('QUESTIONABLE');
    }

    public function test_export_contains_numeric_emr_values_and_current_filter(): void
    {
        [$branch, $warehouse, $otherWarehouse] = $this->createHierarchy();
        $this->createPile($warehouse, '1', 11522, 61.53, 62.22);
        $this->createPile($otherWarehouse, '1', 7517, 63.25, 63.64);

        $response = $this->get(
            route('emr.export', [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
            ]),
        );

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        /** @var StreamedResponse $streamedResponse */
        $streamedResponse = $response->baseResponse;

        ob_start();
        $streamedResponse->getCallback()();
        $content = ob_get_clean();

        $this->assertTrue(str_starts_with($content, "\xEF\xBB\xBF"));
        $this->assertStringNotContainsString('EMR Lower', $content);
        $this->assertStringNotContainsString('EMR Upper', $content);
        $this->assertStringContainsString('EMR Display', $content);
        $this->assertStringContainsString('61.53% – 62.22%', $content);
        $this->assertStringNotContainsString('63.25', $content);
    }

    /**
     * @return array{0: Branch, 1: Warehouse, 2: Warehouse}
     */
    private function createHierarchy(): array
    {
        $branch = Branch::create(['name' => 'NORTH COTABATO']);
        $warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'GID#2, MLANG BS',
        ]);
        $otherWarehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'name' => 'GID#4, MLANG BS',
        ]);

        return [$branch, $warehouse, $otherWarehouse];
    }

    private function createPile(
        Warehouse $warehouse,
        string $number,
        int $volume,
        float $amr,
        float $pmr,
    ): Pile {
        $pile = Pile::create([
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'pile_number' => $number,
            'number' => $number,
            'variety' => 'PD',
            'purity' => 94.31,
            'aged_months' => 5,
            'quality' => 'GQA',
            'volume_kg' => $volume,
        ]);

        $recordDetails = [
            'warehouse_name' => $warehouse->name,
            'pile_number' => $number,
            'variety' => 'PD',
            'purity' => 94.31,
            'mc' => 14.0,
            'quality' => 'GQA',
            'aged_months' => 5,
            'volume_kg' => $volume,
        ];

        AmrRecord::create([
            ...$recordDetails,
            'pile_id' => $pile->id,
            'rice_millers' => 'Test Miller',
            'palay_input_kg' => 1000,
            'rice_recovery_kg' => $amr * 10,
            'milling_recovery' => $amr,
            'trial_number' => 1,
        ]);
        PmrRecord::create([
            ...$recordDetails,
            'pile_id' => $pile->id,
            'palay_input_kg' => 1000,
            'rice_recovery_kg' => $pmr * 10,
            'milling_recovery' => $pmr,
            'trial_number' => 1,
        ]);

        return $pile;
    }
}
