<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkshopDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'percent' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'percent.required' => 'Unesite postotak popusta.',
            'percent.integer' => 'Postotak popusta mora biti cijeli broj.',
            'percent.min' => 'Postotak popusta mora biti između 1 i 99.',
            'percent.max' => 'Postotak popusta mora biti između 1 i 99.',
        ];
    }
}
