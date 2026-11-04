<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_document_mailings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailing_id')->constrained('mailings')->cascadeOnDelete();
            $table->foreignId('member_document_id')->constrained('member_documents')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('recipient');
            $table->string('status');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['member_document_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_document_mailings');
    }
};
