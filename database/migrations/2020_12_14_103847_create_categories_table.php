<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('categories')){
            Schema::create('categories', function (Blueprint $table) {
                $table->integer('cat_id', true);
                $table->string('cat_name', 225)->default('');
                $table->integer('rank')->nullable();
                $table->dateTime('added_on')->nullable();
                $table->integer('parent_id')->nullable()->default(0);
                $table->integer('deleted')->default(0);
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
        Schema::dropIfExists('categories');
    }
}
