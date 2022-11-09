<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('notifications')){
            Schema::create('notifications', function (Blueprint $table) {
                $table->increments('notify_id');
                $table->unsignedInteger('notify_by')->default(0);
                $table->unsignedInteger('notify_to')->default(0);
                $table->unsignedInteger('video_id')->default(0);
                $table->string('message')->nullable();
                $table->string('type', 50)->nullable()->comment('L:like, UL:unlike, F:follow, UF:unfollow, C:comment');
                $table->tinyInteger('read')->nullable()->default(0);
                $table->timestamp('added_on')->nullable();
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
        Schema::dropIfExists('notifications');
    }
}
