<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMailSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('mail_settings')){
            Schema::create('mail_settings', function (Blueprint $table) {
                $table->integer('m_id', true);
                $table->string('api_key', 100)->default('');
                $table->string('api_secret', 100)->default('');
                $table->string('from_email', 100)->default('');
                $table->string('mail_host', 200)->default('');
                $table->string('mail_port', 200)->default('');
                $table->string('mail_username', 200)->default('');
                $table->string('mail_password', 200)->default('');
                $table->string('mail_encryption', 200)->default('');
                $table->char('mail_type', 3)->default('');
                $table->boolean('status')->default(0)->comment('1: yes, 0: no');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mail_settings');
    }
}
