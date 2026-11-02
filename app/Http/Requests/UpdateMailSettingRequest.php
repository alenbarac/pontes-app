<?php

namespace App\Http\Requests;

use App\Support\MailRecipientAllowlist;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'test_sending' => ['required', 'boolean'],
            'confirm_live' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $testing = $this->boolean('test_sending');

            if ($testing && ! MailRecipientAllowlist::restricts()) {
                $validator->errors()->add(
                    'test_sending',
                    'Testno slanje treba barem jednu adresu u MAIL_RECIPIENT_ALLOWLIST.',
                );
            }

            if (! $testing && ! $this->boolean('confirm_live')) {
                $validator->errors()->add('confirm_live', 'Potvrdite stvarno slanje.');
            }
        });
    }
}
