<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MailTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('mail_types')){
            Schema::create('mail_types', function (Blueprint $table) {
                $table->integer('mail_type_id', true);
                $table->string('name', 100)->default('');
                $table->char('mail_type', 3)->default('');
                $table->tinyInteger('active')->default(0)->comment('1: yes, 0: no');
            });

             // Insert some stuff
            // DB::table('mail_types')->insert([
            //     [
            //         'name' => 'SendGrid',
            //         'mail_type' => 'SG',
            //         'active' => 0
            //     ],
            //     [
            //         'name' => 'SMTP',
            //         'mail_type' => 'SM',
            //         'active' => 0
            //     ]
            // ]
            // );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('chats');
    }
}
