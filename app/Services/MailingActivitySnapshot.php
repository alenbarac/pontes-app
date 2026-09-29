<?php

namespace App\Services;

use App\Http\Resources\MailingResource;
use App\Models\InvoiceMailing;
use App\Models\Mailing;
use Illuminate\Http\Request;

/**
 * Header progress, the finish bell, and the dashboard card share this payload.
 */
class MailingActivitySnapshot
{
    /**
     * @return array{active: list<array<string, mixed>>, recent_finished: list<array<string, mixed>>, recent: list<array<string, mixed>>, latest: array<string, mixed>|null}
     */
    public function toArray(?Request $request = null): array
    {
        $request ??= request();

        $active = Mailing::query()
            ->with('memberGroup:id,name')
            ->whereNull('closed_at')
            ->whereHas('invoiceMailings', fn ($query) => $query->where('status', InvoiceMailing::STATUS_QUEUED))
            ->latest('id')
            ->get();

        $recentFinished = Mailing::query()
            ->with('memberGroup:id,name')
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDay())
            ->latest('completed_at')
            ->limit(10)
            ->get();

        $recent = Mailing::query()
            ->with('memberGroup:id,name')
            ->latest('started_at')
            ->latest('id')
            ->limit(20)
            ->get();

        $latest = Mailing::query()
            ->with('memberGroup:id,name')
            ->latest('id')
            ->first();

        $counts = Mailing::countsFor(
            $active->concat($recentFinished)->concat($recent)->when($latest !== null, fn ($rows) => $rows->push($latest))->unique('id')
        );

        $present = function (Mailing $mailing) use ($counts, $request): array {
            return (new MailingResource($mailing, $counts[$mailing->id] ?? null))->resolve($request);
        };

        return [
            'active' => $active
                ->filter(fn (Mailing $mailing) => ($counts[$mailing->id]['queued'] ?? 0) > 0)
                ->map($present)
                ->values()
                ->all(),
            'recent_finished' => $recentFinished->map($present)->values()->all(),
            'recent' => $recent->map($present)->values()->all(),
            'latest' => $latest ? $present($latest) : null,
        ];
    }
}
