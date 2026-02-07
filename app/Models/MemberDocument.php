<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberDocument extends Model
{
    protected $fillable = [
        'member_id',
        'document_template_id',
        'document_type',
        'additional_data',
        'file_path',
    ];

    protected $casts = [
        'additional_data' => 'array',
    ];

    /**
     * Get the member that owns this document.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the template used for this document.
     */
    public function documentTemplate()
    {
        return $this->belongsTo(DocumentTemplate::class);
    }
}
