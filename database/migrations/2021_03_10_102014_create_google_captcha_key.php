<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGoogleCaptchaKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('google_captcha_key')){
            Schema::create('google_captcha_key', function (Blueprint $table) {
                $table->id();
                $table->string('site_key')->default('');
                $table->string('secret_key')->default('');
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
        Schema::dropIfExists('google_captcha_key');
    }
}
