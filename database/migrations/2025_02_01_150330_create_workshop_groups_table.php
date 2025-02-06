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
        // Each Workshop Has Predefined Groups
        Schema::create('workshop_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workshop_id');
            $table->unsignedBigInteger('member_group_id'); // The group associated with this workshop
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('workshop_id')->references('id')->on('workshops')->onDelete('cascade');
            $table->foreign('member_group_id')->references('id')->on('member_groups')->onDelete('cascade');

            // Prevent duplicate group assignments to the same workshop
            $table->unique(['workshop_id', 'member_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_groups');
    }
};

