<?php

namespace App\Models;

use App\Support\MonthString;
use Illuminate\Database\Eloquent\Collection;
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
     * One row per invoice: the latest attempt.
     *
     * @return Collection<int, InvoiceMailing>
     */
    public function latestAttempts(): Collection
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
            ->get();
    }

    /**
     * Close the batch once the latest attempt of every invoice has left the queue.
     * A retry clears completed_at. The update is conditional so two workers
     * finishing together do not rely on a stale in-memory timestamp.
     */
    public function refreshCompletion(): void
    {
        $counts = $this->attemptCounts();

        if ($counts['total'] === 0) {
            return;
        }

        if ($counts['queued'] > 0) {
            static::query()
                ->whereKey($this->id)
                ->whereNotNull('completed_at')
                ->update(['completed_at' => null]);

            $this->completed_at = null;

            return;
        }

        $stamp = now();

        $updated = static::query()
            ->whereKey($this->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => $stamp]);

        if ($updated > 0) {
            $this->completed_at = $stamp;
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
}
