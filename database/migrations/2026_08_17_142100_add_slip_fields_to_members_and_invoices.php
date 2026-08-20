<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'slip_payer_name')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('slip_payer_name')->nullable()->after('invoice_email');
            });
        }

        if (! Schema::hasColumn('invoices', 'slip_description')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('slip_description')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('members', 'slip_payer_name')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('slip_payer_name');
            });
        }

        if (Schema::hasColumn('invoices', 'slip_description')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('slip_description');
            });
        }
    }
};
