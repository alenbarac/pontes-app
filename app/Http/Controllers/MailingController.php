<?php

namespace App\Http\Controllers;

use App\Http\Resources\MailingResource;
use App\Models\Mailing;
use App\Services\MemberDocumentEmailService;
use App\Services\PaymentSlipEmailService;
use App\Support\MailRecipientAllowlist;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MailingController extends Controller
{
    public function __construct(
        protected PaymentSlipEmailService $paymentSlipEmailService,
        protected MemberDocumentEmailService $memberDocumentEmailService,
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
        $result = $mailing->type === Mailing::TYPE_DOCUMENT
            ? $this->memberDocumentEmailService->retryFailed($mailing)
            : $this->paymentSlipEmailService->retryFailed($mailing);

        if ($result['recipient_not_allowed']) {
            return back()->with('error', $result['message'] ?? MailRecipientAllowlist::REFUSAL_MESSAGE);
        }

        $queued = $result['queued'];

        $message = $mailing->type === Mailing::TYPE_DOCUMENT
            ? ($queued > 0
                ? 'Neuspjeli dokumenti ponovno su stavljeni u red.'
                : 'Nema neuspjelih dokumenata za ponovno slanje.')
            : ($queued > 0
                ? 'Neuspjele uplatnice ponovno su stavljene u red.'
                : 'Nema neuspjelih uplatnica za ponovno slanje.');

        return back()->with($queued > 0 ? 'success' : 'error', $message);
    }

    public function close(Mailing $mailing): RedirectResponse
    {
        if ($mailing->closed_at !== null) {
            return back()->with('error', 'Slanje je već označeno kao završeno.');
        }

        $mailing->closeOpenAttempts();

        return back()->with('success', 'Slanje je označeno kao završeno.');
    }

    public function destroy(Mailing $mailing): RedirectResponse
    {
        $mailing->delete();

        return redirect()
            ->route('mailings.index')
            ->with('success', 'Slanje je obrisano iz evidencije.');
    }
}
