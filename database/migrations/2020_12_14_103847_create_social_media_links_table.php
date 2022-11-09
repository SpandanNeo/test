<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSocialMediaLinksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('social_media_links')){
            Schema::create('social_media_links', function (Blueprint $table) {
                $table->integer('social_media_link_id', true);
                $table->string('fb_link', 225)->default('');
                $table->string('twitter_link', 225)->default('');
                $table->string('google_link', 225)->default('');
                $table->string('youtube_link', 225)->default('');
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
        Schema::dropIfExists('social_media_links');
    }
}
