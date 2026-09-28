<?php

namespace App\Http\Requests;

use App\Models\AmrRecord;
use App\Models\Pile;
use App\Models\PmrRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDataEntryRequest extends FormRequest
{
    private function isAmrLowVolume(): bool
    {
        $formType = $this->input('form_type', 'amr');
        if ($formType !== 'amr') {
            return false;
        }

        $volume = $this->filled('volume')
            ? (float) str_replace(',', '', (string) $this->input('volume'))
            : null;

        if ($volume === null && $this->filled('pile_id')) {
            $existingPile = Pile::find($this->input('pile_id'));
            $volume = $existingPile ? (float) $existingPile->volume_kg : null;
        }

        return $volume !== null && $volume <= 50000;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user?->hasRole('STAFF') && $user?->branch_id) {
            $this->merge([
                'branch_id' => $user->branch_id,
            ]);
        }

        if ($this->filled('volume')) {
            $this->merge([
                'volume' => str_replace(
                    ',',
                    '',
                    (string) $this->input('volume'),
                ),
            ]);
        }

        $formType = $this->input('form_type', 'amr');
        $recordModel =
            $formType === 'amr' ? AmrRecord::class : PmrRecord::class;

        $existingTrialNumbers = [];
        $pileId = $this->input('pile_id');
        if (! empty($pileId) && is_numeric($pileId)) {
            $existingTrialNumbers = $recordModel::where('pile_id', (int) $pileId)
                ->pluck('trial_number')
                ->map(fn ($t): int => (int) $t)
                ->all();
        } elseif (
            $this->filled('warehouse_id') &&
            ($this->filled('new_pile_number') || $this->filled('pile_number'))
        ) {
            $pileNumber = trim(
                (string) ($this->input('new_pile_number') ??
                    $this->input('pile_number')),
            );
            $existingPile = Pile::where(
                'warehouse_id',
                $this->input('warehouse_id'),
            )
                ->where('number', $pileNumber)
                ->first();
            if ($existingPile) {
                $existingTrialNumbers = $recordModel::where('pile_id', $existingPile->id)
                    ->pluck('trial_number')
                    ->map(fn ($t): int => (int) $t)
                    ->all();
            }
        }

        $allowsOptionalInputs = $formType === 'pmr' || $this->isAmrLowVolume();

        if ($this->has('trials')) {
            $trials = $this->input('trials');
            if (is_array($trials)) {
                $usedTrialNumbers = $existingTrialNumbers;
                foreach ($trials as $t) {
                    if (
                        ! empty($t['trial_number']) &&
                        is_numeric($t['trial_number'])
                    ) {
                        $usedTrialNumbers[] = (int) $t['trial_number'];
                    }
                }

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
                        $trial['trial_number'] = (int) $trial['trial_number'];
                    }

                    // Auto-compute recovery rate for PMR or AMR (volume <= 50,000) when both palay and rice inputs are provided
                    if ($allowsOptionalInputs) {
                        $hasPalay =
                            isset($trial['palay_input']) &&
                            $trial['palay_input'] !== '' &&
                            $trial['palay_input'] !== null;
                        $hasRice =
                            isset($trial['rice_recovery']) &&
                            $trial['rice_recovery'] !== '' &&
                            $trial['rice_recovery'] !== null;
                        if (
                            $hasPalay &&
                            $hasRice &&
                            (float) $trial['palay_input'] > 0
                        ) {
                            $trial['recovery_rate'] = round(
                                ((float) $trial['rice_recovery'] /
                                    (float) $trial['palay_input']) *
                                    100,
                                2,
                            );
                        }
                    }
                }
                unset($trial);
                $this->merge(['trials' => $trials]);
            }

            return;
        }

        $trialNum = null;
        if ($this->filled('no_of_trial')) {
            $trialNum = (int) $this->input('no_of_trial');
        } else {
            $next = 1;
            while (in_array($next, $existingTrialNumbers, true)) {
                $next++;
            }
            $trialNum = $next;
        }

        $palayInput = $this->input('palay_input');
        $riceRecovery = $this->input('rice_recovery');
        $recoveryRate = $this->input('recovery_rate');
        if (
            $allowsOptionalInputs &&
            $palayInput !== null &&
            $riceRecovery !== null &&
            (float) $palayInput > 0
        ) {
            $recoveryRate = round(
                ((float) $riceRecovery / (float) $palayInput) * 100,
                2,
            );
        }

        $this->merge([
            'trials' => [
                [
                    'trial_number' => $trialNum,
                    'test_milling_date' => $this->input('test_milling_date'),
                    'rice_millers' => $this->input('rice_millers'),
                    'palay_input' => $palayInput,
                    'rice_recovery' => $riceRecovery,
                    'recovery_rate' => $recoveryRate,
                ],
            ],
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isStaff = (bool) ($user?->hasRole('STAFF') && $user?->branch_id);
        $userBranchId = $isStaff ? $user->branch_id : null;

        $warehouseExists = Rule::exists('warehouses', 'id');
        $pileExists = Rule::exists('piles', 'id');
        $maximumTrial = 3;
        $isPmr = $this->input('form_type') === 'pmr';
        $allowsOptionalInputs = $isPmr || $this->isAmrLowVolume();

        if ($userBranchId) {
            $warehouseExists->where('branch_id', $userBranchId);
            $pileExists->where(function ($query) use ($userBranchId): void {
                $query->where('branch_id', $userBranchId)
                    ->orWhereHas('warehouse', fn ($w) => $w->where('branch_id', $userBranchId));
            });
        } elseif ($this->filled('branch_id')) {
            $warehouseExists->where('branch_id', $this->input('branch_id'));
        }

        if ($this->filled('warehouse_id')) {
            $pileExists->where('warehouse_id', $this->input('warehouse_id'));
        }

        $rules = [
            'form_type' => ['required', Rule::in(['amr', 'pmr'])],
            'branch_id' => [
                'nullable',
                $isStaff ? 'required' : 'required_without:new_branch_name',
                $isStaff ? Rule::in([$userBranchId]) : 'exists:branches,id',
                'prohibits:new_branch_name',
            ],
            'new_branch_name' => [
                'nullable',
                $isStaff ? 'prohibited' : 'required_without:branch_id',
                'string',
                'max:100',
                'prohibits:branch_id',
            ],
            'warehouse_id' => [
                'nullable',
                'required_without:new_warehouse_name',
                $warehouseExists,
                'prohibits:new_warehouse_name',
            ],
            'new_warehouse_name' => [
                'nullable',
                'required_without:warehouse_id',
                'string',
                'max:100',
                'prohibits:warehouse_id',
            ],
            'pile_id' => [
                'nullable',
                'required_without_all:new_pile_number,pile_number',
                $pileExists,
                'prohibits:new_pile_number',
            ],
            'new_pile_number' => [
                'nullable',
                'required_without_all:pile_id,pile_number',
                'string',
                'max:50',
                'prohibits:pile_id',
            ],
            'pile_number' => [
                'nullable',
                'string',
                'max:50',
                'prohibits:pile_id',
            ],
            'variety' => ['required', 'string', 'max:100'],
            'purity' => ['required', 'numeric', 'between:0,100'],
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
            'aged' => ['required', 'numeric', 'min:0'],
            'volume' => ['required', 'numeric', 'min:0'],
            'trials' => ['required', 'array', 'min:1', 'max:'.$maximumTrial],
            'trials.*.trial_number' => [
                'required',
                'integer',
                'min:1',
                'max:'.$maximumTrial,
            ],
            'trials.*.test_milling_date' => ['required', 'date_format:Y-m-d'],
            'trials.*.rice_millers' => [
                $allowsOptionalInputs ? 'nullable' : 'required_if:form_type,amr',
                'nullable',
                'string',
                'max:191',
            ],
        ];

        if ($allowsOptionalInputs) {
            $rules['trials.*.palay_input'] = ['nullable', 'numeric', 'gt:0'];
            $rules['trials.*.rice_recovery'] = ['nullable', 'numeric', 'gte:0'];
            $rules['trials.*.recovery_rate'] = [
                'nullable',
                'numeric',
                'between:0,100',
            ];
        } else {
            $rules['trials.*.palay_input'] = ['required', 'numeric', 'gt:0'];
            $rules['trials.*.rice_recovery'] = ['required', 'numeric', 'gte:0'];
        }

        return $rules;
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowedLimit = 3;
                $formType = $this->input('form_type', 'amr');
                $isPmr = $formType === 'pmr';
                $allowsOptionalInputs = $isPmr || $this->isAmrLowVolume();

                foreach ($this->input('trials', []) as $index => $trial) {
                    if ((int) ($trial['trial_number'] ?? 0) > $allowedLimit) {
                        $validator
                            ->errors()
                            ->add(
                                'no_of_trial',
                                'The trial number exceeds the allowed limit.',
                            );
                    }

                    $hasPalay =
                        isset($trial['palay_input']) &&
                        $trial['palay_input'] !== '' &&
                        $trial['palay_input'] !== null;
                    $hasRice =
                        isset($trial['rice_recovery']) &&
                        $trial['rice_recovery'] !== '' &&
                        $trial['rice_recovery'] !== null;
                    $hasRate =
                        isset($trial['recovery_rate']) &&
                        $trial['recovery_rate'] !== '' &&
                        $trial['recovery_rate'] !== null;

                    $palay = $hasPalay ? (float) $trial['palay_input'] : null;
                    $rice = $hasRice ? (float) $trial['rice_recovery'] : null;

                    if ($palay !== null && $rice !== null) {
                        if ($rice > $palay) {
                            $validator
                                ->errors()
                                ->add(
                                    'trials.'.$index.'.rice_recovery',
                                    'Rice output cannot exceed palay input.',
                                );
                            $validator
                                ->errors()
                                ->add(
                                    'rice_recovery',
                                    'Rice output cannot exceed palay input.',
                                );
                        }
                    }

                    if ($allowsOptionalInputs) {
                        // When Palay Input and Rice Output are not both provided, Recovery Rate must be entered
                        if (! ($hasPalay && $hasRice) && ! $hasRate) {
                            $validator
                                ->errors()
                                ->add(
                                    'trials.'.$index.'.recovery_rate',
                                    'Please enter both Palay Input and Rice Output, or enter the Recovery Rate (%) directly.',
                                );
                            $validator
                                ->errors()
                                ->add(
                                    'recovery_rate',
                                    'Please enter both Palay Input and Rice Output, or enter the Recovery Rate (%) directly.',
                                );
                        }
                    }
                }
            },
        ];
    }
}
