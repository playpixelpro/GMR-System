<?php

namespace App\Http\Controllers;

use App\Concerns\GuardsGmrLockedPiles;
use App\Http\Requests\StoreDataEntryRequest;
use App\Http\Requests\UpdatePileDetailsRequest;
use App\Http\Requests\UpdateTrialRequest;
use App\Models\AmrRecord;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Miller;
use App\Models\Pile;
use App\Models\PmrRecord;
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
    use GuardsGmrLockedPiles;

    public function __construct(
        protected AmrCalculationService $amrCalculationService,
        protected PmrCalculationService $pmrCalculationService,
    ) {}

    public function create(Request $request): View
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $isStaff = (bool) $currentUser?->hasRole('STAFF');
        $userBranchId = $isStaff ? $currentUser?->branch_id : null;
        $assignedBranch = $userBranchId ? Branch::find($userBranchId) : null;

        $branchesQuery = Branch::orderBy('name');
        $warehousesQuery = Warehouse::orderBy('name');
        $pilesQuery = Pile::with(['amrRecords', 'pmrRecords', 'warehouse', 'pmrCalculation'])->orderBy('number');

        if ($userBranchId) {
            $branchesQuery->where('id', $userBranchId);
            $warehousesQuery->where('branch_id', $userBranchId);
            $pilesQuery->where(function ($query) use ($userBranchId): void {
                $query->where('branch_id', $userBranchId)
                    ->orWhereHas('warehouse', fn ($w) => $w->where('branch_id', $userBranchId));
            });
        }

        $branches = $branchesQuery->get();
        $warehouses = $warehousesQuery->get();
        $piles = $pilesQuery->get()
            ->map(function (Pile $pile): array {
                $sharedData = [
                    'variety' => $pile->variety,
                    'purity' => $pile->purity,
                    'aged' => $pile->aged_months,
                    'mc' => $pile->mc,
                    'quality' => $pile->quality,
                    'volume' => $pile->volume_kg,
                ];

                $amrRecords = $pile->amrRecords;
                $amrLatestConduct = $amrRecords->max('conduct_number') ?? 1;
                $amrRecordsForConduct = $amrRecords->where('conduct_number', $amrLatestConduct);
                $amrLatestIsRetest = $amrRecordsForConduct->contains('status', 'RETEST');
                $amrActiveRecords = $amrLatestIsRetest ? collect() : $amrRecordsForConduct;

                $pmrRecords = $pile->pmrRecords;
                $pmrLatestConduct = $pmrRecords->max('conduct_number') ?? 1;
                $pmrRecordsForConduct = $pmrRecords->where('conduct_number', $pmrLatestConduct);
                $pmrLatestIsRetest = $pmrRecordsForConduct->contains('status', 'RETEST');
                $pmrActiveRecords = $pmrLatestIsRetest ? collect() : $pmrRecordsForConduct;

                $pilePmrRate = null;
                if ($pile->pmrCalculation?->pmr_rate !== null) {
                    $pilePmrRate = (float) $pile->pmrCalculation->pmr_rate;
                } elseif ($pmrActiveRecords->isNotEmpty()) {
                    $validPmr = $pmrActiveRecords
                        ->filter(fn (PmrRecord $r) => (float) $r->recovery_rate_percentage > 0)
                        ->map(fn (PmrRecord $r) => (float) $r->recovery_rate_percentage);
                    $pilePmrRate = $validPmr->isNotEmpty() ? round((float) $validPmr->avg(), 2) : null;
                }

                return [
                    'id' => $pile->id,
                    'warehouse_id' => $pile->warehouse_id,
                    'branch_id' => $pile->branch_id ?? $pile->warehouse?->branch_id,
                    'number' => $pile->pile_number ?? $pile->number,
                    'pile_number' => $pile->pile_number ?? $pile->number,
                    'is_gmr_locked' => $pile->isGmrLocked(),
                    'shared' => $sharedData,
                    'pmr_rate' => $pilePmrRate,
                    'amr' => [
                        ...$sharedData,
                        'pmr_rate' => $pilePmrRate,
                        'rice_millers' => $amrRecords->first()?->rice_millers,
                        'trials' => $amrActiveRecords
                            ->pluck('trial_number')
                            ->values(),
                        'records' => $amrActiveRecords
                            ->map(
                                fn (AmrRecord $record): array => [
                                    'id' => $record->id,
                                    'trial_number' => $record->trial_number,
                                    'establishment_type' => $record->establishment_type,
                                    'pmr_rate' => $record->pmr_rate !== null ? (float) $record->pmr_rate : null,
                                    'mri_rate' => $record->mri_rate !== null ? (float) $record->mri_rate : null,
                                    'mri_remarks' => $record->mri_remarks,
                                    'test_milling_date' => $record->test_milling_date?->format(
                                        'Y-m-d',
                                    ),
                                    'rice_millers' => $record->rice_millers,
                                    'palay_input' => $record->palay_input_kg,
                                    'rice_recovery' => $record->rice_recovery_kg,
                                    'recovery_rate' => $record->milling_recovery !== null
                                            ? (float) $record->milling_recovery
                                            : $record->milling_recovery_percentage,
                                    'milling_recovery' => $record->milling_recovery !== null
                                            ? (float) $record->milling_recovery
                                            : null,
                                    'conduct_number' => $record->conduct_number,
                                    'status' => $record->status,
                                    'is_locked' => $record->is_locked,
                                    'can_edit' => Auth::check() && Auth::user()->canEditRecord($record) && ! $record->is_locked,
                                    'has_retest_history' => $amrRecords->contains(
                                        'status',
                                        'RETEST',
                                    ),
                                ],
                            )
                            ->values(),
                    ],
                    'pmr' => [
                        ...$sharedData,
                        'rice_millers' => $pmrRecords->first()?->rice_millers ??
                            $amrRecords->first()?->rice_millers,
                        'trials' => $pmrActiveRecords
                            ->pluck('trial_number')
                            ->values(),
                        'records' => $pmrActiveRecords
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
                                    'can_edit' => Auth::check() && Auth::user()->canEditRecord($record) && ! $record->is_locked,
                                    'has_retest_history' => $pmrRecords->contains(
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
            'branches' => $branches,
            'warehouses' => $warehouses,
            'piles' => $piles,
            'formType' => $request->query('type') ?? $request->query('form_type'),
            'isStaff' => $isStaff,
            'userBranchId' => $userBranchId,
            'assignedBranch' => $assignedBranch,
        ]);
    }

    public function updatePileDetails(
        UpdatePileDetailsRequest $request,
        Pile $pile,
    ): JsonResponse {
        $this->ensurePileNotGmrLocked($pile, $request, 'edited');

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id) {
            $pileBranchId = $pile->branch_id ?? $pile->warehouse?->branch_id;
            abort_unless($pileBranchId === $currentUser->branch_id, 403, 'You can only edit piles in your assigned branch.');
        }

        $validated = $request->validated();

        $updateData = [
            'variety' => $validated['variety'],
            'purity' => $validated['purity'],
            'aged_months' => $validated['aged'],
            'mc' => $validated['mc'],
            'quality' => $validated['quality'],
            'volume_kg' => $validated['volume'],
        ];

        DB::transaction(function () use ($pile, $updateData): void {
            $pile->update($updateData);

            $childUpdateData = [
                'variety' => $updateData['variety'],
                'purity' => $updateData['purity'],
                'aged_months' => $updateData['aged_months'],
                'mc' => $updateData['mc'],
                'quality' => $updateData['quality'],
                'volume_kg' => $updateData['volume_kg'],
            ];

            $pile->amrRecords()->update($childUpdateData);
            $pile->pmrRecords()->update($childUpdateData);
        });

        AuditLog::record('DATA_EDITED', $pile, [
            'details' => $updateData,
            'user_id' => Auth::id(),
        ], 'pile', 'Pile details updated');

        return response()->json([
            'message' => 'Pile details updated successfully.',
            'pile' => [
                'id' => $pile->id,
                ...$updateData,
            ],
        ]);
    }

    public function update(
        UpdateTrialRequest $request,
        string $formType,
        int $recordId,
    ): JsonResponse {
        $validated = $request->validated();
        $recordModel =
            $formType === 'amr' ? AmrRecord::class : PmrRecord::class;
        $trial = $recordModel::findOrFail($recordId);

        $this->ensureRecordPileNotGmrLocked($trial, $request, 'edited');

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id) {
            $pileBranchId = $trial->pile?->branch_id ?? $trial->pile?->warehouse?->branch_id;
            abort_unless($pileBranchId === $currentUser->branch_id, 403, 'You can only modify records in your assigned branch.');
        }

        if ($trial->is_locked) {
            abort(403, 'This test milling record is locked and cannot be edited.');
        }

        if (! Auth::user()->canEditRecord($trial)) {
            abort(
                403,
                'You can only edit records within 24 hours of creation, or when edit mode is unlocked by an administrator.',
            );
        }

        $palayInput =
            isset($validated['palay_input']) &&
            $validated['palay_input'] !== '' &&
            $validated['palay_input'] !== null
                ? (float) $validated['palay_input']
                : null;
        $riceRecovery =
            isset($validated['rice_recovery']) &&
            $validated['rice_recovery'] !== '' &&
            $validated['rice_recovery'] !== null
                ? (float) $validated['rice_recovery']
                : null;

        if (
            $palayInput !== null &&
            $riceRecovery !== null &&
            $palayInput > 0
        ) {
            $millingRecovery = round(
                ((float) $riceRecovery / (float) $palayInput) * 100,
                2,
            );
        } elseif (
            isset($validated['pmr_rate'], $validated['mri_rate']) &&
            $validated['pmr_rate'] !== '' &&
            $validated['pmr_rate'] !== null &&
            $validated['mri_rate'] !== '' &&
            $validated['mri_rate'] !== null
        ) {
            $millingRecovery = round(
                (float) $validated['pmr_rate'] - (float) $validated['mri_rate'],
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

        $updateData = [
            'test_milling_date' => $validated['test_milling_date'] ?? null,
            'palay_input_kg' => $palayInput,
            'rice_recovery_kg' => $riceRecovery,
            'milling_recovery' => $millingRecovery,
        ];

        if ($formType === 'amr') {
            $updateData['rice_millers'] = $validated['rice_millers'] ?? null;
            if (isset($validated['pmr_rate'])) {
                $updateData['pmr_rate'] = $validated['pmr_rate'] !== null && $validated['pmr_rate'] !== '' ? (float) $validated['pmr_rate'] : null;
            }
            if (isset($validated['mri_rate'])) {
                $updateData['mri_rate'] = $validated['mri_rate'] !== null && $validated['mri_rate'] !== '' ? (float) $validated['mri_rate'] : null;
            }
            if (isset($validated['establishment_type'])) {
                $updateData['establishment_type'] = $validated['establishment_type'];
            } elseif (isset($validated['pmr_rate'], $validated['mri_rate'])) {
                $updateData['establishment_type'] = 'mri';
            }
            if (array_key_exists('mri_remarks', $validated)) {
                $updateData['mri_remarks'] = $validated['mri_remarks'];
            }

            $millerName = trim((string) ($validated['rice_millers'] ?? ''));
            if ($millerName !== '') {
                Miller::firstOrCreate(['name' => $millerName]);
            }
        }

        $trial->update($updateData);

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
            'within_editing_window' => $trial->created_at?->greaterThanOrEqualTo(now()->subDay()),
            'admin_unlocked' => Auth::user()?->isEditOverrideActive() ?? false,
        ], $formType, 'Test milling data edited');

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

        return response()->json([
            'message' => strtoupper($formType).' trial updated successfully.',
        ]);
    }

    public function destroy(
        Request $request,
        string $formType,
        int $recordId,
    ): JsonResponse|RedirectResponse {
        $recordModel =
            $formType === 'amr' ? AmrRecord::class : PmrRecord::class;
        $trial = $recordModel::findOrFail($recordId);

        $this->ensureRecordPileNotGmrLocked($trial, $request, 'deleted');

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id) {
            $pileBranchId = $trial->pile?->branch_id ?? $trial->pile?->warehouse?->branch_id;
            abort_unless($pileBranchId === $currentUser->branch_id, 403, 'You can only delete records in your assigned branch.');
        }

        if ($trial->is_locked) {
            abort(403, 'This test milling record is locked and cannot be deleted.');
        }

        if (! Auth::user()->canEditRecord($trial)) {
            abort(
                403,
                'You can only delete records within 24 hours of creation, or when edit mode is unlocked by an administrator.',
            );
        }

        $pile = $trial->pile;
        $deletedTrialNumber = $trial->trial_number;
        $trial->delete();

        // Renumber remaining trials sequentially
        if ($pile) {
            $remaining = $recordModel::where('pile_id', $pile->id)
                ->orderBy('trial_number')
                ->get();
            foreach ($remaining as $i => $r) {
                $expected = $i + 1;
                if ($r->trial_number !== $expected) {
                    $r->update(['trial_number' => $expected]);
                }
            }

            if ($formType === 'amr') {
                $this->amrCalculationService->calculateAndStoreForPile($pile);
            } else {
                $this->pmrCalculationService->calculateAndStoreForPile($pile);
            }
        }

        AuditLog::record('DATA_DELETED', $trial, [
            'form_type' => $formType,
            'pile_id' => $pile?->id,
            'deleted_trial_number' => $deletedTrialNumber,
        ], $formType, 'Test milling data deleted');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => strtoupper($formType).' trial deleted successfully.',
            ]);
        }

        return back()->with(
            'status',
            strtoupper($formType).' trial deleted successfully.',
        );
    }

    public function store(StoreDataEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id) {
            $validated['branch_id'] = $currentUser->branch_id;
            unset($validated['new_branch_name']);
        }

        $recordClass =
            $validated['form_type'] === 'amr'
                ? AmrRecord::class
                : PmrRecord::class;

        $createdCount = DB::transaction(function () use (
            $validated,
            $recordClass,
        ): int {
            if (! empty($validated['branch_id'])) {
                $branch = Branch::findOrFail($validated['branch_id']);
            } else {
                $branch = Branch::firstOrCreate([
                    'name' => trim((string) $validated['new_branch_name']),
                ]);
            }

            if (! empty($validated['warehouse_id'])) {
                $warehouse = Warehouse::where('id', $validated['warehouse_id'])
                    ->where('branch_id', $branch->id)
                    ->firstOrFail();
            } else {
                $warehouse = Warehouse::firstOrCreate([
                    'branch_id' => $branch->id,
                    'name' => trim((string) $validated['new_warehouse_name']),
                ]);
            }

            $pileNumber =
                $validated['pile_number'] ??
                ($validated['new_pile_number'] ?? null);

            if (! empty($validated['pile_id'])) {
                $pile = Pile::where('id', $validated['pile_id'])
                    ->where('warehouse_id', $warehouse->id)
                    ->firstOrFail();

                // A pile whose GMR is approved/locked cannot receive new test
                // milling data. Throwing (rather than returning a redirect) keeps
                // us inside the int-typed transaction closure and sends the user
                // back to the data-entry form with the error and their old input.
                if ($pile->isGmrLocked()) {
                    throw ValidationException::withMessages([
                        'pile' => "This pile's GMR has been approved and locked by the Central Office and can no longer accept new test milling data.",
                    ]);
                }
            } else {
                $pile = Pile::firstOrCreate(
                    [
                        'warehouse_id' => $warehouse->id,
                        'number' => trim((string) $pileNumber),
                    ],
                    [
                        'branch_id' => $branch->id,
                        'pile_number' => trim((string) $pileNumber),
                        'variety' => $validated['variety'],
                        'purity' => $validated['purity'],
                        'mc' => $validated['mc'],
                        'quality' => $validated['quality'],
                        'aged_months' => $validated['aged'],
                        'volume_kg' => $validated['volume'],
                    ],
                );
            }

            $currentConductNumber =
                (int) (
                    $recordClass::where('pile_id', $pile->id)->max(
                        'conduct_number',
                    ) ?? 0
                );
            $latestRecords = $recordClass::where('pile_id', $pile->id)
                ->where('conduct_number', $currentConductNumber)
                ->get();
            $latestIsRetest = $latestRecords->contains('status', 'RETEST');
            $latestIsLocked = $latestRecords->isNotEmpty() && $latestRecords->every('is_locked');

            if ($latestIsRetest) {
                $conductNumber = $currentConductNumber + 1;
            } elseif ($currentConductNumber === 0) {
                $conductNumber = 1;
            } else {
                if ($latestIsLocked) {
                    throw ValidationException::withMessages([
                        'pile' => 'The current test milling conduct for this pile is locked and finalized. Request a retest to encode new test milling data.',
                    ]);
                }
                $conductNumber = $currentConductNumber;
            }

            if ($conductNumber > $currentConductNumber) {
                $pile->update([
                    $validated['form_type'] === 'amr' ? 'amr_status' : 'pmr_status' => 'pending',
                ]);
            }

            // Update pile attributes
            $pile->update([
                'variety' => $validated['variety'],
                'purity' => $validated['purity'],
                'mc' => $validated['mc'],
                'quality' => $validated['quality'],
                'aged_months' => $validated['aged'],
                'volume_kg' => $validated['volume'],
            ]);

            // Sync updated attributes across all existing trials for this pile
            $sharedAttributes = [
                'variety' => $validated['variety'],
                'purity' => $validated['purity'],
                'mc' => $validated['mc'],
                'quality' => $validated['quality'],
                'aged_months' => $validated['aged'],
                'volume_kg' => $validated['volume'],
            ];
            AmrRecord::where('pile_id', $pile->id)->update($sharedAttributes);
            PmrRecord::where('pile_id', $pile->id)->update($sharedAttributes);

            $trials = $validated['trials'];
            $count = 0;

            // Keep the millers master list in sync even when a value bypassed
            // the combobox popup (legacy posts, tests, scripted requests).
            if ($validated['form_type'] === 'amr') {
                foreach ($trials as $trial) {
                    $millerName = trim((string) ($trial['rice_millers'] ?? ''));
                    if ($millerName !== '') {
                        Miller::firstOrCreate(['name' => $millerName]);
                    }
                }
            }

            foreach ($trials as $trial) {
                $trialNumber = (int) $trial['trial_number'];

                $existing = $recordClass::where('pile_id', $pile->id)
                    ->where('conduct_number', $conductNumber)
                    ->where('trial_number', $trialNumber)
                    ->first();

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
                    isset($trial['pmr_rate'], $trial['mri_rate']) &&
                    $trial['pmr_rate'] !== '' &&
                    $trial['pmr_rate'] !== null &&
                    $trial['mri_rate'] !== '' &&
                    $trial['mri_rate'] !== null
                ) {
                    $millingRecovery = round(
                        (float) $trial['pmr_rate'] - (float) $trial['mri_rate'],
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
                    'establishment_type' => $validated['form_type'] === 'amr'
                            ? ($trial['establishment_type'] ?? ((float) $validated['volume'] < 50000 && isset($trial['mri_rate']) ? 'mri' : 'test_milling'))
                            : null,
                    'pmr_rate' => $validated['form_type'] === 'amr' && isset($trial['pmr_rate']) && $trial['pmr_rate'] !== ''
                            ? (float) $trial['pmr_rate']
                            : null,
                    'mri_rate' => $validated['form_type'] === 'amr' && isset($trial['mri_rate']) && $trial['mri_rate'] !== ''
                            ? (float) $trial['mri_rate']
                            : null,
                    'mri_remarks' => $validated['form_type'] === 'amr' && isset($trial['mri_remarks'])
                            ? $trial['mri_remarks']
                            : null,
                    'trial_number' => $trialNumber,
                    'test_milling_date' => $trial['test_milling_date'],
                    'palay_input_kg' => $palayInput,
                    'rice_recovery_kg' => $riceRecovery,
                    'milling_recovery' => $millingRecovery,
                ];

                if ($existing && ! $existing->is_locked) {
                    $existing->update($trialData);
                    $record = $existing;
                } elseif (! $existing) {
                    $record = $recordClass::create($trialData);
                    $count++;
                } else {
                    continue;
                }

                AuditLog::record('DATA_ENCODED', $record, [
                    'form_type' => $validated['form_type'],
                    'pile_id' => $pile->id,
                    'trial_number' => $trialNumber,
                    'conduct_number' => $conductNumber,
                ], $validated['form_type'], 'Test milling data encoded');
            }

            if ($validated['form_type'] === 'amr') {
                $this->amrCalculationService->calculateAndStoreForPile($pile);
            } else {
                $this->pmrCalculationService->calculateAndStoreForPile($pile);
            }

            return $count;
        });

        $message = "Saved {$createdCount} trial(s) successfully.";

        return redirect()
            ->route('records.create', ['type' => $validated['form_type']])
            ->with('status', $message);
    }

    public function warehouses(Branch $branch): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id && $currentUser->branch_id !== $branch->id) {
            return response()->json([]);
        }

        return response()->json($branch->warehouses()->orderBy('name')->get());
    }

    public function piles(Warehouse $warehouse): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id && $currentUser->branch_id !== $warehouse->branch_id) {
            return response()->json([]);
        }

        return response()->json($warehouse->piles()->orderBy('number')->get());
    }

    public function createWarehouse(Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $isStaff = (bool) ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id);

        $validated = $request->validate([
            'branch_id' => [
                'required',
                'exists:branches,id',
                $isStaff ? Rule::in([$currentUser->branch_id]) : 'nullable',
            ],
            'name' => 'required|string|max:255',
        ]);

        $branchId = $isStaff ? $currentUser->branch_id : (int) $validated['branch_id'];

        $warehouse = Warehouse::firstOrCreate([
            'branch_id' => $branchId,
            'name' => trim($validated['name']),
        ]);

        return response()->json($warehouse, 201);
    }

    public function createPile(Request $request): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $isStaff = (bool) ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id);

        $validated = $request->validate([
            'warehouse_id' => [
                'required',
                'exists:warehouses,id',
                $isStaff ? Rule::exists('warehouses', 'id')->where('branch_id', $currentUser->branch_id) : 'nullable',
            ],
            'number' => 'required|string|max:255',
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $pile = Pile::firstOrCreate(
            [
                'warehouse_id' => $validated['warehouse_id'],
                'number' => trim($validated['number']),
            ],
            [
                'branch_id' => $warehouse->branch_id,
                'pile_number' => trim($validated['number']),
            ],
        );

        return response()->json($pile, 201);
    }

    public function updatePileStatus(Request $request, Pile $pile): JsonResponse|RedirectResponse
    {
        $this->ensurePileNotGmrLocked($pile, $request, 'modified');

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if ($currentUser?->hasRole('STAFF') && $currentUser?->branch_id) {
            $pileBranchId = $pile->branch_id ?? $pile->warehouse?->branch_id;
            abort_unless($pileBranchId === $currentUser->branch_id, 403);
        }

        if ($request->has('form_type')) {
            $validated = $request->validate([
                'form_type' => ['required', Rule::in(['amr', 'pmr'])],
                'action' => ['required', Rule::in(['recommend', 'retest', 'approved', 'RECOMMEND', 'RETEST'])],
            ]);

            $pile->update([
                strtolower($validated['form_type']).'_status' => strtolower($validated['action']),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => strtoupper($validated['form_type']).' pile marked as '.$validated['action'].'.',
                    'pile' => $pile,
                ]);
            }

            return back()->with(
                'status',
                strtoupper($validated['form_type']).' pile marked as '.$validated['action'].'.',
            );
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $pile->update([
            'status' => $validated['status'],
        ]);

        return response()->json([
            'message' => 'Pile status updated successfully.',
            'status' => $pile->status,
        ]);
    }
}
