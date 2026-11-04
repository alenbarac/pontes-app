<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('email_templates')->updateOrInsert(
            ['key' => 'member_document'],
            [
                'subject' => '{naziv_dokumenta} - {ime}',
                'heading' => '{naziv_dokumenta}',
                'greeting' => 'Poštovani/na {ime},',
                'intro' => 'U prilogu Vam šaljemo dokument {naziv_dokumenta}.',
                'sign_off' => 'Lijep pozdrav,',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('email_templates')->where('key', 'member_document')->delete();
    }
};
