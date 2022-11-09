<?php

use Illuminate\Database\Seeder;

class AppSettingsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('app_settings')->delete();
        
        \DB::table('app_settings')->insert(array (
            0 => 
            array (
                'app_setting_id' => 1,
                'api_key' => 'testapikey',
                'api_user' => 'testapiuser',
                'bg_color' => '#000000',
                'accent_color' => '#383737',
                'button_color' => '#e83a63',
                'text_color' => '#ffffff',
                'button_text_color' => '#ffffff',
                'sender_msg_color' => '#786c6c',
                'sender_msg_text_color' => '#ffffff',
                'my_msg_color' => '#0d7291',
                'my_msg_text_color' => '#ffffff',
                'heading_color' => '#ff9800',
                'sub_heading_color' => '#ffffff',
                'icon_color' => '#ffffff',
                'dashboard_icon_color' => '#ffffff',
                'dashboard_icon_background_color' => '',
                'grid_item_border_color' => '#ffffff',
                'grid_border_radius' => '5',
                'divider_color' => '#ffffff',
                'dp_border_color' => '#ffffff',
                'video_time_limit' => '15,30,60',
                'inactive_button_color' => '#8c7777',
                'inactive_button_text_color' => '#ffffff',
                'header_bg_color' => '#000000',
                'bottom_nav' => '#ffffff',
                'bg_shade' => '#ffffff',
            ),
        ));
        
        
    }
}