<?php

namespace App\Http\Middleware;

use App\Models\MailSetting;
use App\Services\MailingActivitySnapshot;
use App\Support\MailRecipientAllowlist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'csrf_token' => csrf_token(),
            'flash' => fn () => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
            ],
            'mailSending' => fn () => [
                'testSending' => MailSetting::testSending(),
                'allowlistConfigured' => MailRecipientAllowlist::restricts(),
            ],
            'mailingActivity' => $request->user()
                ? Inertia::defer(fn () => app(MailingActivitySnapshot::class)->toArray($request))
                : null,
        ];
    }
}
