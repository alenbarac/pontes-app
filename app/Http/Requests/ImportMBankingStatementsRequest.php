<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMBankingStatementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Molimo odaberite CSV datoteku za uvoz.',
            'file.file' => 'Odabrana datoteka nije valjana.',
            'file.mimes' => 'Datoteka mora biti CSV format.',
            'file.max' => 'Datoteka ne smije biti veća od 10MB.',
        ];
    }
}
