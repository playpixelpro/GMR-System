<?php

namespace App\Http\Requests;

use App\Models\AmrRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTrialRequest extends FormRequest
{
    private function isAmrLowVolume(): bool
    {
        $formType =
            $this->route('formType') ??
            ($this->route('form_type') ?? $this->input('form_type'));
        if ($formType !== 'amr') {
            return false;
        }

        $recordId = $this->route('record');
        if ($recordId) {
            $record = AmrRecord::with('pile')->find($recordId);
            $volume = $record?->pile?->volume_kg ?? $record?->volume_kg;
            if ($volume !== null && (float) $volume <= 50000) {
                return true;
            }
        }

        return false;
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
        $formType =
            $this->route('formType') ??
            ($this->route('form_type') ?? $this->input('form_type'));
        $isPmr = $formType === 'pmr';
        $allowsOptionalInputs = $isPmr || $this->isAmrLowVolume();

        if ($allowsOptionalInputs) {
            return [
                'rice_millers' => ['nullable', 'string', 'max:191'],
                'test_milling_date' => ['nullable', 'date_format:Y-m-d'],
                'palay_input' => ['nullable', 'numeric', 'gt:0'],
                'rice_recovery' => ['nullable', 'numeric', 'gte:0'],
                'recovery_rate' => ['nullable', 'numeric', 'between:0,100'],
            ];
        }

        return [
            'rice_millers' => ['nullable', 'string', 'max:191'],
            'test_milling_date' => ['nullable', 'date_format:Y-m-d'],
            'palay_input' => ['required', 'numeric', 'gt:0'],
            'rice_recovery' => [
                'required',
                'numeric',
                'gte:0',
                'lte:palay_input',
            ],
            'recovery_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $formType =
                    $this->route('formType') ??
                    ($this->route('form_type') ?? $this->input('form_type'));
                $isPmr = $formType === 'pmr';
                $allowsOptionalInputs = $isPmr || $this->isAmrLowVolume();
                $hasPalay = $this->filled('palay_input');
                $hasRice = $this->filled('rice_recovery');
                $hasRate = $this->filled('recovery_rate');

                if ($hasPalay && $hasRice) {
                    if (
                        (float) $this->input('rice_recovery') >
                        (float) $this->input('palay_input')
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                'rice_recovery',
                                'Rice output cannot exceed palay input.',
                            );
                    }
                }

                if ($allowsOptionalInputs) {
                    if (! ($hasPalay && $hasRice) && ! $hasRate) {
                        $validator
                            ->errors()
                            ->add(
                                'recovery_rate',
                                'Please enter both Palay Input and Rice Output, or enter the Recovery Rate (%) directly.',
                            );
                    }
                }
            },
        ];
    }
}
