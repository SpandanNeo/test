<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AppSettingColor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('app_settings', 'bg_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('bg_color')->default('#000000');
            });
        }
        if (!Schema::hasColumn('app_settings', 'accent_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('accent_color')->default('#FFC0CB');
            });
        }
        if (!Schema::hasColumn('app_settings', 'button_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('button_color')->default('#FFC0CB');
            });
        }
        if (!Schema::hasColumn('app_settings', 'text_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('text_color')->default('#ffffff');
            });
        }
        if (!Schema::hasColumn('app_settings', 'button_text_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('button_text_color')->default('#ffffff');
            });
        }

        if (!Schema::hasColumn('app_settings', 'sender_msg_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('sender_msg_color')->default('#D3D3D3');
            });
        }
        if (!Schema::hasColumn('app_settings', 'sender_msg_text_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('sender_msg_text_color')->default('#ffffff');
            });
        }
        if (!Schema::hasColumn('app_settings', 'my_msg_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('my_msg_color')->default('#a4dded');
            });
        }
        if (!Schema::hasColumn('app_settings', 'my_msg_text_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('my_msg_text_color')->default('#ffffff');
            });
        }
        if (!Schema::hasColumn('app_settings', 'heading_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('heading_color')->default('#e25822');
            });
        }
        if (!Schema::hasColumn('app_settings', 'sub_heading_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('sub_heading_color')->default('#ffffff');
            });
        }
        if (!Schema::hasColumn('app_settings', 'icon_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('icon_color')->default('#FFC0CB');
            });
        }
        if (!Schema::hasColumn('app_settings', 'dashboard_icon_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('dashboard_icon_color')->default('#ffffff');
            });
        }
        if (!Schema::hasColumn('app_settings', 'dashboard_icon_background_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('dashboard_icon_background_color')->default('#000000');
            });
        }
        if (!Schema::hasColumn('app_settings', 'grid_item_border_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('grid_item_border_color')->default('#B2BEB5');
            });
        }
        if (!Schema::hasColumn('app_settings', 'grid_border_radius')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('grid_border_radius')->default(10);
            });
        }
        if (!Schema::hasColumn('app_settings', 'divider_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('divider_color')->default('#B2BEB5');
            });
        }
        if (!Schema::hasColumn('app_settings', 'dp_border_color')) {
            Schema::table('app_settings', function (Blueprint $table) {
                $table->string('dp_border_color')->default('#ffffff');
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
