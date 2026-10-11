<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatementImport extends Model
{
    protected $fillable = [
        'filename',
        'user_id',
        'imported_at',
        'total_rows',
        'incoming_rows',
        'paid_count',
        'mismatch_count',
        'failed_count',
        'unresolved_count',
        'ambiguous_count',
        'skipped_count',
    ];

    protected $casts = [
        'imported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementImportLine::class);
    }
}
