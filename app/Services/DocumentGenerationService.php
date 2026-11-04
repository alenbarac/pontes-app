<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ZipArchive;

class DocumentGenerationService
{
    /**
     * Replace placeholders in template content with actual data.
     */
    public function replacePlaceholders(
        DocumentTemplate $template,
        Member $member,
        array $additionalData = []
    ): string {
        $content = $template->content;
        $now = Carbon::now();

        // Member placeholders
        $replacements = [
            '{{member.first_name}}' => $member->first_name ?? '',
            '{{member.last_name}}' => $member->last_name ?? '',
            '{{member.full_name}}' => trim(($member->first_name ?? '').' '.($member->last_name ?? '')),
            '{{member.parent_contact}}' => $member->parent_contact ?? '',
            '{{member.parent_email}}' => $member->parent_email ?? '',
            '{{member.date_of_birth}}' => $member->date_of_birth ? Carbon::parse($member->date_of_birth)->format('d.m.Y') : '',
            '{{member.email}}' => $member->email ?? '',
            '{{member.phone_number}}' => $member->phone_number ?? '',
            '{{date}}' => $now->format('Y-m-d'),
            '{{date.formatted}}' => $now->format('d.m.Y'),
        ];

        // Activity placeholders (for Privole)
        if ($template->type === 'privola' && isset($additionalData['activity'])) {
            $activity = $additionalData['activity'];
            $replacements = array_merge($replacements, [
                '{{activity.name}}' => $activity['name'] ?? '',
                '{{activity.date}}' => isset($activity['date']) ? Carbon::parse($activity['date'])->format('d.m.Y') : '',
                '{{activity.start_date}}' => isset($activity['start_date']) ? Carbon::parse($activity['start_date'])->format('d.m.Y') : '',
                '{{activity.end_date}}' => isset($activity['end_date']) ? Carbon::parse($activity['end_date'])->format('d.m.Y') : '',
                '{{activity.location}}' => $activity['location'] ?? '',
                '{{activity.description}}' => $activity['description'] ?? '',
            ]);
        }

        // Custom placeholders from additionalData
        if (isset($additionalData['custom'])) {
            foreach ($additionalData['custom'] as $key => $value) {
                $replacements["{{{$key}}}"] = $value;
            }
        }

        // Replace all placeholders
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace($placeholder, $value, $content);
        }

        return $this->applyGenderPairs($content, $member->gender);
    }

    /**
     * Swap {{g:muški|ženski}} pairs. An unknown gender leaves the token in place.
     */
    public function applyGenderPairs(string $content, ?string $gender): string
    {
        $replaced = preg_replace_callback(
            DocumentTemplate::GENDER_PAIR_PATTERN,
            function (array $matches) use ($gender) {
                $male = trim($matches[1]);
                $female = trim($matches[2]);

                return match ($gender) {
                    Member::GENDER_FEMALE => $female,
                    Member::GENDER_MALE => $male,
                    default => $matches[0],
                };
            },
            $content
        );

        return is_string($replaced) ? $replaced : $content;
    }

    /**
     * Members who still need a gender because this template uses pair tokens.
     *
     * @param  Collection<int, Member>  $members
     * @return Collection<int, Member>
     */
    public function membersMissingGender(DocumentTemplate $template, Collection $members): Collection
    {
        if (! $template->hasGenderPairs()) {
            return collect();
        }

        return $members
            ->filter(fn (Member $member) => ! in_array($member->gender, [Member::GENDER_MALE, Member::GENDER_FEMALE], true))
            ->values();
    }

    /**
     * @param  Collection<int, Member>  $members
     * @return array{message: string, missing_gender: list<array{id: int, first_name: string, last_name: string}>}
     */
    public function missingGenderPayload(Collection $members): array
    {
        return [
            'message' => 'Nekim članovima nedostaje spol. Odaberite muški ili ženski oblik prije nastavka.',
            'missing_gender' => $members->map(fn (Member $member) => [
                'id' => $member->id,
                'first_name' => $member->first_name ?? '',
                'last_name' => $member->last_name ?? '',
            ])->values()->all(),
        ];
    }

    /**
     * Generate PDF for a single member.
     *
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generatePDF(
        DocumentTemplate $template,
        Member $member,
        array $additionalData = []
    ) {
        $content = $this->replacePlaceholders($template, $member, $additionalData);

        $data = [
            'content' => $content,
            'template' => $template,
            'member' => $member,
        ];

        $view = 'documents.template';

        if (in_array($template->type, ['ispricnica', 'potvrda'], true)) {
            $view = 'documents.ispricnica';
            $data = array_merge($data, [
                'documentTitle' => $template->type === 'potvrda' ? 'POTVRDA' : 'Ispričnica',
                'todayDate' => Carbon::now()->format('d.m.Y.'),
                'logoPath' => public_path('images/documents/ispricnica-logo.png'),
                'signaturePath' => public_path('images/documents/ispricnica-signature.png'),
                'footerRecipientName' => (string) config('pontes.recipient_name', ''),
                'footerRecipientAddress' => (string) config('pontes.recipient_address', ''),
                'footerRecipientPostal' => (string) config('pontes.recipient_postal', ''),
                'footerWebUrl' => (string) config('pontes.web_url', env('PONTES_WEB_URL', '')),
                'footerEmail' => (string) config('pontes.email', env('PONTES_EMAIL', '')),
                'footerPhone' => (string) config('pontes.phone', env('PONTES_PHONE', '')),
            ]);
        }

        $pdf = Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        return $pdf;
    }

    /**
     * Generate PDFs for multiple members and return as zip.
     *
     * @return string Path to zip file
     */
    public function generateBulkPDFs(
        DocumentTemplate $template,
        Collection $members,
        array $additionalData = []
    ): string {
        $tempDir = storage_path('app/temp/documents');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir.'/documents_'.time().'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create zip file');
        }

        foreach ($members as $member) {
            $pdf = $this->generatePDF($template, $member, $additionalData);
            $memberName = str_replace([' ', '/', '\\'], '_', trim(($member->first_name ?? '').'_'.($member->last_name ?? '')));
            $filename = $memberName.'_'.$template->name.'.pdf';
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Generate PDF and return as download response.
     *
     * @return \Illuminate\Http\Response
     */
    public function downloadPDF(
        DocumentTemplate $template,
        Member $member,
        array $additionalData = []
    ) {
        $pdf = $this->generatePDF($template, $member, $additionalData);
        $filename = str_replace([' ', '/', '\\'], '_', $template->name.'_'.trim(($member->first_name ?? '').'_'.($member->last_name ?? ''))).'.pdf';

        return $pdf->download($filename);
    }
}
