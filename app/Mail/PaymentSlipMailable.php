<?php

namespace App\Mail;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentSlipMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public Invoice $invoice,
        public string $recipientEmail
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-slip',
            text: 'emails.payment-slip-text',
            with: $this->bodyData(),
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdf = app(\App\Services\PaymentSlipPdfService::class)->generate($this->invoice);

        // Generate filename: Firstname-Lastname-referencecode.pdf
        $firstName = trim($this->invoice->member->first_name ?? '');
        $lastName = trim($this->invoice->member->last_name ?? '');

        // Transliterate Croatian characters to ASCII
        $firstName = $this->transliterateCroatian($firstName);
        $lastName = $this->transliterateCroatian($lastName);

        // Convert to lowercase, sanitize, then capitalize first letter
        $firstName = strtolower($firstName);
        $lastName = strtolower($lastName);
        // Remove special characters and replace spaces with hyphens
        $firstName = preg_replace('/[^a-z0-9]+/', '-', $firstName);
        $lastName = preg_replace('/[^a-z0-9]+/', '-', $lastName);
        // Capitalize first letter of each name
        $firstName = ucfirst($firstName);
        $lastName = ucfirst($lastName);
        $fileName = trim($firstName.'-'.$lastName.'-'.$this->invoice->reference_code, '-').'.pdf';

        return [
            Attachment::fromData(
                fn () => $pdf->output(),
                $fileName
            )->withMime('application/pdf'),
        ];
    }

    /**
     * Transliterate Croatian characters to ASCII equivalents.
     */
    private function transliterateCroatian(string $text): string
    {
        // Handle multi-character sequences first (DŽ, dž)
        $text = str_replace(['DŽ', 'dž', 'Dž'], ['DJ', 'dj', 'Dj'], $text);

        // Handle single characters
        $transliteration = [
            'Č' => 'C', 'č' => 'c',
            'Ć' => 'C', 'ć' => 'c',
            'Đ' => 'D', 'đ' => 'd',
            'Š' => 'S', 'š' => 's',
            'Ž' => 'Z', 'ž' => 'z',
        ];

        return strtr($text, $transliteration);
    }

    private function subjectLine(): string
    {
        return 'Uplatnica - '.$this->invoice->slipPaymentNotes();
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyData(): array
    {
        $this->invoice->loadMissing(['member', 'workshop']);

        $facts = [];

        $workshopName = trim((string) ($this->invoice->workshop->name ?? ''));
        if ($workshopName !== '') {
            $facts[] = ['label' => 'Radionica', 'value' => $workshopName];
        }

        $currency = trim((string) config('pontes.currency', 'EUR'));
        $facts[] = [
            'label' => 'Iznos',
            'value' => number_format((float) $this->invoice->amount_due, 2, ',', '.').($currency !== '' ? ' '.$currency : ''),
        ];
        $facts[] = [
            'label' => 'Rok plaćanja',
            'value' => Carbon::parse($this->invoice->due_date)->format('d.m.Y.'),
        ];

        $studioName = trim((string) config('pontes.recipient_name'));

        return [
            'title' => $studioName !== '' ? 'Uplatnica - '.$studioName : 'Uplatnica',
            'greeting' => ($name = $this->greetingName()) !== '' ? 'Poštovani/na '.$name.',' : 'Poštovani,',
            'intro' => 'U prilogu Vam šaljemo uplatnicu',
            'membershipLine' => $this->invoice->slipPaymentNotes(),
            'facts' => $facts,
            'signOff' => 'Lijep pozdrav,',
            'studioName' => $studioName,
            'contacts' => $this->contactLinks(),
        ];
    }

    private function greetingName(): string
    {
        $this->invoice->loadMissing('member');

        $payer = trim((string) ($this->invoice->member->slip_payer_name ?? ''));
        if ($payer !== '') {
            return $payer;
        }

        return trim(($this->invoice->member->first_name ?? '').' '.($this->invoice->member->last_name ?? ''));
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
