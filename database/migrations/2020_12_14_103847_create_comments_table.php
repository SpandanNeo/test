<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('comments')){
            Schema::create('comments', function (Blueprint $table) {
                $table->integer('comment_id', true);
                $table->integer('video_id')->default(0);
                $table->integer('user_id')->default(0);
                $table->dateTime('added_on')->nullable();
                $table->dateTime('updated_on')->nullable();
                $table->text('comment')->nullable();
                $table->tinyInteger('active')->default(1);
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
        Schema::dropIfExists('comments');
    }
}
