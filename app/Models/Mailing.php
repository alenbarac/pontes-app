<?php

namespace App\Models;

use App\Support\MonthString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mailing extends Model
{
    use HasFactory;

    public const TYPE_SLIP = 'slip';

    public const SOURCE_GROUP = 'group';

    public const SOURCE_MEMBERS = 'members';

    public const SOURCE_INVOICES = 'invoices';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_FINISHED_WITH_ERRORS = 'finished_with_errors';

    public const STATUS_SENT = 'sent';

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

    protected $fillable = [
        'type',
        'label',
        'source',
        'member_group_id',
        'month',
        'user_id',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function labelFor(?string $month): string
    {
        $parsed = $month !== null ? MonthString::parse($month) : null;
        if ($parsed === null) {
            return 'Uplatnice';
        }

        [$year, $mon] = $parsed;

        return sprintf('Uplatnice %02d/%d', $mon, $year);
    }

    public function invoiceMailings()
    {
        return $this->hasMany(InvoiceMailing::class);
    }

    public function memberGroup()
    {
        return $this->belongsTo(MemberGroup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function groupName(): string
    {
        return match ($this->source) {
            self::SOURCE_GROUP => $this->memberGroup?->name ?: 'Grupa',
            self::SOURCE_MEMBERS => 'Odabrani članovi',
            default => 'Računi',
        };
    }

    public function monthLabel(): ?string
    {
        $parsed = $this->month !== null ? MonthString::parse($this->month) : null;
        if ($parsed === null) {
            return null;
        }

        [$year, $month] = $parsed;

        return self::CROATIAN_MONTHS[$month].' '.$year;
    }

    /**
     * Latest attempt per invoice. A retry adds a new row; counts follow that row.
     *
     * @return array{sent: int, failed: int, queued: int, total: int}
     */
    public function attemptCounts(): array
    {
        $map = self::countsFor([$this->id]);

        return $map[$this->id] ?? self::emptyCounts();
    }

    /**
     * @param  iterable<int, Mailing|int>  $mailings
     * @return array<int, array{sent: int, failed: int, queued: int, total: int}>
     */
    public static function countsFor(iterable $mailings): array
    {
        $ids = collect($mailings)
            ->map(fn ($mailing) => $mailing instanceof self ? $mailing->id : (int) $mailing)
            ->filter()
            ->unique()
            ->values();

        $counts = [];
        foreach ($ids as $id) {
            $counts[$id] = self::emptyCounts();
        }

        if ($ids->isEmpty()) {
            return $counts;
        }

        $latest = InvoiceMailing::query()
            ->selectRaw('MAX(id) as id')
            ->whereIn('mailing_id', $ids->all())
            ->groupBy('mailing_id', 'invoice_id');

        $rows = InvoiceMailing::query()
            ->select(['invoice_mailings.mailing_id', 'invoice_mailings.status'])
            ->joinSub($latest, 'latest', 'latest.id', '=', 'invoice_mailings.id')
            ->get();

        foreach ($rows as $row) {
            $mailingId = (int) $row->mailing_id;
            if (! isset($counts[$mailingId])) {
                continue;
            }

            $status = (string) $row->status;
            if (isset($counts[$mailingId][$status])) {
                $counts[$mailingId][$status]++;
            }
            $counts[$mailingId]['total']++;
        }

        return $counts;
    }

    /**
     * @param  array{sent: int, failed: int, queued: int, total: int}  $counts
     */
    public function derivedStatus(array $counts): string
    {
        if ($counts['queued'] > 0) {
            return self::STATUS_IN_PROGRESS;
        }

        if ($counts['failed'] > 0) {
            return self::STATUS_FINISHED_WITH_ERRORS;
        }

        return self::STATUS_SENT;
    }

    /**
     * Index status: "14 poslano" when every recipient was sent, otherwise "10 / 11".
     *
     * @param  array{sent: int, failed: int, queued: int, total: int}  $counts
     */
    public function statusLabel(array $counts): string
    {
        if ($counts['queued'] === 0 && $counts['failed'] === 0) {
            return $counts['sent'].' poslano';
        }

        return $counts['sent'].' / '.$counts['total'];
    }

    /**
     * @param  array{sent: int, failed: int, queued: int, total: int}|null  $counts
     * @return array<string, mixed>
     */
    public function summary(?array $counts = null): array
    {
        $counts ??= $this->attemptCounts();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'source' => $this->source,
            'group_name' => $this->groupName(),
            'month' => $this->month,
            'month_label' => $this->monthLabel(),
            'started_at' => $this->formatStamp($this->started_at),
            'completed_at' => $this->formatStamp($this->completed_at),
            'sent' => $counts['sent'],
            'failed' => $counts['failed'],
            'queued' => $counts['queued'],
            'total' => $counts['total'],
            'processed' => $counts['sent'] + $counts['failed'],
            'status' => $this->derivedStatus($counts),
            'status_label' => $this->statusLabel($counts),
        ];
    }

    /**
     * One row per recipient: the latest attempt for that invoice.
     *
     * @return list<array<string, mixed>>
     */
    public function recipientRows(): array
    {
        $latest = InvoiceMailing::query()
            ->selectRaw('MAX(id) as id')
            ->where('mailing_id', $this->id)
            ->groupBy('invoice_id');

        return InvoiceMailing::query()
            ->select('invoice_mailings.*')
            ->joinSub($latest, 'latest', 'latest.id', '=', 'invoice_mailings.id')
            ->with('member:id,first_name,last_name')
            ->orderBy('invoice_mailings.id')
            ->get()
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

    /**
     * Close the batch once nothing is still queued. A retry clears completed_at.
     */
    public function refreshCompletion(): void
    {
        $queued = $this->invoiceMailings()
            ->where('status', InvoiceMailing::STATUS_QUEUED)
            ->exists();

        if ($queued) {
            if ($this->completed_at !== null) {
                $this->forceFill(['completed_at' => null])->save();
            }

            return;
        }

        if ($this->invoiceMailings()->exists() && $this->completed_at === null) {
            $this->forceFill(['completed_at' => now()])->save();
        }
    }

    /**
     * @return array{sent: int, failed: int, queued: int, total: int}
     */
    private static function emptyCounts(): array
    {
        return [
            'sent' => 0,
            'failed' => 0,
            'queued' => 0,
            'total' => 0,
        ];
    }

    private function formatStamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->timezone(config('app.timezone'))->format('d.m.Y. H:i');
    }
};
