<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFollowTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('follow')){
            Schema::create('follow', function (Blueprint $table) {
                $table->integer('follow_id', true);
                $table->integer('follow_by')->default(0);
                $table->integer('follow_to')->default(0);
                $table->dateTime('follow_on')->nullable();
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
        Schema::dropIfExists('follow');
    }
}
