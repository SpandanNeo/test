<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideoViewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('video_views')){
            Schema::create('video_views', function (Blueprint $table) {
                $table->integer('view_id', true);
                $table->integer('user_id')->default(0)->index('user_id');
                $table->integer('video_id')->default(0)->index('video_id');
                $table->dateTime('viewed_on')->nullable();
                $table->string('unique_id', 20)->default('')->index('unique_id');
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
        Schema::dropIfExists('video_views');
    }
}
