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
        Schema::create('member_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_template_id')->constrained()->onDelete('cascade');
            $table->string('document_type'); // 'ispricnica' or 'privola'
            $table->text('additional_data')->nullable(); // JSON for activity data, etc.
            $table->string('file_path')->nullable(); // Optional: if we want to store generated PDFs
            $table->timestamps();

            $table->index(['member_id', 'document_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_documents');
    }
};
