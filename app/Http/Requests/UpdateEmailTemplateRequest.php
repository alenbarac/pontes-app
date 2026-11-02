<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $line = fn (string $key) => trim(preg_replace('/\s+/u', ' ', (string) $this->input($key, '')) ?? '');

        $this->merge([
            'subject' => $line('subject'),
            'heading' => $line('heading'),
            'greeting' => $line('greeting'),
            'sign_off' => $line('sign_off'),
            'intro' => trim((string) $this->input('intro', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:180'],
            'heading' => ['required', 'string', 'max:180'],
            'greeting' => ['required', 'string', 'max:180'],
            'intro' => ['required', 'string', 'max:2000'],
            'sign_off' => ['required', 'string', 'max:180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Predmet je obavezan.',
            'heading.required' => 'Naslov je obavezan.',
            'greeting.required' => 'Pozdrav je obavezan.',
            'intro.required' => 'Uvod je obavezan.',
            'sign_off.required' => 'Završetak je obavezan.',
        ];
    }
}
