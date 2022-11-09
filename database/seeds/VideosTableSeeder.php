<?php

use Illuminate\Database\Seeder;

class VideosTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('videos')->delete();
        
        \DB::table('videos')->insert(array (
            0 => 
            array (
                'video_id' => 42,
                'enabled' => 1,
                'user_id' => 5,
                'sound_id' => 3328,
                'title' => 'Demo Video',
                'description' => 'Demo Video Description',
                'master_video' => '1654605936/master.m3u8',
                'video' => '1654605936/2.mp4',
                'thumb' => '1654605936.jpg',
                'gif' => '1654605936.gif',
                'tags' => '',
                'location' => '',
                'duration' => 12,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:45:42',
                'updated_at' => '2022-06-07 12:45:46',
                'active' => 1,
                'flag' => 0,
                'privacy' => 0,
                'total_likes' => 0,
                'total_comments' => 0,
                'total_views' => 0,
                'total_report' => 0,
            ),
            1 => 
            array (
                'video_id' => 43,
                'enabled' => 1,
                'user_id' => 6,
                'sound_id' => 3329,
                'title' => 'Demo Video 1',
                'description' => 'Demo Video 1',
                'master_video' => '1654606038/master.m3u8',
                'video' => '1654606038/3.mp4',
                'thumb' => '1654606038.jpg',
                'gif' => '1654606038.gif',
                'tags' => '',
                'location' => '',
                'duration' => 8,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:47:22',
                'updated_at' => '2022-06-07 12:47:25',
                'active' => 1,
                'flag' => 0,
                'privacy' => 0,
                'total_likes' => 0,
                'total_comments' => 0,
                'total_views' => 0,
                'total_report' => 0,
            ),
            2 => 
            array (
                'video_id' => 44,
                'enabled' => 1,
                'user_id' => 7,
                'sound_id' => 3330,
                'title' => 'Demo Video 2',
                'description' => 'Demo Video 2',
                'master_video' => '1654606063/master.m3u8',
                'video' => '1654606063/4.mp4',
                'thumb' => '1654606063.jpg',
                'gif' => '1654606063.gif',
                'tags' => '',
                'location' => '',
                'duration' => 6,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:47:46',
                'updated_at' => '2022-06-07 12:47:48',
                'active' => 1,
                'flag' => 0,
                'privacy' => 0,
                'total_likes' => 0,
                'total_comments' => 0,
                'total_views' => 0,
                'total_report' => 0,
            ),
            3 => 
            array (
                'video_id' => 45,
                'enabled' => 1,
                'user_id' => 8,
                'sound_id' => 3331,
                'title' => 'Demo Video 3',
                'description' => 'Demo Video 3',
                'master_video' => '1654606089/master.m3u8',
                'video' => '1654606089/5.mp4',
                'thumb' => '1654606089.jpg',
                'gif' => '1654606089.gif',
                'tags' => '',
                'location' => '',
                'duration' => 6,
                'deleted' => 0,
                'created_at' => '2022-06-07 12:48:14',
                'updated_at' => '2022-06-07 12:48:16',
                'active' => 1,
                'flag' => 0,
                'privacy' => 0,
                'total_likes' => 0,
                'total_comments' => 0,
                'total_views' => 0,
                'total_report' => 0,
            ),
        ));
        
        
    }
}