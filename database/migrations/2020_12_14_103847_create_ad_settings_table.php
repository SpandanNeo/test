<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('ad_settings')){
            Schema::create('ad_settings', function (Blueprint $table) {
                $table->integer('ad_setting_id', true);
                $table->string('android_app_id', 300)->default('');
                $table->string('android_banner_app_id', 300)->default('');
                $table->string('android_interstitial_app_id', 300)->default('');
                $table->string('android_video_app_id', 300)->default('');
                $table->tinyInteger('is_banner')->default(0);
                $table->tinyInteger('is_interstitial')->default(0);
                $table->tinyInteger('is_video')->default(0);
                $table->string('banner_show_on', 10)->default('');
                $table->string('interstitial_show_on', 10)->default('');
                $table->string('video_show_on', 10)->default('');
                $table->string('ios_app_id', 300)->default('');
                $table->string('ios_banner_app_id', 300)->default('');
                $table->string('ios_interstitial_app_id', 300)->default('');
                $table->string('ios_video_app_id', 300)->default('');
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
        Schema::dropIfExists('ad_settings');
    }
}
