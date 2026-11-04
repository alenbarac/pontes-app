<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMemberDocumentsRequest;
use App\Models\DocumentTemplate;
use App\Models\Mailing;
use App\Models\Member;
use App\Models\MemberDocument;
use App\Models\MemberGroup;
use App\Services\DocumentGenerationService;
use App\Services\DocumentMailingBatch;
use App\Services\MemberDocumentEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberDocumentEmailController extends Controller
{
    public function __construct(
        protected MemberDocumentEmailService $emails,
        protected DocumentGenerationService $documents,
    ) {}

    public function previewForGroup(SendMemberDocumentsRequest $request, MemberGroup $memberGroup): JsonResponse
    {
        $resolution = $this->resolveGroupMembers($request->validated(), $memberGroup);
        if ($resolution['error'] !== null) {
            return $resolution['error'];
        }

        $template = $this->activeTemplate((int) $request->validated('document_template_id'));
        if ($template instanceof JsonResponse) {
            return $template;
        }

        return response()->json($this->emails->preview(
            $resolution['members'],
            $template,
            $request->boolean('resend'),
        ));
    }

    public function sendForGroup(SendMemberDocumentsRequest $request, MemberGroup $memberGroup): JsonResponse
    {
        $resolution = $this->resolveGroupMembers($request->validated(), $memberGroup);
        if ($resolution['error'] !== null) {
            return $resolution['error'];
        }

        $template = $this->activeTemplate((int) $request->validated('document_template_id'));
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $result = $this->emails->send(
            $resolution['members'],
            $template,
            $this->additionalData($request),
            $request->boolean('resend'),
            new DocumentMailingBatch(
                Mailing::SOURCE_GROUP,
                $memberGroup->id,
                $template->name,
                $request->user()?->id,
            ),
        );

        return $this->sendResponse($result);
    }

    public function previewForMember(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'document_template_id' => 'required|integer|exists:document_templates,id',
            'resend' => 'sometimes|boolean',
        ]);

        $template = $this->activeTemplate((int) $validated['document_template_id']);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        return response()->json($this->emails->preview(
            collect([$member]),
            $template,
            $request->boolean('resend'),
        ));
    }

    public function sendForMember(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'document_template_id' => 'required|integer|exists:document_templates,id',
            'activity' => 'nullable|array',
            'resend' => 'sometimes|boolean',
        ]);

        $template = $this->activeTemplate((int) $validated['document_template_id']);
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $result = $this->emails->send(
            collect([$member]),
            $template,
            $this->additionalData($request),
            $request->boolean('resend'),
            new DocumentMailingBatch(
                Mailing::SOURCE_MEMBERS,
                null,
                $template->name,
                $request->user()?->id,
            ),
        );

        return $this->sendResponse($result);
    }

    public function previewForDocument(Request $request, MemberDocument $memberDocument): JsonResponse
    {
        $memberDocument->load(['member', 'documentTemplate']);

        return response()->json($this->emails->preview(
            collect([$memberDocument->member]),
            $memberDocument->documentTemplate,
            $request->boolean('resend'),
        ));
    }

    public function sendForDocument(Request $request, MemberDocument $memberDocument): JsonResponse
    {
        $request->validate([
            'resend' => 'sometimes|boolean',
        ]);

        $memberDocument->load(['member', 'documentTemplate']);
        $stored = $memberDocument->additional_data;
        $additional = is_array($stored) ? $stored : [];

        $result = $this->emails->send(
            collect([$memberDocument->member]),
            $memberDocument->documentTemplate,
            $additional,
            $request->boolean('resend'),
            new DocumentMailingBatch(
                Mailing::SOURCE_MEMBERS,
                null,
                $memberDocument->documentTemplate->name,
                $request->user()?->id,
            ),
        );

        return $this->sendResponse($result);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{members: Collection<int, Member>, error: JsonResponse|null}
     */
    private function resolveGroupMembers(array $validated, MemberGroup $memberGroup): array
    {
        $allowedIds = $this->memberIdsAssignedToGroup($memberGroup);

        if (($validated['send_scope'] ?? null) === 'all') {
            $memberIds = $allowedIds;
        } else {
            $memberIds = array_values(array_intersect(
                array_map('intval', $validated['member_ids'] ?? []),
                $allowedIds,
            ));
        }

        if ($memberIds === []) {
            return [
                'members' => collect(),
                'error' => response()->json([
                    'message' => 'Nema članova za slanje.',
                    'errors' => ['member_ids' => ['Nema članova za slanje.']],
                ], 422),
            ];
        }

        return [
            'members' => Member::query()->whereIn('id', $memberIds)->orderBy('last_name')->orderBy('first_name')->get(),
            'error' => null,
        ];
    }

    /**
     * @return list<int>
     */
    private function memberIdsAssignedToGroup(MemberGroup $memberGroup): array
    {
        $memberGroup->load('assignedWorkshop');
        $workshopId = $memberGroup->assignedWorkshop?->id;

        return DB::table('member_workshop_group')
            ->where('member_group_id', $memberGroup->id)
            ->when($workshopId, function ($query) use ($workshopId) {
                $query->where('workshop_id', $workshopId);
            })
            ->pluck('member_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function activeTemplate(int $id): DocumentTemplate|JsonResponse
    {
        $template = DocumentTemplate::query()->findOrFail($id);

        if (! $template->is_active) {
            return response()->json([
                'message' => 'Predložak nije aktivan.',
            ], 422);
        }

        return $template;
    }

    /**
     * @return array<string, mixed>
     */
    private function additionalData(Request $request): array
    {
        $activity = $request->input('activity');

        if (! is_array($activity)) {
            return [];
        }

        return ['activity' => $activity];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function sendResponse(array $result): JsonResponse
    {
        if (! empty($result['exceeds_cap'])) {
            return response()->json([
                'message' => MemberDocumentEmailService::maxMessage(),
            ], 422);
        }

        if (isset($result['missing_gender']) && $result['missing_gender'] instanceof Collection && $result['missing_gender']->isNotEmpty()) {
            return response()->json($this->documents->missingGenderPayload($result['missing_gender']), 422);
        }

        if (! empty($result['recipient_not_allowed'])) {
            return response()->json([
                'message' => $result['message'] ?? 'Adresa primatelja nije dozvoljena.',
            ], 422);
        }

        return response()->json([
            'queued' => $result['queued'],
            'skipped_no_email' => $result['skipped_no_email'],
            'skipped_already_sent' => $result['skipped_already_sent'],
            'failed' => $result['failed'],
            'mailing_id' => $result['mailing_id'],
            'message' => $result['message'] ?? $this->emails->humanSummary($result),
        ]);
    }
}
