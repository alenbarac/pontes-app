<?php

namespace App\Http\Resources;

use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Support\MonthString;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailingResource extends JsonResource
{
    /**
     * @var array<int, string>
     */
    private const CROATIAN_MONTHS = [
        1 => 'Siječanj',
        2 => 'Veljača',
        3 => 'Ožujak',
        4 => 'Travanj',
        5 => 'Svibanj',
        6 => 'Lipanj',
        7 => 'Srpanj',
        8 => 'Kolovoz',
        9 => 'Rujan',
        10 => 'Listopad',
        11 => 'Studeni',
        12 => 'Prosinac',
    ];

    private bool $includeRecipients = false;

    /**
     * @param  array{sent: int, failed: int, queued: int, total: int}|null  $counts
     */
    public function __construct($resource, private ?array $counts = null)
    {
        parent::__construct($resource);
    }

    public function withRecipients(): self
    {
        $this->includeRecipients = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Mailing $mailing */
        $mailing = $this->resource;
        $counts = $this->counts ?? $mailing->attemptCounts();

        $payload = [
            'id' => $mailing->id,
            'type' => $mailing->type,
            'label' => $mailing->label,
            'source' => $mailing->source,
            'group_name' => $this->groupName($mailing),
            'month' => $mailing->month,
            'month_label' => $this->monthLabel($mailing),
            'started_at' => $this->formatStamp($mailing->started_at),
            'completed_at' => $this->formatStamp($mailing->completed_at),
            'sent' => $counts['sent'],
            'failed' => $counts['failed'],
            'queued' => $counts['queued'],
            'total' => $counts['total'],
            'processed' => $counts['sent'] + $counts['failed'],
            'status' => $this->derivedStatus($mailing, $counts),
            'status_label' => $this->statusLabel($mailing, $counts),
            'is_closed' => $mailing->closed_at !== null,
            'url' => route('mailings.show', $mailing),
        ];

        if ($this->includeRecipients) {
            $payload['recipients'] = $this->recipients($mailing);
        }

        return $payload;
    }

    private function groupName(Mailing $mailing): string
    {
        return match ($mailing->source) {
            Mailing::SOURCE_GROUP => $mailing->memberGroup?->name ?: 'Grupa',
            Mailing::SOURCE_MEMBERS => 'Odabrani članovi',
            default => 'Računi',
        };
    }

    private function monthLabel(Mailing $mailing): ?string
    {
        $parsed = $mailing->month !== null ? MonthString::parse($mailing->month) : null;
        if ($parsed === null) {
            return null;
        }

        [, $month] = $parsed;

        return self::CROATIAN_MONTHS[$month].' '.$parsed[0];
    }

    /**
     * @param  array{sent: int, failed: int, queued: int, total: int}  $counts
     */
    private function derivedStatus(Mailing $mailing, array $counts): string
    {
        if ($mailing->closed_at !== null) {
            return Mailing::STATUS_CLOSED;
        }

        if ($counts['queued'] > 0) {
            return Mailing::STATUS_IN_PROGRESS;
        }

        if ($counts['failed'] > 0) {
            return Mailing::STATUS_FINISHED_WITH_ERRORS;
        }

        return Mailing::STATUS_SENT;
    }

    /**
     * @param  array{sent: int, failed: int, queued: int, total: int}  $counts
     */
    private function statusLabel(Mailing $mailing, array $counts): string
    {
        if ($mailing->closed_at !== null) {
            return 'Zatvoreno';
        }

        if ($counts['queued'] === 0 && $counts['failed'] === 0) {
            return $counts['sent'].' poslano';
        }

        return $counts['sent'].' / '.$counts['total'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recipients(Mailing $mailing): array
    {
        return $mailing->latestAttempts()
            ->map(function (InvoiceMailing $row) {
                $name = trim(($row->member->first_name ?? '').' '.($row->member->last_name ?? ''));

                return [
                    'id' => $row->id,
                    'invoice_id' => $row->invoice_id,
                    'member_id' => $row->member_id,
                    'member_name' => $name !== '' ? $name : 'Član',
                    'recipient' => $row->recipient,
                    'status' => $row->status,
                    'error' => $row->status === InvoiceMailing::STATUS_FAILED ? $row->error : null,
                ];
            })
            ->sortBy('member_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function formatStamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->timezone(config('app.timezone'))->format('d.m.Y. H:i');
    }
}
