<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\MemberDocument;
use App\Services\DocumentGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberDocumentMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MemberDocument $document,
    ) {}

    /** @var array<string, string>|null */
    private ?array $copy = null;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->lineFromTemplate('subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-slip',
            text: 'emails.payment-slip-text',
            with: $this->bodyData(),
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $this->document->loadMissing(['member', 'documentTemplate']);

        $pdf = app(DocumentGenerationService::class)->generatePDF(
            $this->document->documentTemplate,
            $this->document->member,
            $this->document->additional_data ?? [],
        );

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $this->fileName()
            )->withMime('application/pdf'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyData(): array
    {
        $this->document->loadMissing(['member', 'documentTemplate']);
        $studioName = trim((string) config('pontes.recipient_name'));
        $values = $this->placeholderValues($studioName);
        $memberName = $this->memberName();

        return [
            'title' => $this->lineFromTemplate('heading', $values),
            'greeting' => $this->lineFromTemplate('greeting', $values),
            'intro' => $this->lineFromTemplate('intro', $values),
            'membershipLine' => $values['naziv_dokumenta'],
            'facts' => [
                ['label' => 'Član', 'value' => $memberName !== '' ? $memberName : 'Član'],
            ],
            'signOff' => $this->lineFromTemplate('sign_off', $values),
            'studioName' => $studioName,
            'contacts' => $this->contactLinks(),
        ];
    }

    /**
     * @return array{ime: string, opis: string, studio: string, naziv_dokumenta: string}
     */
    private function placeholderValues(string $studioName): array
    {
        return [
            'ime' => $this->memberName(),
            'opis' => '',
            'studio' => $studioName,
            'naziv_dokumenta' => trim((string) ($this->document->documentTemplate->name ?? '')),
        ];
    }

    /**
     * @param  array{ime?: string, opis?: string, studio?: string, naziv_dokumenta?: string}|null  $values
     */
    private function lineFromTemplate(string $field, ?array $values = null): string
    {
        $values ??= $this->placeholderValues(trim((string) config('pontes.recipient_name')));

        return EmailTemplate::interpolate($this->copy()[$field], $values);
    }

    /**
     * @return array<string, string>
     */
    private function copy(): array
    {
        if ($this->copy !== null) {
            return $this->copy;
        }

        $template = EmailTemplate::forKey(EmailTemplate::DOCUMENT);

        return $this->copy = [
            'subject' => $template->subject,
            'heading' => $template->heading,
            'greeting' => $template->greeting,
            'intro' => $template->intro,
            'sign_off' => $template->sign_off,
        ];
    }

    private function memberName(): string
    {
        $this->document->loadMissing('member');

        return trim(($this->document->member->first_name ?? '').' '.($this->document->member->last_name ?? ''));
    }

    private function fileName(): string
    {
        $templateName = trim((string) ($this->document->documentTemplate->name ?? 'dokument'));
        $base = trim($templateName.' '.$this->memberName());
        $base = $this->transliterateCroatian($base);
        $base = strtolower($base);
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $base), '-');

        return ($base !== '' ? $base : 'dokument').'.pdf';
    }

    private function transliterateCroatian(string $text): string
    {
        $text = str_replace(['DŽ', 'dž', 'Dž'], ['DJ', 'dj', 'Dj'], $text);

        return strtr($text, [
            'Č' => 'C', 'č' => 'c',
            'Ć' => 'C', 'ć' => 'c',
            'Đ' => 'D', 'đ' => 'd',
            'Š' => 'S', 'š' => 's',
            'Ž' => 'Z', 'ž' => 'z',
        ]);
    }

    /**
     * @return array<int, array{href: string, label: string}>
     */
    private function contactLinks(): array
    {
        $links = [];

        $email = trim((string) config('pontes.email'));
        if ($email !== '') {
            $links[] = ['href' => 'mailto:'.$email, 'label' => $email];
        }

        $phone = trim((string) config('pontes.phone'));
        if ($phone !== '') {
            $links[] = [
                'href' => 'tel:'.preg_replace('/[^\d+]/', '', $phone),
                'label' => $phone,
            ];
        }

        $webUrl = trim((string) config('pontes.web_url'));
        if ($webUrl !== '') {
            $links[] = ['href' => $webUrl, 'label' => $webUrl];
        }

        return $links;
    }
}
