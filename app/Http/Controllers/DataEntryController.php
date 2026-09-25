<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDataEntryRequest;
use App\Http\Requests\StorePileRequest;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Requests\UpdateTrialRequest;
use App\Models\AmrRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Pile;
use App\Models\PmrRecord;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AmrCalculationService;
use App\Services\PmrCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
                $sharedRecord =
                    $pile->amrRecords->first() ?? $pile->pmrRecords->first();
                $sharedData = [
                    'variety' => $pile->variety ?? $sharedRecord?->variety,
                    'purity' => $pile->purity ?? $sharedRecord?->purity,
                    'mc' => $pile->mc ?? $sharedRecord?->mc,
                    'quality' => $pile->quality ?? $sharedRecord?->quality,
                    'aged' => $pile->aged_months ?? $sharedRecord?->aged_months,
                    'volume' => $pile->volume_kg ?? $sharedRecord?->volume_kg,
                ];

                return [
                    'id' => $pile->id,
                    'warehouse_id' => $pile->warehouse_id,
                    'branch_id' => $pile->branch_id ?? $pile->warehouse?->branch_id,
                    'number' => $pile->pile_number ?? $pile->number,
                    'pile_number' => $pile->pile_number ?? $pile->number,
                    'shared' => $sharedData,
                    'amr' => [
                        ...$sharedData,
                        'rice_millers' => $pile->amrRecords->first()?->rice_millers ??
                            $pile->pmrRecords->first()?->rice_millers,
                        'trials' => $pile->amrRecords
                            ->pluck('trial_number')
                            ->values(),
                        'records' => $pile->amrRecords
                            ->map(
                                fn (AmrRecord $record): array => [
                                    'id' => $record->id,
                                    'trial_number' => $record->trial_number,
                                    'test_milling_date' => $record->test_milling_date?->format(
                                        'Y-m-d',
                                    ),
                                    'rice_millers' => $record->rice_millers,
                                    'palay_input' => $record->palay_input_kg,
                                    'rice_recovery' => $record->rice_recovery_kg,
                                    'conduct_number' => $record->conduct_number,
                                    'status' => $record->status,
                                    'is_locked' => $record->is_locked,
                                    'has_retest_history' => $pile->amrRecords->contains(
                                        'status',
                                        'RETEST',
                                    ),
                                ],
                            )
                            ->values(),
                    ],
                    'pmr' => [
                        ...$sharedData,
                        'rice_millers' => $pile->pmrRecords->first()?->rice_millers ??
                            $pile->amrRecords->first()?->rice_millers,
                        'trials' => $pile->pmrRecords
                            ->pluck('trial_number')
                            ->values(),
                        'records' => $pile->pmrRecords
                            ->map(
                                fn (PmrRecord $record): array => [
                                    'id' => $record->id,
                                    'trial_number' => $record->trial_number,
                                    'test_milling_date' => $record->test_milling_date?->format(
                                        'Y-m-d',
                                    ),
                                    'palay_input' => $record->palay_input_kg,
                                    'rice_recovery' => $record->rice_recovery_kg,
                                    'recovery_rate' => $record->milling_recovery !== null
                                            ? (float) $record->milling_recovery
                                            : $record->recovery_rate_percentage,
                                    'milling_recovery' => $record->milling_recovery !== null
                                            ? (float) $record->milling_recovery
                                            : null,
                                    'conduct_number' => $record->conduct_number,
                                    'status' => $record->status,
                                    'is_locked' => $record->is_locked,
                                    'has_retest_history' => $pile->pmrRecords->contains(
                                        'status',
                                        'RETEST',
                                    ),
                                ],
                            )
                            ->values(),
                    ],
                ];
            });

        return view('form.create', [
            'formType' => request()->query('type')
                ? request()->string('type')->toString()
                : null,
            'branches' => Branch::query()->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->orderBy('name')->get(),
            'piles' => $piles,
        ]);
    }

    public function createWarehouse(
        StoreWarehouseRequest $request,
    ): JsonResponse {
        $warehouse = Warehouse::firstOrCreate([
            'branch_id' => $request->validated('branch_id'),
            'name' => trim($request->validated('name')),
        ]);

        return response()->json(
            [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'branch_id' => $warehouse->branch_id,
            ],
            $warehouse->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function update(
        UpdateTrialRequest $request,
        string $formType,
        int $record,
    ): JsonResponse|RedirectResponse {
        abort_unless(in_array($formType, ['amr', 'pmr'], true), 404);

        $trial =
            $formType === 'amr'
                ? AmrRecord::findOrFail($record)
                : PmrRecord::findOrFail($record);
        $validated = $request->validated();
        $this->authorizeTrialEdit($trial);

        if ($formType === 'pmr') {
            $palayInput =
                isset($validated['palay_input']) &&
                $validated['palay_input'] !== '' &&
                $validated['palay_input'] !== null
                    ? $validated['palay_input']
                    : null;
            $riceRecovery =
                isset($validated['rice_recovery']) &&
                $validated['rice_recovery'] !== '' &&
                $validated['rice_recovery'] !== null
                    ? $validated['rice_recovery']
                    : null;

            if (
                $palayInput !== null &&
                $riceRecovery !== null &&
                (float) $palayInput > 0
            ) {
                $millingRecovery = round(
                    ((float) $riceRecovery / (float) $palayInput) * 100,
                    2,
                );
            } elseif (
                isset($validated['recovery_rate']) &&
                $validated['recovery_rate'] !== '' &&
                $validated['recovery_rate'] !== null
            ) {
                $millingRecovery = round(
                    (float) $validated['recovery_rate'],
                    2,
                );
            } else {
                $millingRecovery = $trial->milling_recovery;
            }

            $trial->update([
                'test_milling_date' => $validated['test_milling_date'] ?? null,
                'palay_input_kg' => $palayInput,
                'rice_recovery_kg' => $riceRecovery,
                'milling_recovery' => $millingRecovery,
            ]);
        } else {
            $trial->update([
                'rice_millers' => $validated['rice_millers'] ?? null,
                'test_milling_date' => $validated['test_milling_date'] ?? null,
                'palay_input_kg' => $validated['palay_input'],
                'rice_recovery_kg' => $validated['rice_recovery'],
            ]);
        }

        if ($formType === 'amr' && $trial->pile) {
            $this->amrCalculationService->calculateAndStoreForPile(
                $trial->pile,
            );
        } elseif ($formType === 'pmr' && $trial->pile) {
            $this->pmrCalculationService->calculateAndStoreForPile(
                $trial->pile,
            );
        }

        AuditLog::record('DATA_EDITED', $trial, [
            'form_type' => $formType,
            'within_editing_window' => ! Auth::check() || Auth::user()?->role !== 'STAFF',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => strtoupper($formType).' trial updated successfully.',
                'record' => [
                    'id' => $trial->id,
                    'trial_number' => $trial->trial_number,
                    'test_milling_date' => $trial->test_milling_date?->format(
                        'Y-m-d',
                    ),
                    'rice_millers' => $trial->rice_millers,
                    'palay_input' => $trial->palay_input_kg,
                    'rice_recovery' => $trial->rice_recovery_kg,
                    'recovery_rate' => $trial->milling_recovery !== null
                            ? (float) $trial->milling_recovery
                            : null,
                    'milling_recovery' => $trial->milling_recovery !== null
                            ? (float) $trial->milling_recovery
                            : null,
                ],
            ]);
        }

        return redirect()
            ->route('records.create', ['type' => $formType])
            ->with(
                'status',
                strtoupper($formType).' trial updated successfully.',
            );
    }

    public function destroy(
        Request $request,
        string $formType,
        int $record,
    ): JsonResponse {
        return $this->destroyTrial($formType, $record);
    }

    public function destroyTrial(string $formType, int $record): JsonResponse
    {
        abort_unless(in_array($formType, ['amr', 'pmr'], true), 404);

        $trial =
            $formType === 'amr'
                ? AmrRecord::findOrFail($record)
                : PmrRecord::findOrFail($record);

        if ($trial->is_locked || $trial->status === 'RETEST') {
            abort(422, 'Locked test history cannot be deleted.');
        }
        $this->authorizeTrialEdit($trial);
        $pile = $trial->pile;
        $trial->delete();
        AuditLog::record('DATA_DELETED', $trial, ['form_type' => $formType]);

        if ($formType === 'amr' && $pile) {
            $this->amrCalculationService->calculateAndStoreForPile($pile);
        } elseif ($formType === 'pmr' && $pile) {
            $this->pmrCalculationService->calculateAndStoreForPile($pile);
        }

        return response()->json([
            'message' => strtoupper($formType).' trial deleted successfully.',
        ]);
    }

    public function updatePileDetails(
        Request $request,
        Pile $pile,
    ): JsonResponse {
        if ($request->filled('volume')) {
            $request->merge([
                'volume' => str_replace(
                    ',',
                    '',
                    (string) $request->input('volume'),
                ),
            ]);
        }

        $validated = $request->validate([
            'variety' => ['required', 'string', 'max:100'],
            'purity' => ['required', 'numeric', 'between:0,100'],
            'aged' => ['required', 'integer', 'min:0'],
            'mc' => ['required', 'numeric', 'between:0,100'],
            'quality' => [
                'required',
                Rule::in([
                    'good',
                    'fair',
                    'treated',
                    'treated fair',
                    'treated_fair',
                    'poor',
                    'gqa',
                    'premium',
                ]),
            ],
            'volume' => ['required', 'numeric', 'min:0'],
        ]);

        $hasSavedRecords =
            AmrRecord::query()->where('pile_id', $pile->id)->exists() ||
            PmrRecord::query()->where('pile_id', $pile->id)->exists() ||
            $pile->variety !== null;

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
                'volume_kg' => $validated['volume'],
            ];

            $pile->update($sharedDetails);

            AmrRecord::query()
                ->where('pile_id', $pile->id)
                ->update($sharedDetails);
            PmrRecord::query()
                ->where('pile_id', $pile->id)
                ->update($sharedDetails);
        });

        return response()->json([
            'message' => 'Pile details updated successfully.',
        ]);
    }

    public function updatePileStatus(
        Request $request,
        Pile $pile,
    ): RedirectResponse {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user?->hasRole('RMEC', 'ADMINISTRATOR'), 403);
        $validated = $request->validate([
            'form_type' => ['required', Rule::in(['amr', 'pmr'])],
            'action' => [
                'required',
                Rule::in(['confirm', 'recommend', 'retest']),
            ],
        ]);
        $model =
            $validated['form_type'] === 'amr'
                ? AmrRecord::class
                : PmrRecord::class;
        $latestConduct = (int) $model::where('pile_id', $pile->id)
            ->max('conduct_number');
        $tests = $model::where('pile_id', $pile->id)
            ->where('conduct_number', $latestConduct)
            ->get();
        abort_if(
            $tests->isEmpty(),
            422,
            'No test conduct exists for this pile.',
        );
        abort_if(
            $tests->contains('is_locked', true),
            422,
            'This test conduct is locked.',
        );
        $status =
            $validated['action'] === 'recommend'
                ? 'RECOMMENDED'
                : ($validated['action'] === 'retest'
                    ? 'RETEST'
                    : 'PENDING');

        DB::transaction(function () use (
            $tests,
            $pile,
            $validated,
            $status,
        ): void {
            foreach ($tests as $test) {
                $test->update([
                    'status' => $status,
                    'included_in_computation' => $status === 'RECOMMENDED',
                    'is_locked' => $status !== 'PENDING',
                    'confirmed_by' => Auth::id(),
                    'actioned_by' => $status === 'PENDING' ? null : Auth::id(),
                    'confirmed_at' => now(),
                    'actioned_at' => $status === 'PENDING' ? null : now(),
                ]);
                AuditLog::record(strtoupper($validated['action']), $test, [
                    'form_type' => $validated['form_type'],
                    'conduct_number' => $test->conduct_number,
                    'new_status' => $status,
                    'included_in_computation' => $status === 'RECOMMENDED',
                ]);
            }
            $pile->update([
                $validated['form_type'].'_status' => strtolower($status),
            ]);
        });

        return back()->with(
            'status',
            strtoupper($validated['form_type']).
                ' conduct marked as '.
                $status.
                '.',
        );
    }

    private function authorizeTrialEdit(AmrRecord|PmrRecord $trial): void
    {
        if ($trial->is_locked) {
            abort(422, 'This test conduct is locked and cannot be edited.');
        }

        if (! Auth::check()) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();
        if ($user->hasRole('ADMINISTRATOR')) {
            return;
        }

        abort_unless(
            $user->hasRole('STAFF', 'RMEC') &&
                (int) $trial->created_by === (int) $user->id &&
                $trial->created_at?->greaterThanOrEqualTo(now()->subDay()),
            403,
            'Staff can edit their own data only within 24 hours of saving it.',
        );
    }

    public function createPile(StorePileRequest $request): JsonResponse
    {
        $warehouse = Warehouse::with('branch')->findOrFail(
            $request->validated('warehouse_id'),
        );
        $pileNumber = trim($request->validated('number'));

        $pile = Pile::where('warehouse_id', $warehouse->id)
            ->where(function ($query) use ($pileNumber) {
                $query
                    ->where('pile_number', $pileNumber)
                    ->orWhere('number', $pileNumber);
            })
            ->first();

        if (! $pile) {
            $pile = Pile::create([
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'pile_number' => $pileNumber,
                'number' => $pileNumber,
            ]);
        }

        return response()->json(
            [
                'id' => $pile->id,
                'number' => $pile->pile_number ?? $pile->number,
                'pile_number' => $pile->pile_number ?? $pile->number,
                'warehouse_id' => $pile->warehouse_id,
                'branch_id' => $warehouse->branch_id,
            ],
            $pile->wasRecentlyCreated ? 201 : 200,
        );
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

            $pileNumber = trim(
                (string) ($validated['new_pile_number'] ??
                    ($validated['pile_number'] ?? '')),
            );
            if ($pileNumber === '' && ! empty($validated['pile_id'])) {
                $selected = Pile::find($validated['pile_id']);
                $pileNumber =
                    (string) ($selected?->pile_number ??
                        ($selected?->number ?? ''));
            }

            // 1. Find the pile using Branch + Warehouse + Pile Number
            $pile = Pile::where('warehouse_id', $warehouse->id)
                ->where(function ($query) use ($branch) {
                    $query
                        ->where('branch_id', $branch->id)
                        ->orWhereNull('branch_id');
                })
                ->where(function ($query) use ($pileNumber) {
                    $query
                        ->where('pile_number', $pileNumber)
                        ->orWhere('number', $pileNumber);
                })
                ->first();

            $sharedPileData = [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'number' => $pileNumber,
                'pile_number' => $pileNumber,
                'variety' => $validated['variety'],
                'purity' => $validated['purity'],
                'aged_months' => $validated['aged'],
                'mc' => $validated['mc'],
                'quality' => $validated['quality'],
                'volume_kg' => $validated['volume'],
            ];

            // 2. If pile does not exist, create it with all pile details
            // 3. If pile already exists, use existing pile (NEVER duplicate)
            if (! $pile) {
                $pile = Pile::create($sharedPileData);
            } else {
                $pile->update($sharedPileData);
            }

            $recordModel =
                $validated['form_type'] === 'amr'
                    ? AmrRecord::class
                    : PmrRecord::class;

            $maximumTrials = 3;

            $latestConduct =
                (int) ($recordModel::where('pile_id', $pile->id)
                    ->max('conduct_number') ?? 0);
            $latestConductRecords = $recordModel::where('pile_id', $pile->id)
                ->where('conduct_number', $latestConduct)
                ->get();
            if (
                $latestConduct > 0 &&
                $latestConductRecords->every(
                    fn ($record): bool => $record->is_locked,
                )
            ) {
                $latestConduct++;
                $latestConductRecords = collect();
            }
            $conductNumber = max(1, $latestConduct);
            $existingRecords = $latestConductRecords->keyBy('trial_number');
            $existingTrialNumbers = $existingRecords
                ->keys()
                ->map(fn ($trial): int => (int) $trial)
                ->all();

            $usedTrialNumbers = $existingTrialNumbers;
            $trials = $validated['trials'];
            foreach ($trials as &$trial) {
                if (
                    empty($trial['trial_number']) ||
                    ! is_numeric($trial['trial_number'])
                ) {
                    $next = 1;
                    while (in_array($next, $usedTrialNumbers, true)) {
                        $next++;
                    }
                    $trial['trial_number'] = $next;
                    $usedTrialNumbers[] = $next;
                } else {
                    $usedTrialNumbers[] = (int) $trial['trial_number'];
                }
            }
            unset($trial);
            $validated['trials'] = $trials;

            $submittedTrialNumbers = collect($validated['trials'])
                ->pluck('trial_number')
                ->map(fn ($trial): int => (int) $trial)
                ->all();

            if (
                count($submittedTrialNumbers) !==
                count(array_unique($submittedTrialNumbers))
            ) {
                throw ValidationException::withMessages([
                    'trials' => 'Each trial number can only be submitted once.',
                    'no_of_trial' => 'Each trial number can only be submitted once.',
                ]);
            }

            $newTrialsCount = collect($submittedTrialNumbers)
                ->filter(fn ($t) => ! in_array($t, $existingTrialNumbers, true))
                ->count();
            if (
                count($existingTrialNumbers) + $newTrialsCount >
                $maximumTrials
            ) {
                throw ValidationException::withMessages([
                    'trials' => strtoupper($validated['form_type']).
                        ' cannot exceed '.
                        $maximumTrials.
                        ' trials for this pile.',
                    'no_of_trial' => strtoupper($validated['form_type']).
                        ' cannot exceed '.
                        $maximumTrials.
                        ' trials for this pile.',
                ]);
            }

            // Synchronize all existing records for this pile in BOTH AMR and PMR tables
            AmrRecord::query()
                ->where('pile_id', $pile->id)
                ->update([
                    'warehouse_name' => $warehouse->name,
                    'pile_number' => $pile->pile_number ?? $pile->number,
                    'variety' => $validated['variety'],
                    'purity' => $validated['purity'],
                    'mc' => $validated['mc'],
                    'quality' => $validated['quality'],
                    'aged_months' => $validated['aged'],
                    'volume_kg' => $validated['volume'],
                ]);
            PmrRecord::query()
                ->where('pile_id', $pile->id)
                ->update([
                    'warehouse_name' => $warehouse->name,
                    'pile_number' => $pile->pile_number ?? $pile->number,
                    'variety' => $validated['variety'],
                    'purity' => $validated['purity'],
                    'mc' => $validated['mc'],
                    'quality' => $validated['quality'],
                    'aged_months' => $validated['aged'],
                    'volume_kg' => $validated['volume'],
                ]);

            // Save or update trials for this assessment
            foreach ($validated['trials'] as $trial) {
                $trialNumber = (int) $trial['trial_number'];
                $existing = $existingRecords->get($trialNumber);

                $palayInput =
                    isset($trial['palay_input']) &&
                    $trial['palay_input'] !== '' &&
                    $trial['palay_input'] !== null
                        ? (float) $trial['palay_input']
                        : null;
                $riceRecovery =
                    isset($trial['rice_recovery']) &&
                    $trial['rice_recovery'] !== '' &&
                    $trial['rice_recovery'] !== null
                        ? (float) $trial['rice_recovery']
                        : null;

                if ($validated['form_type'] === 'pmr') {
                    if (
                        $palayInput !== null &&
                        $riceRecovery !== null &&
                        $palayInput > 0
                    ) {
                        $millingRecovery = round(
                            ($riceRecovery / $palayInput) * 100,
                            2,
                        );
                    } elseif (
                        isset($trial['recovery_rate']) &&
                        $trial['recovery_rate'] !== '' &&
                        $trial['recovery_rate'] !== null
                    ) {
                        $millingRecovery = round(
                            (float) $trial['recovery_rate'],
                            2,
                        );
                    } else {
                        $millingRecovery = null;
                    }
                } else {
                    $millingRecovery =
                        $palayInput !== null &&
                        $palayInput > 0 &&
                        $riceRecovery !== null
                            ? round(($riceRecovery / $palayInput) * 100, 2)
                            : null;
                }

                $trialData = [
                    'pile_id' => $pile->id,
                    'conduct_number' => $conductNumber,
                    'status' => 'PENDING',
                    'included_in_computation' => false,
                    'is_locked' => false,
                    'created_by' => Auth::id(),
                    'warehouse_name' => $warehouse->name,
                    'pile_number' => $pile->pile_number ?? $pile->number,
                    'variety' => $validated['variety'],
                    'purity' => $validated['purity'],
                    'mc' => $validated['mc'],
                    'quality' => $validated['quality'],
                    'aged_months' => $validated['aged'],
                    'volume_kg' => $validated['volume'],
                    'rice_millers' => $validated['form_type'] === 'amr'
                            ? $trial['rice_millers'] ?? null
                            : null,
                    'trial_number' => $trialNumber,
                    'test_milling_date' => $trial['test_milling_date'],
                    'palay_input_kg' => $palayInput,
                    'rice_recovery_kg' => $riceRecovery,
                    'milling_recovery' => $millingRecovery,
                ];

                if ($existing && ! $existing->is_locked) {
                    $existing->update($trialData);
                } else {
                    $recordModel::create($trialData);
                }
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
