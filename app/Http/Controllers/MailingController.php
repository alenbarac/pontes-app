<?php

namespace App\Http\Controllers;

use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Services\PaymentSlipEmailService;
use Illuminate\Http\JsonResponse;
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

        return Inertia::render('Mailings/Index', [
            'mailings' => [
                'data' => collect($paginator->items())
                    ->map(fn (Mailing $mailing) => $this->present($mailing, $counts[$mailing->id] ?? null))
                    ->values(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Mailing $mailing): Response
    {
        $mailing->load('memberGroup:id,name');

        return Inertia::render('Mailings/Show', [
            'mailing' => [
                ...$this->present($mailing),
                'recipients' => $mailing->recipientRows(),
            ],
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

    /**
     * Active batches for the header, plus recently finished ones for the bell.
     */
    public function activity(): JsonResponse
    {
        $active = Mailing::query()
            ->with('memberGroup:id,name')
            ->whereHas('invoiceMailings', fn ($query) => $query->where('status', InvoiceMailing::STATUS_QUEUED))
            ->latest('id')
            ->get();

        $recent = Mailing::query()
            ->with('memberGroup:id,name')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDay())
            ->latest('completed_at')
            ->limit(10)
            ->get();

        $latest = Mailing::query()
            ->with('memberGroup:id,name')
            ->latest('id')
            ->first();

        $counts = Mailing::countsFor(
            $active->concat($recent)->when($latest !== null, fn ($rows) => $rows->push($latest))->unique('id')
        );

        $present = fn (Mailing $mailing) => $this->present($mailing, $counts[$mailing->id] ?? null);

        return response()->json([
            'active' => $active
                ->filter(fn (Mailing $mailing) => ($counts[$mailing->id]['queued'] ?? 0) > 0)
                ->map($present)
                ->values(),
            'recent_finished' => $recent->map($present)->values(),
            'latest' => $latest ? $present($latest) : null,
        ]);
    }

    /**
     * @param  array{sent: int, failed: int, queued: int, total: int}|null  $counts
     * @return array<string, mixed>
     */
    private function present(Mailing $mailing, ?array $counts = null): array
    {
        return [
            ...$mailing->summary($counts),
            'url' => route('mailings.show', $mailing),
        ];
    }
}
