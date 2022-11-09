<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlockedUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('blocked_users')){
            Schema::create('blocked_users', function (Blueprint $table) {
                $table->integer('block_id', true);
                $table->integer('user_id')->default(0);
                $table->integer('blocked_by')->default(0);
                $table->dateTime('blocked_on')->nullable();
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
        Schema::dropIfExists('blocked_users');
    }
}
