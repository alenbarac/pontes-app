<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\PaymentSlipMailable;
use App\Models\Invoice;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test 
                            {--invoice= : Invoice ID to send test payment slip}
                            {--to= : Recipient email address (required if no invoice)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email sending with Mailtrap or configured mail driver';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invoiceId = $this->option('invoice');
        $recipientEmail = $this->option('to');

        // Check mail configuration
        $mailDriver = config('mail.default');
        $this->info("Using mail driver: {$mailDriver}");

        if ($invoiceId) {
            // Test with actual invoice
            $invoice = Invoice::with(['member', 'workshop', 'membershipPlan'])->find($invoiceId);
            
            if (!$invoice) {
                $this->error("Invoice with ID {$invoiceId} not found.");
                return 1;
            }

            // Determine recipient email
            $email = $recipientEmail;
            if (!$email) {
                if (!empty($invoice->member->invoice_email)) {
                    $email = $invoice->member->invoice_email;
                } elseif (!empty($invoice->member->email)) {
                    $email = $invoice->member->email;
                } elseif (!empty($invoice->member->parent_email)) {
                    $email = $invoice->member->parent_email;
                }
            }

            if (!$email) {
                $this->error("No email address found for invoice member. Use --to option to specify recipient.");
                return 1;
            }

            $this->info("Sending payment slip email to: {$email}");
            
            try {
                $mailable = new PaymentSlipMailable($invoice, $email);
                Mail::to($email)->send($mailable);
                
                $this->info("✓ Payment slip email sent successfully!");
                $this->info("Check your Mailtrap inbox or logs to verify.");
                return 0;
            } catch (\Exception $e) {
                $this->error("Failed to send email: " . $e->getMessage());
                return 1;
            }
        } else {
            // Simple test email
            if (!$recipientEmail) {
                $this->error("Please provide either --invoice=ID or --to=email@example.com");
                return 1;
            }

            $this->info("Sending test email to: {$recipientEmail}");
            
            try {
                Mail::raw('This is a test email from Pontes App. If you receive this, your mail configuration is working correctly!', function ($message) use ($recipientEmail) {
                    $message->to($recipientEmail)
                            ->subject('Test Email - Pontes App');
                });
                
                $this->info("✓ Test email sent successfully!");
                $this->info("Check your Mailtrap inbox or logs to verify.");
                return 0;
            } catch (\Exception $e) {
                $this->error("Failed to send email: " . $e->getMessage());
                return 1;
            }
        }
    }
}
