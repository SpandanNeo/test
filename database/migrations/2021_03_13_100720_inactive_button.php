<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InactiveButton extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('app_settings', 'inactive_button_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('inactive_button_color')->default('#000000');
            });
        }
        if (!Schema::hasColumn('app_settings', 'inactive_button_text_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('inactive_button_text_color')->default('#ffffff');
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
