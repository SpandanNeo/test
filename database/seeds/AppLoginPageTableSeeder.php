<?php

use Illuminate\Database\Seeder;

class AppLoginPageTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('app_login_page')->delete();
        
        \DB::table('app_login_page')->insert(array (
            0 => 
            array (
                'app_login_page_id' => 1,
                'logo' => 'lwMPdx0YO8Ryon0QPia0CeGzgy2Aqu3Dql0rINlc.png',
                'title' => 'Sign Up For Leuke',
                'description' => '<p>Create New Account</p>',
                'fb_login' => 0,
                'google_login' => 0,
                'privacy_policy' => '<p>By Signing in your agree with Leuke Terms of Service use and Privacy Policy</p>',
                'apple_login' => 0,
                'background_img' => 'RmFyzWA7lzEg1rR51KQwvdhAGA8mG8jDrSeRDIOM.png',
            ),
        ));
        
        
    }
}