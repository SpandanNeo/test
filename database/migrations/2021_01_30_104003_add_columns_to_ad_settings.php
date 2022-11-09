<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToAdSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ad_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('ad_settings', 'disable_inter')) {
                $table->tinyInteger('disable_inter')->default(0);
            }
            if (!Schema::hasColumn('ad_settings', 'disable_banner')) {
                $table->tinyInteger('disable_banner')->default(0);
            }
            if (!Schema::hasColumn('ad_settings', 'disable_rewarded')) {
            $table->tinyInteger('disable_rewarded')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ad_settings', function (Blueprint $table) {
            //
        });
    }
}
