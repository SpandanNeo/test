<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePusherSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('pusher_settings')){
            Schema::create('pusher_settings', function (Blueprint $table) {
                $table->integer('pusher_setting_id', true);
                $table->string('pusher_app_id', 225)->default('');
                $table->string('pusher_app_key', 225)->default('');
                $table->string('pusher_app_secret', 225)->default('');
                $table->string('pusher_app_cluster', 225)->default('');
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
        Schema::dropIfExists('pusher_settings');
    }
}
