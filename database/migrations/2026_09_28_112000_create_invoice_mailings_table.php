<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Outbound log for payment-slip (and later reminder / document) emails.
     */
    public function up(): void
    {
        Schema::create('invoice_mailings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // slip, reminder, document
            $table->string('recipient');
            $table->string('status'); // queued, sent, failed
            $table->string('error')->nullable(); // short, no SMTP internals
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_mailings');
    }
};
