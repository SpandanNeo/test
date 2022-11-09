<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Driver extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('driver')){
            Schema::create('driver', function (Blueprint $table) {
                $table->integer('driver_id', true);
                $table->string('driver', 100)->default('');
                $table->tinyInteger('active')->default(0)->comment('1: yes, 0: no');
            });

             // Insert some stuff
            //  DB::table('driver')->insert([
            //     [
            //         'driver' => 'local',
            //         'active' => 1
            //     ],
            //     [
            //         'name' => 's3',
            //         'active' => 0
            //     ]
            // ]
            // );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('driver');
    }
}
