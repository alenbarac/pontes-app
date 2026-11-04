<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = collect(EmailTemplate::defaults())
            ->map(fn (array $defaults, string $key) => $this->present($key))
            ->values();

        return Inertia::render('EmailTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    public function edit(string $key): Response
    {
        abort_unless(isset(EmailTemplate::defaults()[$key]), 404);

        return Inertia::render('EmailTemplates/Edit', [
            'template' => $this->present($key),
            'placeholders' => collect(EmailTemplate::placeholders($key))
                ->map(fn (string $label, string $token) => ['token' => $token, 'label' => $label])
                ->values(),
            'sample' => [
                'ime' => 'Ivana Anić',
                'opis' => $key === EmailTemplate::DOCUMENT ? 'Ispričnica - Nije me strah' : 'Članarina za 10/2026',
                'naziv_dokumenta' => $key === EmailTemplate::DOCUMENT ? 'Ispričnica - Nije me strah' : '',
                'studio' => trim((string) config('pontes.recipient_name')),
                'facts' => $key === EmailTemplate::DOCUMENT
                    ? [
                        ['label' => 'Dokument', 'value' => 'Ispričnica - Nije me strah'],
                        ['label' => 'Član', 'value' => 'Ivana Anić'],
                    ]
                    : array_values(array_filter([
                        ['label' => 'Program', 'value' => 'Dramska radionica'],
                        ['label' => 'Iznos', 'value' => '45,00 '.trim((string) config('pontes.currency', 'EUR'))],
                        $key === EmailTemplate::REMINDER
                            ? null
                            : ['label' => 'Rok plaćanja', 'value' => '15.10.2026.'],
                    ])),
                'contacts' => array_values(array_filter([
                    trim((string) config('pontes.email')),
                    trim((string) config('pontes.phone')),
                    trim((string) config('pontes.web_url')),
                ])),
            ],
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, string $key): RedirectResponse
    {
        abort_unless(isset(EmailTemplate::defaults()[$key]), 404);

        EmailTemplate::forKey($key)->update($request->validated());

        return redirect()
            ->route('email-templates.edit', $key)
            ->with('success', 'Predložak je spremljen.');
    }

    public function reset(string $key): RedirectResponse
    {
        abort_unless(isset(EmailTemplate::defaults()[$key]), 404);

        $template = EmailTemplate::forKey($key);
        $template->update($template->messageDefaults());

        return redirect()
            ->route('email-templates.edit', $key)
            ->with('success', 'Predložak je vraćen na izvorni tekst.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(string $key): array
    {
        $defaults = EmailTemplate::defaults()[$key];
        $template = EmailTemplate::forKey($key);

        return [
            'key' => $key,
            'name' => $defaults['name'],
            'description' => $defaults['description'],
            'subject' => $template->subject,
            'heading' => $template->heading,
            'greeting' => $template->greeting,
            'intro' => $template->intro,
            'sign_off' => $template->sign_off,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }
}
