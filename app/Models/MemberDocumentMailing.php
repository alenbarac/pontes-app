<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberDocumentMailing extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const FAILURE_MESSAGE = 'Slanje dokumenta nije uspjelo.';

    protected $fillable = [
        'mailing_id',
        'member_document_id',
        'member_id',
        'recipient',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function mailing()
    {
        return $this->belongsTo(Mailing::class);
    }

    public function memberDocument()
    {
        return $this->belongsTo(MemberDocument::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
