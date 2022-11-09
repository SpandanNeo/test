<?php

use Illuminate\Database\Seeder;

class SoundsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('sounds')->delete();
        
        \DB::table('sounds')->insert(array (
            0 => 
            array (
                'sound_id' => 3324,
                'title' => 'I Love You',
                'sound_name' => '1654605716.mp3',
                'user_id' => 0,
                'cat_id' => '2',
                'parent_id' => 0,
                'duration' => 32,
                'album' => 'Bodyguard',
                'artist' => 'Salman',
                'tags' => NULL,
                'used_times' => 0,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:41:57',
                'active' => 1,
                'image' => 'OkIugGRLuVWMYsTjxDTmH3y62yE9TCOod6wqS6hi.jpg',
            ),
            1 => 
            array (
                'sound_id' => 3325,
                'title' => 'In The End',
                'sound_name' => '1654605773.mp3',
                'user_id' => 0,
                'cat_id' => '4',
                'parent_id' => 0,
                'duration' => 30,
                'album' => 'English',
                'artist' => 'English',
                'tags' => NULL,
                'used_times' => 0,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:42:53',
                'active' => 1,
                'image' => 'atR4DmJ6GgxrGgRbcM7eZxq8mjOeVmb3L3SkxtUh.jpg',
            ),
            2 => 
            array (
                'sound_id' => 3326,
                'title' => 'Upar Wale Ne Insaan Ke',
                'sound_name' => '1654605819.mp3',
                'user_id' => 0,
                'cat_id' => '5',
                'parent_id' => 0,
                'duration' => 17,
                'album' => 'Ab Tak Chappan',
                'artist' => 'Nana Patekar',
                'tags' => NULL,
                'used_times' => 0,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:43:40',
                'active' => 1,
                'image' => 'ZFHuF8a0FkLTAXunTkUOcV24sDBqUy9i9EzeAP92.jpg',
            ),
            3 => 
            array (
                'sound_id' => 3327,
                'title' => 'I Hate You Like I Love You',
                'sound_name' => '1654605903.mp3',
                'user_id' => 0,
                'cat_id' => '6',
                'parent_id' => 0,
                'duration' => 15,
                'album' => 'Delhi Belly',
                'artist' => 'Delhi',
                'tags' => NULL,
                'used_times' => 0,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:45:04',
                'active' => 1,
                'image' => 'Y95Uc4AfgcrmZZew0VoKvT1Z2rL40Vgn2PRnN68m.jpg',
            ),
            4 => 
            array (
                'sound_id' => 3328,
                'title' => '1654605936.mp3',
                'sound_name' => '1654605936.mp3',
                'user_id' => 5,
                'cat_id' => '0',
                'parent_id' => 0,
                'duration' => 12,
                'album' => '',
                'artist' => '',
                'tags' => NULL,
                'used_times' => 1,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:45:42',
                'active' => 1,
                'image' => '',
            ),
            5 => 
            array (
                'sound_id' => 3329,
                'title' => '1654606038.mp3',
                'sound_name' => '1654606038.mp3',
                'user_id' => 6,
                'cat_id' => '0',
                'parent_id' => 0,
                'duration' => 8,
                'album' => '',
                'artist' => '',
                'tags' => NULL,
                'used_times' => 1,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:47:22',
                'active' => 1,
                'image' => '',
            ),
            6 => 
            array (
                'sound_id' => 3330,
                'title' => '1654606063.mp3',
                'sound_name' => '1654606063.mp3',
                'user_id' => 7,
                'cat_id' => '0',
                'parent_id' => 0,
                'duration' => 6,
                'album' => '',
                'artist' => '',
                'tags' => NULL,
                'used_times' => 1,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:47:46',
                'active' => 1,
                'image' => '',
            ),
            7 => 
            array (
                'sound_id' => 3331,
                'title' => '1654606089.mp3',
                'sound_name' => '1654606089.mp3',
                'user_id' => 8,
                'cat_id' => '0',
                'parent_id' => 0,
                'duration' => 6,
                'album' => '',
                'artist' => '',
                'tags' => NULL,
                'used_times' => 1,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:48:14',
                'active' => 1,
                'image' => '',
            ),
        ));
        
        
    }
}