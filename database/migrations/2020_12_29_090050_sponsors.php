<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Sponsors extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('sponsors')){
        Schema::create('sponsors', function (Blueprint $table) {
            $table->integer('sponsor_id', true);
            $table->string('heading', 100)->default('');
            $table->string('title', 200)->default('');
            $table->string('image', 200)->default('');
            $table->string('url', 200)->default('');
            $table->tinyInteger('active')->default(1)->comment('1: yes, 0: no');
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
        Schema::dropIfExists('sponsors');
    }
}
