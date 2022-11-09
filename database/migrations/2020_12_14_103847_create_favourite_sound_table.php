<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFavouriteSoundTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('favourite_sound')){
            Schema::create('favourite_sound', function (Blueprint $table) {
                $table->integer('fav_id', true);
                $table->integer('sound_id')->default(0);
                $table->integer('user_id')->default(0);
                $table->dateTime('created_at')->nullable();
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
        Schema::dropIfExists('favourite_sound');
    }
}
