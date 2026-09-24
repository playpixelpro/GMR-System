<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePileRequest extends FormRequest
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
            'branch_id' => ['required', 'exists:branches,id'],
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where(
                    fn ($query) => $query->where('branch_id', $this->input('branch_id')),
                ),
            ],
            'number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('piles', 'number')->where(
                    fn ($query) => $query->where('warehouse_id', $this->input('warehouse_id')),
                ),
            ],
        ];
    }
}
