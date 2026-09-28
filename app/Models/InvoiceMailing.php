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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
