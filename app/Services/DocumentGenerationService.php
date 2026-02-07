<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\Member;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentGenerationService
{
    /**
     * Replace placeholders in template content with actual data.
     *
     * @param DocumentTemplate $template
     * @param Member $member
     * @param array $additionalData
     * @return string
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
            '{{member.full_name}}' => trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')),
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

        return $content;
    }

    /**
     * Generate PDF for a single member.
     *
     * @param DocumentTemplate $template
     * @param Member $member
     * @param array $additionalData
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

        $pdf = Pdf::loadView('documents.template', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('enable-local-file-access', true);

        return $pdf;
    }

    /**
     * Generate PDFs for multiple members and return as zip.
     *
     * @param DocumentTemplate $template
     * @param Collection $members
     * @param array $additionalData
     * @return string Path to zip file
     */
    public function generateBulkPDFs(
        DocumentTemplate $template,
        Collection $members,
        array $additionalData = []
    ): string {
        $tempDir = storage_path('app/temp/documents');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipPath = $tempDir . '/documents_' . time() . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Cannot create zip file');
        }

        foreach ($members as $member) {
            $pdf = $this->generatePDF($template, $member, $additionalData);
            $memberName = str_replace([' ', '/', '\\'], '_', trim(($member->first_name ?? '') . '_' . ($member->last_name ?? '')));
            $filename = $memberName . '_' . $template->name . '.pdf';
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Generate PDF and return as download response.
     *
     * @param DocumentTemplate $template
     * @param Member $member
     * @param array $additionalData
     * @return \Illuminate\Http\Response
     */
    public function downloadPDF(
        DocumentTemplate $template,
        Member $member,
        array $additionalData = []
    ) {
        $pdf = $this->generatePDF($template, $member, $additionalData);
        $filename = str_replace([' ', '/', '\\'], '_', $template->name . '_' . trim(($member->first_name ?? '') . '_' . ($member->last_name ?? ''))) . '.pdf';

        return $pdf->download($filename);
    }
}
