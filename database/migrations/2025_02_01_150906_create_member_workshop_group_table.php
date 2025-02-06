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
        // Each Member Can Join Only One Group Per Workshop
        Schema::create('member_workshop_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('workshop_id');
            $table->unsignedBigInteger('member_group_id'); // The group they belong to in this workshop
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('workshop_id')->references('id')->on('workshops')->onDelete('cascade');
            $table->foreign('member_group_id')->references('id')->on('member_groups')->onDelete('cascade');

            // Unique constraint: A member can only belong to one group per workshop
            $table->unique(['member_id', 'workshop_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_workshop_group');
    }
};

