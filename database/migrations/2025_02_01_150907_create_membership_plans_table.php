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
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workshop_id'); // Each plan is linked to a specific workshop
            $table->string('plan'); // Plan name (e.g., "6-Month Plan", "Yearly Plan")
            $table->decimal('fee', 8, 2); // Base fee for the plan
            $table->string('billing_frequency')->default('Monthly'); // Billing type (Monthly, Yearly, etc.)
            $table->string('discount_type')->nullable(); // Optional discount type
            $table->decimal('total_fee', 8, 2)->nullable(); // Final fee after discount
            $table->timestamps();
            // Foreign key constraint
            $table->foreign('workshop_id')->references('id')->on('workshops')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
