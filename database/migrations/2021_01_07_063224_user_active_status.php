<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserActiveStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('users', 'active_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->tinyInteger('active_status')->default(0); // use this for field after specific column.
            });
        }
        if (!Schema::hasColumn('users', 'dark_mode')) {
            Schema::table('users', function (Blueprint $table) {
                $table->tinyInteger('dark_mode')->default(0); // use this for field after specific column.
            });
        }
        if (!Schema::hasColumn('users', 'messenger_color')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('messenger_color')->default('#2180f3'); // use this for field after specific column.
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
