<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id'); // Foreign key to Member
            $table->unsignedBigInteger('workshop_id'); // Foreign key to Workshop
            $table->unsignedBigInteger('membership_plan_id')->nullable(); // Foreign key to Membership Plan
            $table->decimal('amount_due', 8, 2); // Amount to be paid
            $table->decimal('amount_paid', 8, 2)->default(0.00); // Amount already paid
            $table->date('due_date'); // Invoice due date
            $table->string('payment_status')->default('Otvoreno'); // Status: Pending, Paid, Overdue
            $table->string('reference_code')->unique(); // Unique invoice number
            $table->text('notes')->nullable(); // Additional notes for the invoice
            $table->string('school_year', 9)->nullable(); // Format: "2025-2026"
            $table->string('invoice_type', 20)->default('membership'); // 'membership' or 'session'
            $table->date('session_date')->nullable(); // For session-based invoices (meeting date)
            $table->timestamps();

            // Index for invoice type queries
            $table->index('invoice_type');

            // Index for school year queries
            $table->index('school_year');

            // Foreign key constraints
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('workshop_id')->references('id')->on('workshops')->onDelete('cascade');

            // Ensure membership_plans exists before adding FK
            $table->foreign('membership_plan_id')->references('id')->on('membership_plans')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
