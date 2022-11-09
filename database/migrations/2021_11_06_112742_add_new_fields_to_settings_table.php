<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewFieldsToSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('app_settings', 'header_bg_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('header_bg_color')->default('#000000');
            });
        }
        if (!Schema::hasColumn('app_settings', 'bottom_nav')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('bottom_nav')->default('#ffffff');
            });
        }
        if (!Schema::hasColumn('app_settings', 'bg_shade')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('bg_shade')->default('#ffffff');
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
        //
    }
}
