<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSoundsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('sounds')){
            Schema::create('sounds', function (Blueprint $table) {
                $table->integer('sound_id', true);
                $table->string('title', 200)->default('');
                $table->string('sound_name', 200)->default('');
                $table->integer('user_id')->default(0);
                $table->string('cat_id', 100)->default('');
                $table->integer('parent_id')->default(0);
                $table->integer('duration')->default(0)->comment('in seconds');
                $table->string('album', 200)->default('');
                $table->string('artist', 200)->default('');
                $table->text('tags')->nullable();
                $table->integer('used_times')->default(0);
                $table->tinyInteger('deleted')->default(0)->comment('1:yes, 0:no');
                $table->dateTime('created_at')->nullable();
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
        Schema::dropIfExists('sounds');
    }
}
