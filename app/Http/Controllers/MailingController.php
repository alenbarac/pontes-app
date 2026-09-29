<?php

namespace App\Http\Controllers;

use App\Http\Resources\MailingResource;
use App\Models\Mailing;
use App\Services\PaymentSlipEmailService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MailingController extends Controller
{
    public function __construct(
        protected PaymentSlipEmailService $paymentSlipEmailService,
    ) {}

    public function index(): Response
    {
        $paginator = Mailing::query()
            ->with('memberGroup:id,name')
            ->latest('started_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $counts = Mailing::countsFor($paginator->items());

        $paginator->through(function (Mailing $mailing) use ($counts) {
            return (new MailingResource($mailing, $counts[$mailing->id] ?? null))->resolve(request());
        });

        return Inertia::render('Mailings/Index', [
            'mailings' => $paginator,
        ]);
    }

    public function show(Mailing $mailing): Response
    {
        $mailing->load('memberGroup:id,name');

        return Inertia::render('Mailings/Show', [
            'mailing' => (new MailingResource($mailing))->withRecipients()->resolve(request()),
        ]);
    }

    public function retryFailed(Mailing $mailing): RedirectResponse
    {
        $queued = $this->paymentSlipEmailService->retryFailed($mailing);

        $message = $queued > 0
            ? 'Neuspjele uplatnice ponovno su stavljene u red.'
            : 'Nema neuspjelih uplatnica za ponovno slanje.';

        return back()->with($queued > 0 ? 'success' : 'error', $message);
    }
}
