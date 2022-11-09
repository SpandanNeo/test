<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use FFMpeg\Format\Video\X264;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class ConvertVideoForStreaming implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $video;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $lowBitrateFormat = (new X264('aac', 'libx264'))->setKiloBitrate(800);
        // $mediumBitrateFormat = (new X264('aac', 'libx264'))->setKiloBitrate(1000);
        // $highBitrateFormat = (new X264('aac', 'libx264'))->setKiloBitrate(800);
 
        $converted_name = $this->getCleanFileName($this->video['c_path']);

        // open the uploaded video from the right disk...
        $res=FFMpeg::fromDisk($this->video['disk'])
            ->open($this->video['path'])            
            ->exportForHLS()
            ->setSegmentLength(3)
            ->setKeyFrameInterval(24)
            ->addFormat($lowBitrateFormat, function($media) {
               $media->scale(560,-2);
            })
            /*->addFormat($lowBitrateFormat, function($media) {
               $media->scale(480,-2);
            }) */           
            // ->addFormat($mediumBitrateFormat, function($media) {
            //     $media->scale(560,-2);
            //  })             
            // ->addFormat($highBitrateFormat, function($media) {
            //     $media->scale(560,-2);
            //  })
            
            ->toDisk('local')
            
            ->save("public/videos/".$this->video['user_id'].'/'.$converted_name);
            // FFMpeg::cleanupTemporaryFiles();
            //\Artisan::call('queue:work');
            $data =array(
                'master_video' => $converted_name,
                'updated_at' => date('Y-m-d H:i:s')
            );
            DB::table('videos')->where('video_id',$this->video['video_id'])->update($data);
    }

    private function getCleanFileName($filename){
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename) . '.m3u8';
    }
}
