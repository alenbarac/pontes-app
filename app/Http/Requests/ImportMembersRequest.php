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
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
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
            'file.mimes' => 'Datoteka mora biti Excel (.xlsx, .xls) ili CSV format.',
            'file.max' => 'Datoteka ne smije biti veća od 10MB.',
        ];
    }
}

