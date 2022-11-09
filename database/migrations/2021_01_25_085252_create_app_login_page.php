<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppLoginPage extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
    
        if (!Schema::hasTable('app_login_page')){
            Schema::create('app_login_page', function (Blueprint $table) {
                $table->integer('app_login_page_id', true);
                $table->string('logo')->default('');
                $table->string('title')->default('');
                $table->text('description')->nullable();
                $table->tinyInteger('fb_login')->default(0)->comment('0: no, 1 : yes');;
                $table->tinyInteger('google_login')->default(0)->comment('0: no, 1 : yes');;
                $table->text('privacy_policy')->nullable();
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
        Schema::dropIfExists('app_login_page');
    }
}
