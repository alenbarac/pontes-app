<?php

namespace App\Mail;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
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
            subject: 'Uplatnica - '.$this->invoice->reference_code,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $invoice = $this->invoice;
        $invoice->load(['member', 'workshop', 'membershipPlan']);

        $memberFullName = trim(($invoice->member->first_name ?? '').' '.($invoice->member->last_name ?? ''));
        $slipPayerOverride = trim((string) ($invoice->member->slip_payer_name ?? ''));
        $payerDisplayName = $slipPayerOverride !== '' ? $slipPayerOverride : $memberFullName;
        $amount = number_format((float) $invoice->amount_due, 2, ',', '.');
        $dueDate = \Carbon\Carbon::parse($invoice->due_date)->format('d.m.Y.');

        return new Content(
            view: 'emails.payment-slip',
            with: [
                'memberName' => $payerDisplayName,
                'referenceCode' => $invoice->reference_code,
                'amount' => $amount,
                'dueDate' => $dueDate,
                'workshopName' => $invoice->workshop->name ?? '',
            ],
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
}
