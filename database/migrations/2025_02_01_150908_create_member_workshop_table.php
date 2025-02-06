<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_workshop', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('workshop_id');
            $table->unsignedBigInteger('membership_plan_id')->nullable(); // Membership assigned to this workshop
            $table->timestamps();

            // Foreign Key Constraints
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('workshop_id')->references('id')->on('workshops')->onDelete('cascade');
            $table->foreign('membership_plan_id')->references('id')->on('membership_plans')->onDelete('cascade');

            // Unique Constraint: A member can only have one membership per workshop
            $table->unique(['member_id', 'workshop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_workshop');
    }
};
