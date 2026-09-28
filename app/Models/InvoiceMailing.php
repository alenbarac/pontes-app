<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceMailing extends Model
{
    use HasFactory;

    public const TYPE_SLIP = 'slip';

    public const TYPE_REMINDER = 'reminder';

    public const TYPE_DOCUMENT = 'document';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const FAILURE_MESSAGE = 'Slanje uplatnice nije uspjelo.';

    protected $fillable = [
        'invoice_id',
        'member_id',
        'type',
        'recipient',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    protected $appends = [
        'sent_on',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Calendar day of sent_at, as dd.MM. in the app timezone.
     */
    public function getSentOnAttribute(): ?string
    {
        if ($this->sent_at === null) {
            return null;
        }

        return $this->sent_at->timezone(config('app.timezone'))->format('d.m.');
    }

    /**
     * One staff-facing line. Failed rows keep only the short stored message.
     *
     * @return array{status: string, sent_at: string|null, sent_on: string|null, recipient: string|null, error: string|null, resent: bool}
     */
    public function toStaffSummary(bool $resent = false): array
    {
        return [
            'status' => $this->status,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'sent_on' => $this->sent_on,
            'recipient' => $this->recipient,
            'error' => $this->status === self::STATUS_FAILED ? $this->error : null,
            'resent' => $resent,
        ];
    }
}
