<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('is_active');
        });

        Schema::table('member_groups', function (Blueprint $table) {
            $table->boolean('is_test')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });

        Schema::table('member_groups', function (Blueprint $table) {
            $table->dropColumn('is_test');
        });
    }
};
