<?php

namespace App\Http\Requests;

use App\Models\Pile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePileDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user?->hasRole('STAFF') && $user?->branch_id) {
            $pile = $this->route('pile');
            if ($pile) {
                $pileBranchId = $pile->branch_id ?? $pile->warehouse?->branch_id;
                if ($pileBranchId !== $user->branch_id) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('volume')) {
            $this->merge([
                'volume' => str_replace(',', '', (string) $this->input('volume')),
            ]);
        }

        if ($this->has('test_milling_volume')) {
            $this->merge([
                'test_milling_volume' => $this->filled('test_milling_volume')
                    ? str_replace(',', '', (string) $this->input('test_milling_volume'))
                    : null,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variety' => ['required', 'string', 'max:100'],
            'purity' => ['required', 'numeric', 'between:0,100'],
            'aged' => ['required', 'numeric', 'min:0'],
            'mc' => ['required', 'numeric', 'between:0,100'],
            'quality' => ['required', 'string'],
            'volume' => ['required', 'numeric', 'min:0'],
            'test_milling_volume' => [
                'nullable',
                Rule::requiredIf(
                    fn (): bool => $this->filled('volume') &&
                        (float) $this->input('volume') >= Pile::TEST_MILLING_REQUIRED_VOLUME_KG,
                ),
                'numeric',
                'min:0',
            ],
        ];
    }
}
