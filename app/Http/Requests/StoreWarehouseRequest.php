<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $user = $this->user();
        if ($user?->hasRole('STAFF') && $user?->branch_id) {
            $this->merge([
                'branch_id' => $user->branch_id,
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isStaff = (bool) ($user?->hasRole('STAFF') && $user?->branch_id);

        return [
            'branch_id' => [
                'required',
                'exists:branches,id',
                $isStaff ? Rule::in([$user->branch_id]) : 'nullable',
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('warehouses', 'name')->where(
                    fn ($query) => $query->where('branch_id', $this->input('branch_id')),
                ),
            ],
        ];
    }
}
