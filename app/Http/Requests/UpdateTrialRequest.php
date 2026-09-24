<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rice_millers' => ['nullable', 'string', 'max:191'],
            'test_milling_date' => ['nullable', 'date_format:Y-m-d'],
            'palay_input' => ['required', 'numeric', 'gt:0'],
            'rice_recovery' => ['required', 'numeric', 'gte:0', 'lte:palay_input'],
        ];
    }
}
