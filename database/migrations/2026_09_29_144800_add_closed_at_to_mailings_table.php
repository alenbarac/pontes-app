<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff can close a batch without waiting for every queued message.
     */
    public function up(): void
    {
        Schema::table('mailings', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('mailings', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
