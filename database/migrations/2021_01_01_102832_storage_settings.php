<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class StorageSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('storage_settings')){
            Schema::create('storage_settings', function (Blueprint $table) {
                $table->integer('storage_setting_id', true);
                $table->integer('driver_id')->nullable()->default(0);
                $table->string('access_key_id', 100)->default('');
                $table->string('secret_access_key', 100)->default('');
                $table->string('region', 100)->default('');
                $table->string('bucket', 200)->default('');
                $table->string('url', 200)->default('');
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
        Schema::dropIfExists('storage_settings');
    }
}
