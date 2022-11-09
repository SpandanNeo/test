<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Chats extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('chats')){
            Schema::create('chats', function (Blueprint $table) {
                $table->integer('id', true);
                $table->integer('from_id')->nullable()->default(0);
                $table->integer('to_id')->nullable()->default(0);
                $table->text('msg')->nullable()->collation('latin1_swedish_ci');
                $table->dateTime('sent_on');
                $table->tinyInteger('is_read')->default(0)->comment('0: unread, 1 : read');;
                $table->dateTime('read_on')->useCurrent();
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
        Schema::dropIfExists('chats');
    }
}
