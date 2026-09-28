<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A mailing is one staff send: a group, a member selection, or invoices.
     * invoice_mailings rows are the recipients.
     */
    public function up(): void
    {
        Schema::create('mailings', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // slip, later reminder / document
            $table->string('label');
            $table->string('source'); // group, members, invoices
            $table->foreignId('member_group_id')->nullable()->constrained()->nullOnDelete();
            $table->char('month', 7)->nullable(); // YYYY-MM
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['started_at', 'id']);
        });

        Schema::table('invoice_mailings', function (Blueprint $table) {
            $table->foreignId('mailing_id')
                ->nullable()
                ->after('id')
                ->constrained('mailings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_mailings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mailing_id');
        });

        Schema::dropIfExists('mailings');
    }
};
