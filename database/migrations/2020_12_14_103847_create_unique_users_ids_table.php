<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUniqueUsersIdsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('unique_users_ids')){
            Schema::create('unique_users_ids', function (Blueprint $table) {
                $table->integer('unique_id', true);
                $table->string('unique_token', 200)->nullable()->default('');
                $table->integer('user_id')->default(0);
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
        Schema::dropIfExists('unique_users_ids');
    }
}
