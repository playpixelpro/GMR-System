<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDataEntryRequest;
use App\Http\Requests\StorePileRequest;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateTrialRequest;
use App\Models\AmrRecord;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\Warehouse;
use App\Services\AmrCalculationService;
use App\Services\PmrCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DataEntryController extends Controller
{
    public function __construct(
        protected AmrCalculationService $amrCalculationService,
        protected PmrCalculationService $pmrCalculationService,
    ) {}

    public function create(): View
    {
        $piles = Pile::query()
            ->with(['amrRecords', 'pmrRecords', 'warehouse.branch'])
            ->orderBy('number')
            ->get()
            ->map(function (Pile $pile): array {
                $sharedRecord = $pile->amrRecords->first() ?? $pile->pmrRecords->first();
                $sharedData = [
                    'variety' => $sharedRecord?->variety,
                    'purity' => $sharedRecord?->purity,
                    'mc' => $sharedRecord?->mc,
                    'quality' => $sharedRecord?->quality,
                    'aged' => $sharedRecord?->aged_months,
                    'volume' => $sharedRecord?->volume_bags,
                ];

                return [
                    'id' => $pile->id,
                    'warehouse_id' => $pile->warehouse_id,
                    'branch_id' => $pile->warehouse?->branch_id,
                    'number' => $pile->number,
                    'shared' => $sharedData,
                    'amr' => [
                        ...$sharedData,
                        'rice_millers' => $pile->amrRecords->first()?->rice_millers ?? $pile->pmrRecords->first()?->rice_millers,
                        'trials' => $pile->amrRecords->pluck('trial_number')->values(),
                        'records' => $pile->amrRecords->map(fn (AmrRecord $record): array => [
                            'id' => $record->id,
                            'trial_number' => $record->trial_number,
                            'test_milling_date' => $record->test_milling_date?->format('Y-m-d'),
                            'rice_millers' => $record->rice_millers,
                            'palay_input' => $record->palay_input_kg,
                            'rice_recovery' => $record->rice_recovery_kg,
                        ])->values(),
                    ],
                    'pmr' => [
                        ...$sharedData,
                        'rice_millers' => $pile->pmrRecords->first()?->rice_millers ?? $pile->amrRecords->first()?->rice_millers,
                        'trials' => $pile->pmrRecords->pluck('trial_number')->values(),
                        'records' => $pile->pmrRecords->map(fn (PmrRecord $record): array => [
                            'id' => $record->id,
                            'trial_number' => $record->trial_number,
                            'test_milling_date' => $record->test_milling_date?->format('Y-m-d'),
                            'palay_input' => $record->palay_input_kg,
                            'rice_recovery' => $record->rice_recovery_kg,
                        ])->values(),
                    ],
                ];
            });

        return view('form.create', [
            'formType' => request()->query('type') ? request()->string('type')->toString() : null,
            'branches' => Branch::query()->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'piles' => $piles,
        ]);
    }

    public function createWarehouse(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::firstOrCreate([
            'branch_id' => $request->validated('branch_id'),
            'name' => trim($request->validated('name')),
        ]);

        return response()->json([
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'branch_id' => $warehouse->branch_id,
        ], $warehouse->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateTrialRequest $request, string $formType, int $record): JsonResponse|RedirectResponse
    {
        abort_unless(in_array($formType, ['amr', 'pmr'], true), 404);

        $trial = $formType === 'amr'
            ? AmrRecord::findOrFail($record)
            : PmrRecord::findOrFail($record);
        $validated = $request->validated();

        $trial->update([
            'rice_millers' => $formType === 'amr' ? ($validated['rice_millers'] ?? null) : null,
            'test_milling_date' => $validated['test_milling_date'] ?? null,
            'palay_input_kg' => $validated['palay_input'],
            'rice_recovery_kg' => $validated['rice_recovery'],
        ]);

        if ($formType === 'amr' && $trial->pile) {
            $this->amrCalculationService->calculateAndStoreForPile($trial->pile);
        } elseif ($formType === 'pmr' && $trial->pile) {
            $this->pmrCalculationService->calculateAndStoreForPile($trial->pile);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => strtoupper($formType).' trial updated successfully.',
            ]);
        }

        return redirect()
            ->route('records.create', ['type' => $formType])
            ->with('status', strtoupper($formType).' trial updated successfully.');
    }

    public function destroyTrial(string $formType, int $record): JsonResponse
    {
        abort_unless(in_array($formType, ['amr', 'pmr'], true), 404);

        $trial = $formType === 'amr'
            ? AmrRecord::findOrFail($record)
            : PmrRecord::findOrFail($record);

        $pile = $trial->pile;
        $trial->delete();

        if ($formType === 'amr' && $pile) {
            $this->amrCalculationService->calculateAndStoreForPile($pile);
        } elseif ($formType === 'pmr' && $pile) {
            $this->pmrCalculationService->calculateAndStoreForPile($pile);
        }

        return response()->json([
            'message' => strtoupper($formType).' trial deleted successfully.',
        ]);
    }

    public function updatePileDetails(Request $request, Pile $pile): JsonResponse
    {
        if ($request->filled('volume')) {
            $request->merge([
                'volume' => str_replace(',', '', (string) $request->input('volume')),
            ]);
        }

        $validated = $request->validate([
            'variety' => ['required', 'string', 'max:100'],
            'purity' => ['required', 'numeric', 'between:0,100'],
            'aged' => ['required', 'integer', 'min:0'],
            'mc' => ['required', 'numeric', 'between:0,100'],
            'quality' => ['required', Rule::in(['good', 'treated fair', 'treated_fair', 'poor', 'gqa', 'premium', 'fair'])],
            'volume' => ['required', 'numeric', 'min:0'],
        ]);

        $hasSavedRecords = AmrRecord::query()->where('pile_id', $pile->id)->exists()
            || PmrRecord::query()->where('pile_id', $pile->id)->exists();

        if (! $hasSavedRecords) {
            throw ValidationException::withMessages([
                'pile_id' => 'This pile has no saved trial data to update.',
            ]);
        }

        DB::transaction(function () use ($pile, $validated): void {
            $sharedDetails = [
                'variety' => $validated['variety'],
                'purity' => $validated['purity'],
                'aged_months' => $validated['aged'],
                'mc' => $validated['mc'],
                'quality' => $validated['quality'],
                'volume_bags' => $validated['volume'],
            ];

            AmrRecord::query()->where('pile_id', $pile->id)->update($sharedDetails);
            PmrRecord::query()->where('pile_id', $pile->id)->update($sharedDetails);
        });

        return response()->json([
            'message' => 'Pile details updated successfully.',
        ]);
    }

    public function updatePileStatus(Request $request, Pile $pile): RedirectResponse
    {
        $validated = $request->validate([
            'form_type' => ['required', Rule::in(['amr', 'pmr'])],
            'action' => ['required', Rule::in(['recommend', 'retest', 'approved'])],
        ]);

        $pile->update([
            $validated['form_type'].'_status' => $validated['action'],
        ]);

        return back()->with(
            'status',
            strtoupper($validated['form_type']).' pile marked as '.$validated['action'].'.',
        );
    }

    public function createPile(StorePileRequest $request): JsonResponse
    {
        $pile = Pile::create([
            'warehouse_id' => $request->validated('warehouse_id'),
            'number' => trim($request->validated('number')),
        ]);

        return response()->json([
            'id' => $pile->id,
            'number' => $pile->number,
            'warehouse_id' => $pile->warehouse_id,
            'branch_id' => $request->validated('branch_id'),
        ], 201);
    }

    public function store(StoreDataEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $branch = isset($validated['new_branch_name'])
                ? Branch::firstOrCreate([
                    'name' => trim($validated['new_branch_name']),
                ])
                : Branch::findOrFail($validated['branch_id']);

            $warehouse = isset($validated['new_warehouse_name'])
                ? $branch->warehouses()->firstOrCreate([
                    'name' => trim($validated['new_warehouse_name']),
                ])
                : $branch->warehouses()->findOrFail($validated['warehouse_id']);

            $pile = isset($validated['pile_id'])
                ? $warehouse->piles()->findOrFail($validated['pile_id'])
                : $warehouse->piles()->firstOrCreate([
                    'number' => trim($validated['new_pile_number'] ?? $validated['pile_number']),
                ]);

            $recordModel = $validated['form_type'] === 'amr'
                ? AmrRecord::class
                : PmrRecord::class;
            $statusColumn = $validated['form_type'].'_status';
            $maximumTrials = $validated['form_type'] === 'amr' ? 3 : 5;
            $existingTrialNumbers = $recordModel::where('pile_id', $pile->id)
                ->pluck('trial_number')
                ->map(fn ($trial): int => (int) $trial)
                ->all();
            $submittedTrialNumbers = collect($validated['trials'])
                ->pluck('trial_number')
                ->map(fn ($trial): int => (int) $trial)
                ->all();

            if ($pile->{$statusColumn} !== null) {
                throw ValidationException::withMessages([
                    'trials' => 'This pile is locked for '.strtoupper($validated['form_type']).' after the '.$pile->{$statusColumn}.' action.',
                ]);
            }

            if (count($submittedTrialNumbers) !== count(array_unique($submittedTrialNumbers))) {
                throw ValidationException::withMessages([
                    'trials' => 'Each trial number can only be submitted once.',
                ]);
            }

            if (count($existingTrialNumbers) + count($submittedTrialNumbers) > $maximumTrials) {
                throw ValidationException::withMessages([
                    'trials' => strtoupper($validated['form_type']).' cannot exceed '.$maximumTrials.' trials for this pile.',
                ]);
            }

            if (array_intersect($existingTrialNumbers, $submittedTrialNumbers) !== []) {
                throw ValidationException::withMessages([
                    'trials' => 'One or more selected trials have already been completed for this pile.',
                ]);
            }

            $shared = [
                'pile_id' => $pile->id,
                'warehouse_name' => $warehouse->name,
                'pile_number' => $pile->number,
                'variety' => $validated['variety'],
                'purity' => $validated['purity'],
                'mc' => $validated['mc'],
                'quality' => $validated['quality'],
                'aged_months' => $validated['aged'],
                'volume_bags' => $validated['volume'],
            ];

            // Synchronize all existing records for this pile in BOTH AMR and PMR tables
            AmrRecord::query()->where('pile_id', $pile->id)->update([
                'warehouse_name' => $warehouse->name,
                'pile_number' => $pile->number,
                'variety' => $validated['variety'],
                'purity' => $validated['purity'],
                'mc' => $validated['mc'],
                'quality' => $validated['quality'],
                'aged_months' => $validated['aged'],
                'volume_bags' => $validated['volume'],
            ]);
            PmrRecord::query()->where('pile_id', $pile->id)->update([
                'warehouse_name' => $warehouse->name,
                'pile_number' => $pile->number,
                'variety' => $validated['variety'],
                'purity' => $validated['purity'],
                'mc' => $validated['mc'],
                'quality' => $validated['quality'],
                'aged_months' => $validated['aged'],
                'volume_bags' => $validated['volume'],
            ]);

            foreach ($validated['trials'] as $trial) {
                $recordData = [
                    ...$shared,
                    'rice_millers' => $trial['rice_millers'] ?? null,
                    'trial_number' => $trial['trial_number'],
                    'test_milling_date' => $trial['test_milling_date'],
                    'palay_input_kg' => $trial['palay_input'],
                    'rice_recovery_kg' => $trial['rice_recovery'],
                ];

                $recordModel::create($recordData);
            }

            if ($validated['form_type'] === 'amr') {
                $this->amrCalculationService->calculateAndStoreForPile($pile);
            } elseif ($validated['form_type'] === 'pmr') {
                $this->pmrCalculationService->calculateAndStoreForPile($pile);
            }
        });

        return redirect()
            ->route('records.create', ['type' => $validated['form_type']])
            ->with(
                'status',
                strtoupper($validated['form_type']).
                    ' trial saved successfully.',
            );
    }
}
