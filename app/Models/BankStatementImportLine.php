<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementImportLine extends Model
{
    public const KIND_PAID = 'paid';

    public const KIND_MISMATCH = 'mismatch';

    public const KIND_UNRESOLVED = 'unresolved';

    public const KIND_AMBIGUOUS = 'ambiguous';

    public const RESOLUTION_OPEN = 'open';

    public const RESOLUTION_RESOLVED = 'resolved';

    public const RESOLUTION_DISMISSED = 'dismissed';

    protected $fillable = [
        'bank_statement_import_id',
        'row_number',
        'kind',
        'payer',
        'amount_paid',
        'amount_due',
        'reference',
        'value_date',
        'description',
        'message',
        'note',
        'member_id',
        'invoice_id',
        'candidate_invoice_ids',
        'resolution',
    ];

    protected $casts = [
        'amount_paid' => 'float',
        'amount_due' => 'float',
        'value_date' => 'date',
        'candidate_invoice_ids' => 'array',
    ];

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
