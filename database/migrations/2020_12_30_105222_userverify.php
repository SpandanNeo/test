<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Userverify extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('user_verify')){
        Schema::create('user_verify', function (Blueprint $table) {
            $table->integer('user_verify_id', true);
            $table->integer('user_id')->nullable()->default(0);
            $table->string('name', 200)->default('');
            $table->string('address', 250)->default('');
            $table->string('front_idproof', 200)->default('');
            $table->string('back_idproof', 200)->default('');
            $table->text('rejected_reason')->nullable();
            $table->char('verified', 1)->default('P')->comment('P: pending, A: accepted, R:rejected');
            $table->dateTime('added_on')->nullable();
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
        Schema::dropIfExists('user_verify');
    }
}
