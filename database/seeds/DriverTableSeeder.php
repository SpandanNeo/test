<?php

use Illuminate\Database\Seeder;

class DriverTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('driver')->delete();
        
        \DB::table('driver')->insert(array (
            0 => 
            array (
                'driver_id' => 1,
                'driver' => 'local',
                'active' => 1,
            ),
            1 => 
            array (
                'driver_id' => 2,
                'driver' => 's3',
                'active' => 0,
            ),
        ));
        
        
    }
}