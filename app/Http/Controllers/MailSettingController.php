<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMailSettingRequest;
use App\Models\MailSetting;
use App\Support\MailRecipientAllowlist;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MailSettingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('EmailSettings/Edit', [
            'setting' => [
                'test_sending' => MailSetting::testSending(),
            ],
            'allowlist' => MailRecipientAllowlist::addresses(),
        ]);
    }

    public function update(UpdateMailSettingRequest $request): RedirectResponse
    {
        $testing = $request->boolean('test_sending');

        MailSetting::current()->update([
            'test_sending' => $testing,
        ]);

        return back()->with(
            'success',
            $testing
                ? 'Testno slanje je uključeno. Poruke idu samo na dopuštene adrese.'
                : 'Stvarno slanje je uključeno. Poruke idu na e-mail za račune.',
        );
    }
}
