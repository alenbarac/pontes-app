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
        Schema::create('member_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Group name
            $table->text('description')->nullable(); // Optional description
            $table->timestamps();
        });

        // Pivot table for many-to-many relationship between Members and MemberGroups
        Schema::create('member_group_member', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('member_group_id');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('member_group_id')->references('id')->on('member_groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop pivot table first
        Schema::dropIfExists('member_group_member');
        Schema::dropIfExists('member_groups');
    }
};
