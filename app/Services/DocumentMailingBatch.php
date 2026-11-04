<?php

namespace App\Services;

use App\Models\Mailing;

/**
 * One staff action that emails member documents. The row is created when the
 * first message is queued, so a send that only skips members leaves no batch.
 */
class DocumentMailingBatch
{
    public ?Mailing $mailing = null;

    public function __construct(
        public readonly string $source,
        public readonly ?int $memberGroupId,
        public readonly string $label,
        public readonly ?int $userId = null,
    ) {}

    public function ensure(): Mailing
    {
        if ($this->mailing === null) {
            $this->mailing = Mailing::query()->create([
                'type' => Mailing::TYPE_DOCUMENT,
                'label' => $this->label,
                'source' => $this->source,
                'member_group_id' => $this->memberGroupId,
                'month' => null,
                'user_id' => $this->userId,
                'started_at' => now(),
            ]);
        }

        return $this->mailing;
    }
}
