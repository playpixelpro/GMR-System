<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDataEntryRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('volume')) {
            $this->merge([
                'volume' => str_replace(',', '', (string) $this->input('volume')),
            ]);
        }

        if ($this->has('trials')) {
            return;
        }

        if ($this->filled('no_of_trial')) {
            $this->merge([
                'trials' => [[
                    'trial_number' => $this->input('no_of_trial'),
                    'test_milling_date' => $this->input('test_milling_date'),
                    'rice_millers' => $this->input('rice_millers'),
                    'palay_input' => $this->input('palay_input'),
                    'rice_recovery' => $this->input('rice_recovery'),
                ]],
            ]);
        }
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
        $warehouseExists = Rule::exists('warehouses', 'id');
        $pileExists = Rule::exists('piles', 'id');
        $maximumTrial = $this->input('form_type') === 'amr' ? 3 : 5;

        if ($this->filled('branch_id')) {
            $warehouseExists->where('branch_id', $this->input('branch_id'));
        }

        if ($this->filled('warehouse_id')) {
            $pileExists->where('warehouse_id', $this->input('warehouse_id'));
        }

        return [
            'form_type' => ['required', Rule::in(['amr', 'pmr'])],
            'branch_id' => ['nullable', 'required_without:new_branch_name', 'exists:branches,id', 'prohibits:new_branch_name'],
            'new_branch_name' => ['nullable', 'required_without:branch_id', 'string', 'max:100', 'prohibits:branch_id'],
            'warehouse_id' => ['nullable', 'required_without:new_warehouse_name', $warehouseExists, 'prohibits:new_warehouse_name'],
            'new_warehouse_name' => ['nullable', 'required_without:warehouse_id', 'string', 'max:100', 'prohibits:warehouse_id'],
            'pile_id' => ['nullable', 'required_without_all:new_pile_number,pile_number', $pileExists, 'prohibits:new_pile_number'],
            'new_pile_number' => ['nullable', 'required_without_all:pile_id,pile_number', 'string', 'max:50', 'prohibits:pile_id'],
            'pile_number' => ['nullable', 'string', 'max:50', 'prohibits:pile_id'],
            'variety' => ['required', 'string', 'max:100'],
            'purity' => ['required', 'numeric', 'between:0,100'],
            'mc' => ['required', 'numeric', 'between:0,100'],
            'quality' => ['required', Rule::in(['gqa', 'premium', 'good', 'fair', 'poor'])],
            'aged' => ['required', 'integer', 'min:0'],
            'volume' => ['required', 'numeric', 'min:0'],
            'trials' => ['required', 'array', 'min:1', 'max:'.$maximumTrial],
            'trials.*.trial_number' => ['required', 'integer', 'min:1', 'max:'.$maximumTrial],
            'trials.*.test_milling_date' => ['required', 'date_format:Y-m-d'],
            'trials.*.rice_millers' => ['required_if:form_type,amr', 'nullable', 'string', 'max:191'],
            'trials.*.palay_input' => ['required', 'numeric', 'gt:0'],
            'trials.*.rice_recovery' => ['required', 'numeric', 'gte:0'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('trials', []) as $index => $trial) {
                if ((int) ($trial['trial_number'] ?? 0) > ($this->input('form_type') === 'amr' ? 3 : 5)) {
                    $validator->errors()->add('no_of_trial', 'The trial number exceeds the allowed limit.');
                }

                if ((float) ($trial['rice_recovery'] ?? 0) > (float) ($trial['palay_input'] ?? 0)) {
                    $validator->errors()->add(
                        'trials.'.$index.'.rice_recovery',
                        'Rice output cannot exceed palay input.',
                    );
                    $validator->errors()->add('rice_recovery', 'Rice output cannot exceed palay input.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'branch',
            'new_branch_name' => 'new branch name',
            'warehouse_id' => 'warehouse',
            'new_warehouse_name' => 'new warehouse name',
            'pile_id' => 'pile number',
            'new_pile_number' => 'new pile number',
            'trials.*.trial_number' => 'trial number',
            'trials.*.test_milling_date' => 'test milling date',
            'trials.*.rice_millers' => 'rice miller',
            'trials.*.palay_input' => 'palay input',
            'trials.*.rice_recovery' => 'rice output',
        ];
    }
}
