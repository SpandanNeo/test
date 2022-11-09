<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHomeSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('home_settings')){
            Schema::create('home_settings', function (Blueprint $table) {
                $table->integer('home_setting_id', true);
                $table->string('heading', 200)->default('');
                $table->string('sub_heading', 200)->default('');
                $table->string('img1', 200)->default('');
                $table->string('img2', 200)->default('');
                $table->string('img1_link', 200)->default('');
                $table->string('img2_link', 200)->default('');
                $table->integer('comments_per_page')->default(8);
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
        Schema::dropIfExists('home_settings');
    }
}
