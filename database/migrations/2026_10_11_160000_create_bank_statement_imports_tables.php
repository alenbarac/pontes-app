<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('imported_at');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('incoming_rows')->default(0);
            $table->unsignedInteger('paid_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('unresolved_count')->default(0);
            $table->unsignedInteger('ambiguous_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->timestamps();

            $table->index(['imported_at', 'id']);
        });

        Schema::create('bank_statement_import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_import_id')
                ->constrained('bank_statement_imports')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('kind'); // paid, mismatch, unresolved, ambiguous
            $table->string('payer')->nullable();
            $table->decimal('amount_paid', 8, 2)->default(0);
            $table->decimal('amount_due', 8, 2)->nullable();
            $table->string('reference')->nullable();
            $table->date('value_date')->nullable();
            $table->text('description')->nullable();
            $table->string('message')->nullable();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->json('candidate_invoice_ids')->nullable();
            $table->string('resolution')->default('open'); // open, resolved, dismissed
            $table->timestamps();

            $table->index(['bank_statement_import_id', 'kind', 'resolution'], 'bs_import_lines_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_import_lines');
        Schema::dropIfExists('bank_statement_imports');
    }
};
