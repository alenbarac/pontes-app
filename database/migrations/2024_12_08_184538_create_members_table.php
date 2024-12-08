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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('first_name'); // First name
            $table->string('last_name')->nullable(); // Optional last name
            $table->year('birth_year'); // Birth year
            $table->string('phone_number'); // Phone number
            $table->string('email')->unique(); // Unique email
            $table->boolean('is_active')->default(true); // Active status
           
            $table->string('parent_contact')->nullable(); // Parent contact (phone)
            $table->string('parent_email')->nullable(); // Parent email
            $table->timestamps();

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
