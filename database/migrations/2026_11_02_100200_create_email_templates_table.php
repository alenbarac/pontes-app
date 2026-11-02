<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('subject');
            $table->string('heading');
            $table->string('greeting');
            $table->text('intro');
            $table->string('sign_off');
            $table->timestamps();
        });

        $now = now();

        DB::table('email_templates')->insert([
            [
                'key' => 'payment_slip',
                'subject' => 'Uplatnica - {opis}',
                'heading' => 'Uplatnica - {studio}',
                'greeting' => 'Poštovani/na {ime},',
                'intro' => 'U prilogu Vam šaljemo uplatnicu',
                'sign_off' => 'Lijep pozdrav,',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'payment_reminder',
                'subject' => 'Podsjetnik za uplatu - {opis}',
                'heading' => 'Podsjetnik za uplatu',
                'greeting' => 'Poštovani/na {ime},',
                'intro' => 'Unutar naše evidencije ustanovili smo da nije podmiren račun. U prilogu ponovno šaljemo uplatnicu.',
                'sign_off' => 'Lijep pozdrav,',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
