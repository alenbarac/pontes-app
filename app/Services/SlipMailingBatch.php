<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Mailing;
use App\Support\MonthString;
use Carbon\Carbon;

/**
 * One staff action. The mailing row is created when the first slip is queued,
 * so a send that only skips invoices does not leave an empty batch.
 */
class SlipMailingBatch
{
    public ?Mailing $mailing = null;

    public function __construct(
        public readonly string $source,
        public readonly ?int $memberGroupId = null,
        public readonly ?string $month = null,
        public readonly string $audience = '',
        public readonly ?int $userId = null,
    ) {}

    public static function forGroup(int $memberGroupId, string $groupName, string $month, ?int $userId = null): self
    {
        return new self(
            source: Mailing::SOURCE_GROUP,
            memberGroupId: $memberGroupId,
            month: $month,
            audience: 'članovima grupe '.$groupName,
            userId: $userId,
        );
    }

    public static function forMembers(string $month, ?int $userId = null): self
    {
        return new self(
            source: Mailing::SOURCE_MEMBERS,
            month: $month,
            audience: 'odabranim članovima',
            userId: $userId,
        );
    }

    public static function forInvoices(?int $userId = null): self
    {
        return new self(source: Mailing::SOURCE_INVOICES, userId: $userId);
    }

    public function ensure(Invoice $invoice): Mailing
    {
        $invoiceMonth = self::monthFromInvoice($invoice);

        if ($this->mailing === null) {
            $month = $this->month ?? $invoiceMonth;

            $this->mailing = Mailing::query()->create([
                'type' => Mailing::TYPE_SLIP,
                'label' => Mailing::labelFor($month),
                'source' => $this->source,
                'member_group_id' => $this->memberGroupId,
                'month' => $month,
                'user_id' => $this->userId,
                'started_at' => now(),
            ]);

            return $this->mailing;
        }

        if (
            $this->month === null
            && $this->mailing->month !== null
            && $invoiceMonth !== null
            && $invoiceMonth !== $this->mailing->month
        ) {
            $this->mailing->update([
                'month' => null,
                'label' => Mailing::labelFor(null),
            ]);
        }

        return $this->mailing;
    }

    public static function monthFromInvoice(Invoice $invoice): ?string
    {
        if (empty($invoice->due_date)) {
            return null;
        }

        $month = Carbon::parse($invoice->due_date)->format('Y-m');

        return MonthString::isValid($month) ? $month : null;
    }
}
