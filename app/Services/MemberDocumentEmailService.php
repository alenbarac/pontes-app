<?php

namespace App\Services;

use App\Jobs\SendMemberDocumentMailing;
use App\Models\DocumentTemplate;
use App\Models\Mailing;
use App\Models\MailSetting;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\MemberDocumentMailing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MemberDocumentEmailService
{
    public const MAX_PER_SEND = 100;

    public function __construct(
        protected DocumentGenerationService $documents,
    ) {}

    public static function maxMessage(): string
    {
        return 'Možete poslati najviše '.self::MAX_PER_SEND.' dokumenata odjednom.';
    }

    /**
     * @param  Collection<int, Member>  $members
     * @return array<string, mixed>
     */
    public function preview(Collection $members, DocumentTemplate $template, bool $resend = false): array
    {
        $plan = $this->plan($members, $template, $resend);

        return [
            'template_name' => $template->name,
            'has_gender_pairs' => $template->hasGenderPairs(),
            'member_count' => $members->count(),
            'missing_gender' => $plan['missing_gender'],
            'skipped_no_email' => $plan['skipped_no_email'],
            'already_sent' => $plan['already_sent'],
            'deliverable_count' => $plan['deliverable_count'],
            'exceeds_cap' => $plan['exceeds_cap'],
            'recipient_not_allowed' => $plan['recipient_not_allowed'],
            'message' => $plan['message'],
        ];
    }

    /**
     * Upsert one document per member and queue a mail to invoice_email.
     *
     * @param  Collection<int, Member>  $members
     * @param  array<string, mixed>  $additionalData
     * @return array<string, mixed>
     */
    public function send(
        Collection $members,
        DocumentTemplate $template,
        array $additionalData,
        bool $resend,
        DocumentMailingBatch $batch,
    ): array {
        $plan = $this->plan($members, $template, $resend);

        if ($plan['exceeds_cap']) {
            return $this->emptyResult(exceedsCap: true);
        }

        if ($plan['missing_gender_members']->isNotEmpty()) {
            return array_merge($this->emptyResult(), [
                'missing_gender' => $plan['missing_gender_members'],
            ]);
        }

        if ($plan['recipient_not_allowed']) {
            return array_merge($this->emptyResult(), [
                'recipient_not_allowed' => true,
                'message' => $plan['message'],
            ]);
        }

        $queued = 0;
        $failed = [];

        DB::transaction(function () use ($plan, $template, $additionalData, $batch, &$queued, &$failed) {
            foreach ($plan['deliverable'] as $row) {
                /** @var Member $member */
                $member = $row['member'];
                $document = $this->upsertDocument($template, $member, $additionalData);
                $parent = $batch->ensure();

                $mailing = MemberDocumentMailing::query()->create([
                    'mailing_id' => $parent->id,
                    'member_document_id' => $document->id,
                    'member_id' => $member->id,
                    'recipient' => $row['email'],
                    'status' => MemberDocumentMailing::STATUS_QUEUED,
                    'error' => null,
                    'sent_at' => null,
                ]);

                try {
                    SendMemberDocumentMailing::dispatch($mailing->id);
                    $queued++;
                } catch (Throwable $e) {
                    $mailing->update([
                        'status' => MemberDocumentMailing::STATUS_FAILED,
                        'error' => MemberDocumentMailing::FAILURE_MESSAGE,
                    ]);
                    $failed[] = [
                        'id' => $member->id,
                        'name' => $this->memberName($member),
                    ];

                    Log::error('Failed to queue member document email', [
                        'member_id' => $member->id,
                        'member_document_id' => $document->id,
                        'member_document_mailing_id' => $mailing->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        if ($queued > 0) {
            $batch->mailing?->refreshCompletion();
        }

        return [
            'queued' => $queued,
            'skipped_no_email' => $plan['skipped_no_email'],
            'skipped_already_sent' => $plan['already_sent'],
            'failed' => $failed,
            'exceeds_cap' => false,
            'mailing_id' => $batch->mailing?->id,
            'message' => null,
        ];
    }

    /**
     * @return array{queued: int, recipient_not_allowed: bool, message?: string}
     */
    public function retryFailed(Mailing $mailing): array
    {
        $latest = MemberDocumentMailing::query()
            ->selectRaw('MAX(id) as id')
            ->where('mailing_id', $mailing->id)
            ->groupBy('member_document_id');

        $failed = MemberDocumentMailing::query()
            ->select('member_document_mailings.*')
            ->joinSub($latest, 'latest', 'latest.id', '=', 'member_document_mailings.id')
            ->where('member_document_mailings.status', MemberDocumentMailing::STATUS_FAILED)
            ->get();

        if ($message = MailSetting::blockMessageFor($failed->pluck('recipient'))) {
            return [
                'queued' => 0,
                'recipient_not_allowed' => true,
                'message' => $message,
            ];
        }

        $queued = 0;

        foreach ($failed as $row) {
            $created = MemberDocumentMailing::query()->create([
                'mailing_id' => $mailing->id,
                'member_document_id' => $row->member_document_id,
                'member_id' => $row->member_id,
                'recipient' => $row->recipient,
                'status' => MemberDocumentMailing::STATUS_QUEUED,
                'error' => null,
                'sent_at' => null,
            ]);

            try {
                SendMemberDocumentMailing::dispatch($created->id);
                $queued++;
            } catch (Throwable $e) {
                $created->update([
                    'status' => MemberDocumentMailing::STATUS_FAILED,
                    'error' => MemberDocumentMailing::FAILURE_MESSAGE,
                ]);

                Log::error('Failed to queue member document email', [
                    'member_document_mailing_id' => $created->id,
                    'mailing_id' => $mailing->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($queued > 0) {
            $mailing->forceFill(['completed_at' => null])->save();
            $mailing->refreshCompletion();
        }

        return [
            'queued' => $queued,
            'recipient_not_allowed' => false,
        ];
    }

    /**
     * @param  array{queued?: int, skipped_no_email?: list<array<string, mixed>>, skipped_already_sent?: list<array<string, mixed>>, failed?: list<array<string, mixed>>}  $summary
     */
    public function humanSummary(array $summary): string
    {
        $parts = [];
        $queued = (int) ($summary['queued'] ?? 0);

        if ($queued > 0) {
            $parts[] = 'Pokrenuto je slanje '.$queued.' '.$this->noun($queued).'.';
        }

        $skipCount = count($summary['skipped_no_email'] ?? []);
        if ($skipCount > 0) {
            $parts[] = $skipCount.' '.$this->noun($skipCount).' nema e-mail za račune i zato '
                .($skipCount === 1 ? 'je preskočen' : 'su preskočeni').'.';
        }

        $alreadySentCount = count($summary['skipped_already_sent'] ?? []);
        if ($alreadySentCount > 0) {
            $parts[] = $alreadySentCount.' '.$this->noun($alreadySentCount)
                .($alreadySentCount === 1 ? ' je već poslan' : ' je već poslano')
                .' i zato '.($alreadySentCount === 1 ? 'je preskočen' : 'su preskočeni').'.';
        }

        $failCount = count($summary['failed'] ?? []);
        if ($failCount > 0) {
            $parts[] = $failCount.' '.$this->noun($failCount).' nije moglo krenuti na slanje.';
        }

        if ($parts === []) {
            return 'Slanje nije pokrenuto.';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  Collection<int, Member>  $members
     * @return array<string, mixed>
     */
    private function plan(Collection $members, DocumentTemplate $template, bool $resend): array
    {
        $members = $members->values();
        $emptyLists = [
            'missing_gender' => [],
            'missing_gender_members' => collect(),
            'skipped_no_email' => [],
            'already_sent' => [],
            'deliverable' => [],
            'deliverable_count' => 0,
            'exceeds_cap' => false,
            'recipient_not_allowed' => false,
            'message' => null,
        ];

        if ($members->count() > self::MAX_PER_SEND) {
            return array_merge($emptyLists, [
                'exceeds_cap' => true,
                'message' => self::maxMessage(),
            ]);
        }

        $missing = $this->documents->membersMissingGender($template, $members);
        $missingIds = $missing->pluck('id')->map(fn ($id) => (int) $id)->all();
        $sentMemberIds = $resend ? [] : $this->sentMemberIds($members, $template);

        $skippedNoEmail = [];
        $alreadySent = [];
        $deliverable = [];

        foreach ($members as $member) {
            if (in_array((int) $member->id, $missingIds, true)) {
                continue;
            }

            $name = $this->memberName($member);
            $email = trim((string) ($member->invoice_email ?? ''));

            if (isset($sentMemberIds[(int) $member->id])) {
                $alreadySent[] = [
                    'id' => $member->id,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                ];

                continue;
            }

            if ($email === '') {
                $skippedNoEmail[] = [
                    'id' => $member->id,
                    'name' => $name,
                ];

                continue;
            }

            $deliverable[] = [
                'member' => $member,
                'email' => $email,
            ];
        }

        $blockMessage = MailSetting::blockMessageFor(array_column($deliverable, 'email'));

        return [
            'missing_gender' => $missing->map(fn (Member $member) => [
                'id' => $member->id,
                'first_name' => $member->first_name ?? '',
                'last_name' => $member->last_name ?? '',
            ])->values()->all(),
            'missing_gender_members' => $missing,
            'skipped_no_email' => $skippedNoEmail,
            'already_sent' => $alreadySent,
            'deliverable' => $deliverable,
            'deliverable_count' => count($deliverable),
            'exceeds_cap' => false,
            'recipient_not_allowed' => $blockMessage !== null,
            'message' => $blockMessage,
        ];
    }

    /**
     * @param  Collection<int, Member>  $members
     * @return array<int, true>
     */
    private function sentMemberIds(Collection $members, DocumentTemplate $template): array
    {
        $memberIds = $members->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($memberIds === []) {
            return [];
        }

        return MemberDocumentMailing::query()
            ->where('status', MemberDocumentMailing::STATUS_SENT)
            ->whereIn('member_id', $memberIds)
            ->whereHas('memberDocument', function ($query) use ($template) {
                $query->where('document_template_id', $template->id);
            })
            ->pluck('member_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $additionalData
     */
    private function upsertDocument(DocumentTemplate $template, Member $member, array $additionalData): MemberDocument
    {
        $existing = MemberDocument::query()
            ->where('member_id', $member->id)
            ->where('document_template_id', $template->id)
            ->where('document_type', $template->type)
            ->first();

        if ($existing !== null) {
            if ($additionalData !== []) {
                $existing->update(['additional_data' => $additionalData]);
            }

            return $existing;
        }

        return MemberDocument::query()->create([
            'member_id' => $member->id,
            'document_template_id' => $template->id,
            'document_type' => $template->type,
            'additional_data' => $additionalData,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(bool $exceedsCap = false): array
    {
        return [
            'queued' => 0,
            'skipped_no_email' => [],
            'skipped_already_sent' => [],
            'failed' => [],
            'exceeds_cap' => $exceedsCap,
            'mailing_id' => null,
            'message' => $exceedsCap ? self::maxMessage() : null,
        ];
    }

    private function memberName(Member $member): string
    {
        $name = trim(($member->first_name ?? '').' '.($member->last_name ?? ''));

        return $name !== '' ? $name : 'Član';
    }

    private function noun(int $count): string
    {
        $mod100 = $count % 100;
        $mod10 = $count % 10;

        if ($mod100 >= 11 && $mod100 <= 14) {
            return 'dokumenata';
        }

        if ($mod10 === 1) {
            return 'dokument';
        }

        if ($mod10 >= 2 && $mod10 <= 4) {
            return 'dokumenta';
        }

        return 'dokumenata';
    }
}
