<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('social_settings')){
            Schema::create('social_settings', function (Blueprint $table) {
                $table->integer('social_setting_id', true);
                $table->string('google_client_id', 225)->default('');
                $table->string('google_secret', 225)->default('');
                $table->string('facebook_client_id', 225)->default('');
                $table->string('facebook_secret', 225)->default('');
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
        Schema::dropIfExists('social_settings');
    }
}
