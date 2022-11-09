<?php

use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('settings')->delete();
        
        \DB::table('settings')->insert(array (
            0 => 
            array (
                'setting_id' => 1,
                'site_name' => 'Unify SoftTech',
                'site_address' => '<p>India</p>',
                'site_email' => 'support@unifysofttech.com',
                'site_phone' => '7878787878',
                'site_logo' => 'gyTvhcb3rxpUfFCG0JNmdtWkqFzzBjpGyGBTbFsy.png',
                'watermark' => 'Pe7IVI4jvQJ7rTvkwbvKyQ8D0JVrsi1KeKp1YSsz.png',
                'cur_version' => 'v3.5',
                'updated_at' => '2022-06-07 12:17:51',
                'migrated' => 0,
            ),
        ));
        
        
    }
}