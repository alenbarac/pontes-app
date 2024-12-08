<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id'); // Foreign key to Member
            $table->unsignedBigInteger('membership_id')->nullable(); // Foreign key to Membership
            $table->decimal('amount_due', 8, 2); // Amount to be paid
            $table->decimal('amount_paid', 8, 2)->default(0.00); // Amount already paid
            $table->date('due_date'); // Invoice due date
            $table->string('payment_status')->default('Pending'); // Status: Pending, Paid, Overdue
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('membership_id')->references('id')->on('memberships')->onDelete('set null');
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
