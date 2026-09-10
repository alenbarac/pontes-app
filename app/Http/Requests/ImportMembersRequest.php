<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMembersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($extension, ['xlsx', 'xls', 'csv', 'ods'], true)) {
                        $fail('Datoteka mora biti Excel (.xlsx, .xls, .ods) ili CSV format.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Molimo odaberite datoteku za učitavanje.',
            'file.file' => 'Odabrana datoteka nije valjana.',
            'file.mimes' => 'Datoteka mora biti Excel (.xlsx, .xls, .ods) ili CSV format.',
            'file.max' => 'Datoteka ne smije biti veća od 10MB.',
        ];
    }
}
