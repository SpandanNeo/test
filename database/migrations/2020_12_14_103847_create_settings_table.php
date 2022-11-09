<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('settings')){
            Schema::create('settings', function (Blueprint $table) {
                $table->integer('setting_id', true);
                $table->string('site_name', 200)->default('');
                $table->text('site_address')->nullable();
                $table->string('site_email', 100)->default('');
                $table->string('site_phone', 15)->default('');
                $table->string('site_logo', 200)->default('');
                $table->string('watermark', 200)->default('');
                $table->string('cur_version', 200)->default('');
                $table->dateTime('updated_at')->nullable();
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
        Schema::dropIfExists('settings');
    }
}
