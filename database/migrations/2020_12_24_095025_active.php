<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Active extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('social_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('social_settings', 'google_active')) {
                $table->tinyInteger('google_active')->default(0)->comment('1: yes, 0: no');
            }
            if (!Schema::hasColumn('social_settings', 'fb_active')) {
                $table->tinyInteger('fb_active')->default(0)->comment('1: yes, 0: no');
            }
            //
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('social_settings', function (Blueprint $table) {
            //
        });
    }
}
