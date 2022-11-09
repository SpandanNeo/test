<?php

use Illuminate\Database\Seeder;

class AdSettingsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('ad_settings')->delete();
        
        \DB::table('ad_settings')->insert(array (
            0 => 
            array (
                'ad_setting_id' => 1,
                'android_app_id' => 'ca-app-pub-3940256099942544~3347511713',
                'android_banner_app_id' => 'ca-app-pub-3940256099942544/6300978111',
                'android_interstitial_app_id' => 'ca-app-pub-3940256099942544/1033173712',
                'android_video_app_id' => 'ca-app-pub-3940256099942544/5224354917',
                'is_banner' => 0,
                'is_interstitial' => 0,
                'is_video' => 0,
                'banner_show_on' => '',
                'interstitial_show_on' => '2',
                'video_show_on' => '3',
                'ios_app_id' => 'ca-app-pub-3940256099942544~1458002511',
                'ios_banner_app_id' => 'ca-app-pub-3940256099942544/2934735716',
                'ios_interstitial_app_id' => 'ca-app-pub-3940256099942544/4411468910',
                'ios_video_app_id' => 'ca-app-pub-3940256099942544/1712485313',
                'disable_inter' => 0,
                'disable_banner' => 0,
                'disable_rewarded' => 0,
            ),
        ));
        
        
    }
}