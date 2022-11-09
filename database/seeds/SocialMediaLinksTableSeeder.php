<?php

use Illuminate\Database\Seeder;

class SocialMediaLinksTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('social_media_links')->delete();
        
        \DB::table('social_media_links')->insert(array (
            0 => 
            array (
                'social_media_link_id' => 1,
                'fb_link' => 'https://www.facebook.com/',
                'twitter_link' => 'https://twitter.com/',
                'google_link' => 'https://google.com',
                'youtube_link' => 'https://www.youtube.com/',
            ),
        ));
        
        
    }
}