<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('reports')){
            Schema::create('reports', function (Blueprint $table) {
                $table->integer('report_id', true);
                $table->string('type', 100)->default('');
                $table->string('description', 500)->default('');
                $table->dateTime('report_on')->nullable();
                $table->integer('user_id')->default(0);
                $table->integer('video_id')->default(0);
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
        Schema::dropIfExists('reports');
    }
}
