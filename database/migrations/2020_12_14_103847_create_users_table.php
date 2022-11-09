<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('users')){
            Schema::create('users', function (Blueprint $table) {
                $table->integer('user_id', true);
                $table->string('username', 30)->default('');
                $table->string('fname', 20)->default('');
                $table->string('lname', 20)->default('');
                $table->string('email', 100)->default('');
                $table->string('mobile', 15)->default('');
                $table->char('gender', 1)->default('')->comment('m:Male, f:Female, ot:Others');
                $table->text('bio')->nullable();
                $table->string('user_dp', 300)->default('');
                $table->string('password', 100)->nullable()->default('');
                $table->date('dob')->nullable();
                $table->string('country', 40)->default('');
                $table->string('languages', 40)->default('');
                $table->string('app_token', 100)->default('');
                $table->char('login_type', 2)->comment('G: google, FB: Facebook, IN: Insta,  OT: OTP');
                $table->string('time_zone', 50)->default('');
                $table->string('player_id', 50)->default('');
                $table->string('ios_uuid', 200)->default('');
                $table->string('verification_code', 20)->nullable()->default('');
                $table->dateTime('verification_time')->nullable();
                $table->tinyInteger('active')->default(0)->comment('1: yes, 0: no');
                $table->tinyInteger('deleted')->default(0);
                $table->dateTime('last_active')->nullable();
                $table->dateTime('created_at');
                $table->dateTime('updated_at');
                $table->integer('email_verified')->default(0);
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
        Schema::dropIfExists('users');
    }
}
