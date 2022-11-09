<?php

use Illuminate\Database\Seeder;

class SponsorsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('sponsors')->delete();
        
        \DB::table('sponsors')->insert(array (
            0 => 
            array (
                'sponsor_id' => 2,
                'heading' => 'Unify SoftTech',
                'title' => 'Unify',
                'image' => '20220607122305_20211106070610_unify-logo.png',
                'url' => 'https://www.unifysofttech.com',
                'active' => 1,
            ),
        ));
        
        
    }
}