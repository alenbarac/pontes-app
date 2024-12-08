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
        Schema::create('workshops', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Workshop name
            $table->string('type'); // Type of workshop (e.g., "Group", "Individual")
            $table->text('description')->nullable(); // Optional description
            $table->timestamps();
        });

        // Pivot table for many-to-many relationship between Members and Workshops
        Schema::create('member_workshop', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('workshop_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('workshop_id')->references('id')->on('workshops')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop pivot table first
        Schema::dropIfExists('member_workshop');
        Schema::dropIfExists('workshops');
    }
};
