<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNsfwSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('nsfw_settings')){
            Schema::create('nsfw_settings', function (Blueprint $table) {
                $table->integer('ns_id', true);
                $table->string('api_key', 100)->default('');
                $table->string('api_secret', 100)->nullable()->default('');
                $table->boolean('status')->default(0)->comment('1: yes, 0: no');
                $table->boolean('wad')->default(1)->comment('Weapons Alcohol Drugs Detection');
                $table->boolean('offensive')->default(1)->comment('Offensive');
                $table->boolean('nudity')->default(1)->comment('Nudity');
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
        Schema::dropIfExists('nsfw_settings');
    }
}
