<?php

namespace App\Services;

use App\Models\AmrCalculation;
use App\Models\AmrRecord;
use App\Models\Pile;
use App\Models\PmrCalculation;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin-only destructive cleanup of laboratory test data, piles, and
 * warehouses.
 *
 * Every deletion runs in a transaction and writes its audit entry first,
 * so the audit row (audit_logs.pile_id is a foreign key) always exists
 * while the referenced row is still valid, and a failed deletion rolls
 * its audit entry back with it. Snapshots captured in the audit metadata
 * preserve what was removed.
 */
class DataCleanupService
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly AmrCalculationService $amrCalculations,
        private readonly PmrCalculationService $pmrCalculations,
    ) {}

    /**
     * Delete a single AMR trial, renumber the remaining trials, and keep
     * the AMR calculation (or the pile's AMR state) consistent.
     */
    public function deleteAmrTrial(AmrRecord $trial): void
    {
        DB::transaction(function () use ($trial): void {
            $pile = $trial->pile;
            $snapshot = $this->trialSnapshot('amr', $trial, $pile);
            $trialNumber = $trial->trial_number;

            $trial->delete();

            if ($pile === null) {
                return;
            }

            $this->renumberTrials($pile, AmrRecord::class);

            if (AmrRecord::where('pile_id', $pile->id)->exists()) {
                $this->amrCalculations->calculateAndStoreForPile($pile);
            } else {
                AmrCalculation::where('pile_id', $pile->id)->delete();
                $pile->update(['amr_status' => null]);
            }

            $this->activity->log(
                'settings',
                'AMR_TRIAL_DELETED',
                "AMR laboratory test milling trial #{$trialNumber} deleted for pile {$this->pileLabel($pile)}",
                $trial,
                $snapshot,
            );
        });
    }

    /**
     * Delete a single PMR trial, renumber the remaining trials, and keep
     * the PMR calculation (or the pile's PMR state) consistent.
     */
    public function deletePmrTrial(PmrRecord $trial): void
    {
        DB::transaction(function () use ($trial): void {
            $pile = $trial->pile;
            $snapshot = $this->trialSnapshot('pmr', $trial, $pile);
            $trialNumber = $trial->trial_number;

            $trial->delete();

            if ($pile === null) {
                return;
            }

            $this->renumberTrials($pile, PmrRecord::class);

            if (PmrRecord::where('pile_id', $pile->id)->exists()) {
                $this->pmrCalculations->calculateAndStoreForPile($pile);
            } else {
                PmrCalculation::where('pile_id', $pile->id)->delete();
                $pile->update(['pmr_status' => null]);
            }

            $this->activity->log(
                'settings',
                'PMR_TRIAL_DELETED',
                "PMR laboratory test milling trial #{$trialNumber} deleted for pile {$this->pileLabel($pile)}",
                $trial,
                $snapshot,
            );
        });
    }

    /**
     * Delete every AMR trial of a pile together with its calculation
     * snapshot, returning the pile to an "AMR never entered" state.
     */
    public function deleteAllAmrTrials(Pile $pile): void
    {
        DB::transaction(function () use ($pile): void {
            $trials = $pile->amrRecords()->orderBy('trial_number')->get();

            $this->activity->log(
                'settings',
                'AMR_DATA_DELETED',
                "All AMR laboratory test milling data ({$trials->count()} trial(s)) deleted for pile {$this->pileLabel($pile)}",
                $pile,
                $this->bulkSnapshot('amr', $pile, $trials),
            );

            $trials->each->delete();
            AmrCalculation::where('pile_id', $pile->id)->delete();
            $pile->update(['amr_status' => null]);
        });
    }

    /**
     * Delete every PMR trial of a pile together with its calculation
     * snapshot, returning the pile to an "PMR never entered" state.
     */
    public function deleteAllPmrTrials(Pile $pile): void
    {
        DB::transaction(function () use ($pile): void {
            $trials = $pile->pmrRecords()->orderBy('trial_number')->get();

            $this->activity->log(
                'settings',
                'PMR_DATA_DELETED',
                "All PMR laboratory test milling data ({$trials->count()} trial(s)) deleted for pile {$this->pileLabel($pile)}",
                $pile,
                $this->bulkSnapshot('pmr', $pile, $trials),
            );

            $trials->each->delete();
            PmrCalculation::where('pile_id', $pile->id)->delete();
            $pile->update(['pmr_status' => null]);
        });
    }

    /**
     * Delete a pile after its test data has been emptied by the caller's
     * guard. Millings, milling progress, and GMR-approval pile snapshots
     * are removed by their database-level cascades.
     */
    public function deletePile(Pile $pile): void
    {
        DB::transaction(function () use ($pile): void {
            $snapshot = $this->pileSnapshot($pile);

            $this->activity->log(
                'settings',
                'PILE_DELETED',
                "Pile {$this->pileLabel($pile)} deleted with all of its data",
                $pile,
                $snapshot,
            );

            // Defensive: the controller already requires these to be empty.
            AmrRecord::where('pile_id', $pile->id)->delete();
            PmrRecord::where('pile_id', $pile->id)->delete();
            AmrCalculation::where('pile_id', $pile->id)->delete();
            PmrCalculation::where('pile_id', $pile->id)->delete();

            $pile->delete();
        });
    }

    /**
     * Delete an empty warehouse (the caller's guard requires zero piles).
     */
    public function deleteWarehouse(Warehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse): void {
            $snapshot = [
                'warehouse_id' => $warehouse->id,
                'name' => $warehouse->name,
                'branch_id' => $warehouse->branch_id,
                'branch' => $warehouse->branch?->name,
                'pile_count' => $warehouse->piles()->count(),
            ];

            $this->activity->log(
                'settings',
                'WAREHOUSE_DELETED',
                "Warehouse '{$warehouse->name}' deleted",
                $warehouse,
                $snapshot,
            );

            $warehouse->delete();
        });
    }

    /**
     * Sequentially renumber the remaining trials of a pile, mirroring the
     * behavior of the existing data-entry trial deletion.
     *
     * @param  class-string<AmrRecord|PmrRecord>  $model
     */
    private function renumberTrials(Pile $pile, string $model): void
    {
        $remaining = $model::where('pile_id', $pile->id)
            ->orderBy('trial_number')
            ->get();

        foreach ($remaining as $index => $trial) {
            $expected = $index + 1;
            if ($trial->trial_number !== $expected) {
                $trial->update(['trial_number' => $expected]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function trialSnapshot(string $formType, AmrRecord|PmrRecord $trial, ?Pile $pile): array
    {
        return [
            'form_type' => $formType,
            'trial_id' => $trial->getKey(),
            'trial_number' => $trial->trial_number,
            'conduct_number' => $trial->conduct_number,
            'test_milling_date' => $trial->test_milling_date?->toDateString(),
            'palay_input_kg' => $trial->palay_input_kg !== null ? (float) $trial->palay_input_kg : null,
            'rice_recovery_kg' => $trial->rice_recovery_kg !== null ? (float) $trial->rice_recovery_kg : null,
            'milling_recovery' => $trial->milling_recovery !== null ? (float) $trial->milling_recovery : null,
            'pile_id' => $pile?->getKey(),
            'pile_number' => $pile?->pile_number ?? $pile?->number,
        ];
    }

    /**
     * @param  Collection<int, AmrRecord|PmrRecord>  $trials
     * @return array<string, mixed>
     */
    private function bulkSnapshot(string $formType, Pile $pile, Collection $trials): array
    {
        return [
            'form_type' => $formType,
            'pile_id' => $pile->getKey(),
            'pile_number' => $pile->pile_number ?? $pile->number,
            'warehouse' => $pile->warehouse?->name,
            'branch' => $pile->warehouse?->branch?->name ?? $pile->branch?->name,
            'gmr_status' => $pile->gmr_status,
            'deleted_trial_count' => $trials->count(),
            'trials' => $trials
                ->map(fn (AmrRecord|PmrRecord $trial): array => [
                    'trial_id' => $trial->getKey(),
                    'trial_number' => $trial->trial_number,
                    'test_milling_date' => $trial->test_milling_date?->toDateString(),
                    'palay_input_kg' => $trial->palay_input_kg !== null ? (float) $trial->palay_input_kg : null,
                    'rice_recovery_kg' => $trial->rice_recovery_kg !== null ? (float) $trial->rice_recovery_kg : null,
                    'milling_recovery' => $trial->milling_recovery !== null ? (float) $trial->milling_recovery : null,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Full identity snapshot of a pile, captured before deletion.
     *
     * @return array<string, mixed>
     */
    private function pileSnapshot(Pile $pile): array
    {
        $volumeKg = $pile->volume_kg !== null ? (float) $pile->volume_kg : null;

        return [
            'pile_id' => $pile->getKey(),
            'pile_number' => $pile->pile_number ?? $pile->number,
            'number' => $pile->number,
            'warehouse_id' => $pile->warehouse_id,
            'warehouse' => $pile->warehouse?->name,
            'branch_id' => $pile->branch_id ?? $pile->warehouse?->branch_id,
            'branch' => $pile->branch?->name ?? $pile->warehouse?->branch?->name,
            'variety' => $pile->variety,
            'quality' => $pile->quality,
            'volume_kg' => $volumeKg,
            'volume_bags' => $volumeKg !== null ? round($volumeKg / 50, 3) : null,
            'gmr_status' => $pile->gmr_status,
            'gmr_locked_at' => $pile->gmr_locked_at?->toIso8601String(),
            'amr_status' => $pile->amr_status,
            'pmr_status' => $pile->pmr_status,
            'final_gmr' => $pile->finalGmr(),
            'amr_trial_count' => $pile->amrRecords()->count(),
            'pmr_trial_count' => $pile->pmrRecords()->count(),
            'milling_count' => $pile->millings()->count(),
        ];
    }

    private function pileLabel(Pile $pile): string
    {
        return (string) ($pile->pile_number ?? $pile->number ?? '#'.$pile->getKey());
    }
}
