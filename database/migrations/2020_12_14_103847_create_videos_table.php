<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('videos')){
            Schema::create('videos', function (Blueprint $table) {
                $table->integer('video_id', true);
                $table->integer('enabled')->default(0);
                $table->integer('user_id')->nullable()->default(0);
                $table->integer('sound_id')->default(0);
                $table->string('title', 200)->default('');
                $table->text('description')->nullable();
                $table->string('master_video', 200)->default('');
                $table->string('video', 250)->default('');
                $table->string('thumb', 200)->nullable();
                $table->string('gif', 200)->nullable();
                $table->text('tags')->nullable();
                $table->string('location', 200)->default('');
                $table->integer('duration')->default(0)->comment('in seconds');
                $table->tinyInteger('deleted')->default(0)->comment('1:yes,0:no');
                $table->dateTime('created_at');
                $table->dateTime('updated_at')->nullable();
                $table->tinyInteger('active')->default(1)->comment('1:yes,0:no');
                $table->tinyInteger('flag')->default(0)->comment('1:yes,0:no');
                $table->tinyInteger('privacy')->default(0)->comment('0:Public,1:Only Me,2:Only Followers');
                $table->integer('total_likes')->default(0);
                $table->integer('total_comments')->default(0);
                $table->integer('total_views')->default(0);
                $table->integer('total_report')->default(0);
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
        Schema::dropIfExists('videos');
    }
}
