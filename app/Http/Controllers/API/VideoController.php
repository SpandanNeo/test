<?php

namespace App\Http\Controllers\API;
use DateTime;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\Hash;
use App\Helpers\Common\Functions;
use FFMpeg\Format\Video\X264;
// use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
// use ProtoneMedia\LaravelFFMpeg\Support\ServiceProvider;
use App\Jobs\ConvertVideoForStreaming;
// use ProtoneMedia\LaravelFFMpeg\Filesystem\Media;
use Auth;
use Illuminate\Support\Facades\URL;
use FFMpeg\Filters\Video\VideoFilters; 
use FFMpeg as FFMpeg;
use FFProbe as FFProbe;
use App\GifCreator;
use FFMpeg\Coordinate\TimeCode;
use Intervention\Image\ImageManagerStatic as Image;
use Owenoj\LaravelGetId3\GetId3;
use \Sightengine\SightengineClient;
use GuzzleHttp\Exception\ClientException;
use App\Mail\SendMail;
use App\Notifications\UserNotification;
use App\Mail\SendMailWithAttachments;
use Mail;
use App\User;
use Illuminate\Http\File;

class VideoController extends Controller
{
    private $ffmpeg;
    private $ffprobe;

    public function __construct() {
        $this->ffmpeg= FFMpeg\FFMpeg::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));
        $this->ffprobe=  FFMpeg\FFProbe::create(array(
            'ffmpeg.binaries'  => config('app.ffmpeg'),
            'ffprobe.binaries' => config('app.ffprobe'),
            'timeout'          => 3600, // The timeout for the underlying process
            'ffmpeg.threads'   => 12, // The number of threads that FFMpeg should use
        ));
   
        // $this->middleware('auth:api');
    }
    private function _error_string($errArray)
    {
        $error_string = '';
        foreach ($errArray as $key) {
            $error_string.= $key."\n";
        }
        return $error_string;
    }


    public function index(Request $request){
        if(auth()->guard('api')->user()){
            $login_id=auth()->guard('api')->user()->user_id;
        }else{
            $login_id=0;
        }
 
        $messages = DB::table('chat_messages as m')
                    ->join('chat_chats as c','c.message_id','m.id')
                    ->join('chat_conversations as cc','cc.id','c.conversation_id')
                    ->where('c.type', 0)
                    ->where('read_at',null)
                    ->where('c.user_id', '!=', $login_id)
                    ->whereRaw(DB::raw("(cc.user_from=$login_id or cc.user_to=$login_id)"));
    

        $total = $messages->count();

        $videoStoragePath = asset(Storage::url('public/videos'));
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $soundImgPath = asset(Storage::url('public/sounds'));

        $page_size = isset($request->page_size) ? $request->page_size : 10;
        /*ifnull( case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',s.user_id,'/small/',u.user_dp) END,'".secure_asset('default/music-icon.png')."') as sound_image_url*/
        /*ifnull( case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',s.user_id,'/small/',u.user_dp) END,'".asset('default/music-icon.png')."') as sound_image_url*/
        /*'".asset('default/music-icon.png')."' as sound_image_url*/
            
        $videos = DB::table("videos as v")->select(DB::raw("v.video_id,v.wide as wide,v.sound_id,case when s.image !='' THEN concat('".$soundImgPath."','/images/',s.image) ELSE ifnull( case when INSTR(su.user_dp,'https://') > 0 THEN su.user_dp ELSE concat('".$userDpPath."/',su.user_id,'/small/',su.user_dp) END,'".asset('default/music-icon.png')."') END as sound_image_url,v.user_id,v.description,v.title,case when u.user_dp!='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',v.user_id,'/small/',u.user_dp) END ELSE '' END as user_dp, case when v.master_video = ''  then concat('".$videoStoragePath."/',v.user_id,'/',video) else concat('".$videoStoragePath."/',v.user_id,'/',master_video) end as video,ifnull(case when thumb='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',thumb) end,'') as thumb,ifnull(s.title,'') as sound_title,concat('@',u.username) as username,
            v.privacy,v.duration,v.tags,ifnull(v.created_at,'NA') as created_at,ifnull(v.updated_at,'NA') as updated_at,
            ifnull(l.like_id,0) as like_id,ifnull(f2.follow_id,0) as isFollowing, v.total_likes as total_likes,v.total_views as total_views, v.total_comments as total_comments,count(f3.follow_id) as total_followers, IF(uv.verified='A', true, false) as isVerified"))
        ->join("users as u","v.user_id","u.user_id")
        // ->leftJoin("user_verify as uv","uv.user_id","u.user_id")
        ->leftJoin('user_verify as uv', function ($join){
            $join->on('uv.user_id','=','u.user_id')
            ->where('uv.verified','A');
        })
        ->leftJoin("sounds as s","s.sound_id","v.sound_id")
        ->leftJoin("users as su","s.user_id","su.user_id")
        ->leftJoin('likes as l', function ($join)use ($request,$login_id){
            $join->on('l.video_id','=','v.video_id')
            ->where('l.user_id',$login_id);
        })
        ->leftJoin('follow as f3','f3.follow_to','u.user_id');
       /* $videos = DB::table("videos as v")->select(DB::raw("v.video_id,v.sound_id,'".asset('default/music-icon.png')."' as sound_image_url,v.user_id,v.description,v.title,case when u.user_dp!='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',v.user_id,'/small/',u.user_dp) END ELSE '' END as user_dp, concat('".$videoStoragePath."/',v.user_id,'/',video) as video,case when thumb='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',thumb) end as thumb,ifnull(case when thumb='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',thumb) end,'') as thumb,ifnull(s.title,'') as sound_title,concat('@',u.username) as username,
            v.privacy,v.duration,v.user_id,v.tags,ifnull(v.created_at,'NA') as created_at,ifnull(v.updated_at,'NA') as updated_at,
            ifnull(l.like_id,0) as like_id,ifnull(f2.follow_id,0) as isFollowing,ifnull(l.like_id,0) as like_id,ifnull(f2.follow_id,0) as isFollowing,
            (CASE WHEN v.total_likes >= 1000000000
            THEN concat(FORMAT(v.total_likes/1000000000,2),' ','B')
            WHEN v.total_likes >= 1000000
            THEN concat(FORMAT(v.total_likes/1000000,2),' ','M')
            WHEN v.total_likes >= 1000
            THEN concat(FORMAT(v.total_likes/1000,2),' ','K')
            ELSE
            v.total_likes
            END) as total_likes,
            (CASE WHEN v.total_v0iews >= 1000000000
            THEN concat(FORMAT(v.total_views/1000000000,2),' ','B')
            WHEN v.total_views >= 1000000
            THEN concat(FORMAT(v.total_views/1000000,2),' ','M')
            WHEN v.total_views >= 1000
            THEN concat(FORMAT(v.total_views/1000,2),' ','K')
            ELSE
            v.total_views
            END) as total_views,
            (CASE WHEN v.total_comments >= 1000000000
            THEN concat(FORMAT(v.total_comments/1000000000,2),' ','B')
            WHEN v.total_comments >= 1000000
            THEN concat(FORMAT(v.total_comments/1000000,2),' ','M')
            WHEN v.total_comments >= 1000
            THEN concat(FORMAT(v.total_comments/1000,2),' ','K')
            ELSE
            v.total_comments
            END) as total_comments,
            IF(uv.verified='A', true, false) as isVerified"))
        ->leftJoin("users as u","v.user_id","u.user_id")
        ->leftJoin("user_verify as uv","uv.user_id","u.user_id")
        ->leftJoin("sounds as s","s.sound_id","v.sound_id")
        ->leftJoin('likes as l', function ($join)use ($request){
            $join->on('l.video_id','=','v.video_id')
            ->where('l.user_id',$request->login_id);
        });*/
        if($request->user_id > 0  && $request->user_id == $login_id) {
            //$videos = $videos->whereRaw(DB::raw("v.privacy=1"));
            $videos = $videos->where("v.user_id","=", $request->user_id); 
        } else {
            $videos = $videos->where("v.privacy","<>", "1");    
        }

        $videos = $videos->where("v.deleted",0)
        ->where("v.enabled",1)
        ->where("v.active",1)
        ->where("v.flag",0)
        ->where("v.total_report","<",50);
        if($request->following == 1) {
            $videos = $videos->join('follow as f', function ($join)use ($request,$login_id){
                $join->on('f.follow_to','=','v.user_id')
                ->where('f.follow_by',$login_id);
            });
        }
        if(isset($request->hashtag) && $request->hashtag){
            $videos=$videos->whereRaw("FIND_IN_SET('$request->hashtag',v.tags)");
        }
        if(isset($request->search) && $request->search!=""){
            $search = $request->search;
            $videos = $videos->whereRaw(DB::raw("((v.title like '%" . $search . "%') or (v.tags like '%" . $search . "%'))"));
            //where('v.title', 'like', '%' . $search . '%')->orWhere('v.tags', 'like', '%' . $search . '%')->orWhere('v.tags', 'like', '%' . $search . '%');
        }
        if(isset($request->user_id) && $request->user_id>0) {
            $videos = $videos->where('v.user_id',$request->user_id);        
        }
        if($request->video_id>0){
            $videos = $videos->orderBy(DB::raw('v.video_id='.$request->video_id),'desc');
        }
        $is_following_videos = 0;
        if($login_id > 0 && $login_id!=$request->user_id) {
            $videos = $videos->leftJoin('blocked_users as bu1', function ($join)use ($request,$login_id){
                $join->on('v.user_id','=','bu1.user_id');
                // $join->whereRaw(DB::raw(" ( bu1.blocked_by=".$login_id." OR bu1.user_id=".$login_id." )" ));
                 $join->whereRaw(DB::raw(" ( bu1.blocked_by=".$login_id." )" ));
            });

            $videos = $videos->leftJoin('blocked_users as bu2', function ($join)use ($request,$login_id){
                $join->on('v.user_id','=','bu2.blocked_by');
                // $join->whereRaw(DB::raw(" ( bu2.blocked_by=".$login_id." OR bu2.user_id=".$login_id." )" ));
                  $join->whereRaw(DB::raw(" ( bu2.user_id=".$login_id." )" ));
            });
            $videos = $videos->whereRaw( DB::Raw(' bu1.block_id is null and bu2.block_id is null '));
            $videos = $videos->leftJoin('follow as f2', function ($join) use ($request,$login_id){
                $join->on('v.user_id','=','f2.follow_to')
                ->where('f2.follow_by',$login_id);
            });
            
            $videos = $videos->leftJoin('reports as rp', function ($join)use ($request,$login_id){
                $join->on('v.video_id','=','rp.video_id');
                $join->whereRaw(DB::raw(" ( rp.user_id=".$login_id." )" ));
            });
            $videos = $videos->whereRaw( DB::Raw(' rp.report_id is null '));

            if($request->user_id != $login_id) {
                $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f2.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END '));
            }

            // $login_id = $request->login_id;
            $followingVideos = DB::table("follow")
            ->select(DB::raw("follow_id"))
            ->where("follow_by",$login_id)
            ->first(); 
            if($followingVideos) {
                $is_following_videos = 1;
            }
        }  else {
            $videos = $videos->leftJoin('follow as f2', function ($join) use ($request,$login_id){
                $join->on('v.user_id','=','f2.follow_to')
                ->where('f2.follow_by',$login_id);
            });
            $videos = $videos->where("v.privacy","<>",2);        
        }
    
        $videos=$videos->groupBy(DB::raw("v.video_id,v.sound_id,sound_image_url,v.user_id,v.description,v.title,user_dp,sound_title,username,
            v.privacy,v.duration,v.user_id,v.tags,created_at,updated_at,
            like_id,isFollowing, v.total_likes,v.total_views, v.total_comments ,isVerified"));
        $videos = $videos->inRandomOrder("'".$request->random."'");
        //->orderBy("v.video_id","desc");
        // $videos = ($request->video_id == null || $request->video_id == 0) ? $videos->orderBy(DB::raw('RAND()')) : $videos->orderBy("v.video_id","desc");
        $videos= $videos->paginate($page_size);
        //dd(DB::getQueryLog());
        // $videos = preg_replace("/<.+>/sU", "", $videos);
        $response = array("status" => "success",'data' => $videos, 'is_following_videos' => $is_following_videos,'messagesCount'=>$total);
        return response()->json($response); 
    }
    public function uploadVideo(Request $request){
        // print_r($request->all());
        // exit;
        
        if(auth()->guard('api')->user()){
             $validator = Validator::make($request->all(), [ 
                 'description' => 'required', 
                'video'          => 'required|mimes:mp4,mov,ogg,qt',  
                'thumbnail_file' => 'required|image|mimes:jpeg,png,jpg,gif,svg',               
            ],[ 
                'description.required'   => 'Description is required',
                'video.required'   => 'Video is required',
                'thumbnail_file.required'   => 'Thumbnail File is required',
               
            ]);

            if (!$validator->passes()) {
                return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
            }else{
                    $storage_path=config('app.filesystem_driver');
            $sound_id=$request->sound_id;
            $time_folder=time();
            $user_id=auth()->guard('api')->user()->user_id;
            $videoPath = 'public/videos/'. $user_id;
            $audioPath = 'public/sounds/'. $user_id;
            // $videoFileName=$this->CleanFileNameMp4($request->file('video')->getClientOriginalName());
            // $request->video->storeAs($videoPath . '/' . $time_folder, $videoFileName);
            // Storage::setVisibility($videoPath . '/' . $time_folder.'/'.$videoFileName, 'public');
            Storage::disk('local')->makeDirectory('public/videos/'.$user_id.'/'.$time_folder);
            Storage::disk('local')->makeDirectory('public/videos/'.$user_id.'/thumb');
            Storage::disk('local')->makeDirectory('public/sounds/'.$user_id);
            $filenametostore = request()->file('video')->store($videoPath . '/' . $time_folder);  
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);  
            $videoFileName = array_pop($fileArray); 
            if($sound_id==0){
                $uploadStatus=Functions::ffmpegUploadApi(asset(Storage::url($videoPath . '/' .$time_folder.'/'. $videoFileName)),storage_path('app/public/videos/'.$user_id.'/'.$time_folder.'/'.$videoFileName),storage_path('app/public/sounds/'.$user_id.'/'.$time_folder.'.mp3'),$storage_path,$videoPath.'/'.$time_folder,$videoFileName,$audioPath,$time_folder.'.mp3');
                if($uploadStatus['status']=='error'){
                    return response()->json(["status" => "error", "msg"=>"A video without audio stream can not be uploaded."]);
                }else{
                    $streamCount = $this->ffprobe->streams(asset(Storage::url($videoPath . '/' .$time_folder.'/'. $videoFileName)))->audios()->count();
                   
                    if ($streamCount > 0) {
                        $duration = $this->ffprobe
                                ->streams(storage_path('app/public/sounds/'.$user_id.'/'.$time_folder.'.mp3' ))                 
                                ->audios()
                                ->first()                  
                                ->get('duration');
                        // dd($duration);
                        $audio_duration=round($duration);
    
                        $track = new GetId3(new File(storage_path('app/public/sounds/'.$user_id.'/'.$time_folder.'.mp3' )));
                        
                        $title=$track->getTitle();
                        $album=$track->getAlbum();
                        $artist=$track->getArtist();
                        if($storage_path=='s3'){
                            unlink(storage_path('app/public/sounds/'.$user_id.'/'.$time_folder.'.mp3' ));
                        }
    
                            $audioData = array(
                            'user_id' => $user_id,
                            'cat_id' => 0,
                            'title'     => ($title!=null) ? $title : "",
                            'album'     => ($album!=null) ? $album : "",
                            'artist'    => ($artist!=null) ? $artist : "",
                            'sound_name' => $time_folder.'.mp3',
                            // 'tags'     => $hashtags,
                            'duration' =>$audio_duration,
                            'used_times' =>1,
                            'created_at' => date('Y-m-d H:i:s')
                        ); 
    
                        $s_id=DB::table('sounds')->insertGetId($audioData);
                        $sound_id=$s_id;
                    }
                }

            }
            
            //thumb file upload
            $thumbPath = 'public/videos/'.$user_id.'/thumb';
            $thumbFilePath = $request->file('thumbnail_file')->store($thumbPath);            
            Storage::setVisibility($thumbFilePath, 'public');
            $thumbFileArray = explode('/',$thumbFilePath);  
            $thumbFileName = array_pop($thumbFileArray); 
            $thumbFileUrl = asset(config('app.video_path').$user_id."/thumb/".$thumbFileName);
            $v_path=asset(Storage::url($videoPath.'/'. $time_folder.'/'.$videoFileName));
            $video_duration = $this->ffprobe
                                    ->streams($v_path)
                                    ->videos()                   
                                    ->first()                  
                                    ->get('duration');
            $video_dimensions =  $this->ffprobe
            ->streams($v_path)   // extracts streams informations
            ->videos()                      // filters video streams
            ->first()                       // returns the first video stream
            ->getDimensions();              // returns a FFMpeg\Coordinate\Dimension object
            $width = $video_dimensions->getWidth();
            $height = $video_dimensions->getHeight();
            $wide = $height > $width ? 0 :1;
              //video moderation
              $nsfw = DB::table("nsfw_settings")->where("ns_id",1)->first();
              $nudity = array();
              if($nsfw){
                  if($nsfw->status==1){
                      $nsfw_filters = [];
                      if($nsfw->nudity==1){
                          $nsfw_filters[] = 'nudity';
                      }
                      if($nsfw->wad==1){
                          $nsfw_filters[] = 'wad';
                      }
                      if($nsfw->offensive==1){
                          $nsfw_filters[] = 'offensive';
                      }
                      if($nsfw->api_key!='' && $nsfw->api_secret!=''){
                          $client = new SightengineClient($nsfw->api_key, $nsfw->api_secret);

                    
                          $pic_frames = array();
                          $secds = 0;
                          $images = [];
                          do{

                              $pic_frames[] = $secds;
                              $secds = $secds+3;

                          }while($secds<$video_duration);
                          // dd($pic_frames);
                          foreach ($pic_frames as $key => $seconds) {
                            $video = $this->ffmpeg->open($v_path);
                            $video
                                ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                                ->save(storage_path('app/'.$videoPath.'/'."thumb_{$key}.jpg"));
                            //   $mediaOpener = $mediaOpener->getFrameFromSeconds($seconds)
                            //   ->export()
                            //   ->save('public/videos/'.$this->authUser->user_id.'/'."thumb_{$key}.jpg");
                            //   $imgName = storage_path('app/'.$videoPath.'/'."thumb_{$key}.jpg");
                            $imgName = asset(Storage::disk('local')->url($videoPath.'/'."thumb_{$key}.jpg")); 
                              $images[] = 'public/videos/'.$user_id.'/'."thumb_{$key}.jpg";
                              try{
                              
                                  $output = $client->check($nsfw_filters)->set_url($imgName);
                              // dd($output);
                                  if($output->status=="success"){
                                      if(in_array('wad',$nsfw_filters)){
                                          if($output->weapon > 0.50){
                                              $nudity[] = $imgName;
                                              break;
                                          }elseif($output->alcohol > 0.50){
                                              $nudity[] = $imgName;
                                              break;
                                          }elseif($output->drugs > 0.50){
                                              $nudity[] = $imgName;
                                              break;
                                          }
                                      }
                                      if(in_array('nudity',$nsfw_filters)){
                                          if(isset($output->nudity)){
                                              $raw_nudity = $output->nudity;
                                              if($raw_nudity->raw> 0.50){
                                                  $nudity[] = $imgName;
                                                  break;
                                              }
                                          }
                                      }

                                      if(in_array('offensive',$nsfw_filters)){
                                          if(isset($output->offensive)){
                                              $offensive = $output->offensive;
                                              if($offensive->prob> 0.50){
                                                  $nudity[] = $imgName;
                                                  break;
                                              }
                                          }
                                      }
                                  }else{
                                      echo "fail";
                                  }
                              }
                              catch(ClientException $e) {
                                  $msg = $e->getResponse()->getReasonPhrase();
                                  $mail_settings = DB::table("mail_settings")->where("m_id",1)->first();
                                  if($mail_settings){
                                      $company_settings = DB::table("settings")->where("setting_id",1)->first();
                                      $admin_email = $company_settings->site_email;
                                      $site_name = $company_settings->site_name;
                                      $from_email = $mail_settings->from_email;
                                      $mailBody = '
                                      <b style="font-size:16px;color:#333333;margin:0;padding-bottom:10px;text-transform:capitalize">
                                      Video Moderation API warning
                                      </b>
                                      <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">
                                      '.$msg.'
                                      </p>
                                      <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you</p>
                                      <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">'.$site_name.'</p>
                                      ';
                                          // dd($mailBody);
                                          // $ref_id
                                      $from_email = config("app.from_mail");
                                      $array = array('subject'=>$site_name.' - Video Moderation API warning','view'=>'emails.site.company_panel','body' => $mailBody);
                                      if(filter_var($from_email, FILTER_VALIDATE_EMAIL)){
                                          $array['from'] = $from_email;
                                      }
                                      // dd(strpos($_SERVER['SERVER_NAME'], "local"));
                                      if(strpos($_SERVER['SERVER_NAME'], "localhost")===false && strpos($_SERVER['SERVER_NAME'], "leukewebpanel.local") ===false){
                                          Mail::to($admin_email)->send(new SendMail($array));  
                                      }
                                  }
                                  break;
                              }
                          }
                          foreach($images as $val) {
                              Storage::disk('local')->delete($val);
                              // unlink($val);
                          }
                      }

                  }

              }

            $data = [
                'enabled' => 1,
                'user_id' => $user_id,
                'sound_id' => $sound_id,
                'duration' => $video_duration,
                //'title' => implode(' ', explode(' ', $description, 3)),
                'description' => $request->description,
                'video' => $time_folder . '/' . $videoFileName,
                'thumb' => $thumbFileName,
                'created_at' => date('Y-m-d h:i:s'),
                'privacy' =>$request->privacy,
                'wide' =>$wide
            ];
            $flagged=0;
            if(count($nudity)>0){
              
                // $response = array("status" => "failed","msg"=>"Video is flagged by our system and its under moderation.");
                $flagged = 1;
                $data['flag']=1;
            }
            
            $hashtags='';
            if(isset($request->description)) {
                if(stripos($request->description,'#')!==false) {
                    $str = $request->description;
                    preg_match_all('/#([^\s]+)/', $str, $matches);
                    $hashtags = implode(',', $matches[1]);
                }
            }

            if($hashtags!='') {
                $data['tags'] = $hashtags;
                // dd($hashtags);
            }
            $file_path= $videoPath.'/'. $time_folder.'/'.$videoFileName;
            $c_path=  $this->getCleanFileName($time_folder.'/master.m3u8');
            $v_id=DB::table('videos')
                ->insertGetId($data);
                
                $video = array(
                'disk'          => $storage_path,
                'original_name' => $request->video->getClientOriginalName(),
                'path'          => $file_path,
                'c_path'        => $c_path,
                'title'         => $request->title,
                'video_id'      => $v_id,
                'user_id'       => $user_id
            );

            ConvertVideoForStreaming::dispatch($video);
                // notification
                
                
			     $users = DB::table("users as u")->select(DB::raw("GROUP_CONCAT(u.user_id) as user_ids"))
					->leftJoin('follow as f', function ($join) use ($request){
						$join->on('u.user_id','=','f.follow_to');
						// ->where('f.follow_by',$request->login_id);
					})
					->leftJoin('follow as f2', function ($join) use ($request,$user_id){
						$join->on('u.user_id','=','f2.follow_to')
						->where('f2.follow_by',$user_id);
					});
					if($user_id > 0) {
						$users = $users->leftJoin('blocked_users as bu', function ($join)use ($request,$user_id){
							$join->on('u.user_id','=','bu.user_id');
							$join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
						});
	
						$users = $users->leftJoin('blocked_users as bu2', function ($join)use ($request,$user_id){
							$join->on('u.user_id','=','bu2.blocked_by');
							$join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
						});
	
						$users = $users->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
					}
					$users=$users->where('f.follow_to','<>', $user_id);
					$users=$users->where('f.follow_by', $user_id)
					->where("u.deleted",0)
					->where("u.active",1);
				
				
				$users = $users->orderBy('u.user_id','desc');
				$users= $users->first();
                
                $title= auth()->guard('api')->user()->fname.' '.auth()->guard('api')->user()->lname;
                $body = 'Upload new video '.$request->description;
                $img=$thumbFileUrl;
                if ($users) {
        			$user_ids = explode(',', $users->user_ids);
        			// dd($user_ids);
        			$firebaseToken = User::where('fcm_token', '<>', '')->whereIn('user_id', $user_ids)->pluck('fcm_token')->all();
        		}
                $SERVER_API_KEY = config('app.server_api_key');
                
                $json_data = [
            			"registration_ids" => $firebaseToken,
            			"notification" => [
            				"body" => $body,
            				"title" => $title,
            				"icon" => $img
            			],
            			"data" => [
            				"title" => $title,
            				"body" => $body,
            				"id" => $v_id,
            				"type" => 'video',
            				"image" => $img,
            				'msg' => 'masssss',
            				"name" => 'nammmeees'
            			],
            			"click_action" => 'FLUTTER_NOTIFICATION_CLICK'
            		];
            		$data = json_encode($json_data);
            		// dd($data);
            		//FCM API end-point
            		$url = 'https://fcm.googleapis.com/fcm/send';
            		//api_key in Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key
            		// $server_key = 'YOUR_KEY';
            		//header with content_type api key
            		$headers = array(
            			'Content-Type:application/json',
            			'Authorization:key=' . $SERVER_API_KEY
            		);
            		//CURL request to route notification to FCM connection server (provided by Google)
            		$ch = curl_init();
            		curl_setopt($ch, CURLOPT_URL, $url);
            		curl_setopt($ch, CURLOPT_POST, true);
            		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            		$result = curl_exec($ch);
            		// 			dd($result);
            		if ($result === FALSE) {
            			die('Oops! FCM Send Error: ' . curl_error($ch));
            		}
            
            	//	return $result;
                
                //end notification
                
                
            $thumb=asset(Storage::url($videoPath.'/thumb/'.$thumbFileName));
            if($flagged==0){
                    return response()->json(["status" => "success",'msg'=>'Video uploaded successfully', "video" => $v_path,"thumb" => $thumb]);
                    // $response = array("status" => "success",'msg'=>'Video uploaded successfully' , 'video' => $videoFileUrl,'thumb' => $thumbFileName);   
                   }else{
                        return response()->json(["status" => "error", "msg"=>"Video is flagged by our system and its under moderation."]);
                   }
            }
        }else{
            return response()->json([
                "status" => "error", "msg" => "Unauthorized user!"
            ]);
        }
    }
    public function uploadVideo3(Request $request){
        // print_r($request->all());
        // exit;
        $validator = Validator::make($request->all(), [ 
            'user_id'          => 'required',              
            'app_token'          => 'required', 
            'description' => 'required', 
            'video'          => 'required|mimes:mp4,mov,ogg,qt',  
            'thumbnail_file' => 'required|image|mimes:jpeg,png,jpg,gif,svg', 
            // 'gif_file' => 'required|image|mimes:gif',
        ],[ 
            'user_id.required'   => 'User Id  is required.',
            'app_token.required'   => 'App Token is required.',
            'description.required'   => 'Description is required',
            'video.required'   => 'Video is required',
            'thumbnail_file.required'   => 'Thumbnail File is required',
            'sound_id.required'   => 'Sound is required',
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            $functions = new Functions();
            $token_res= $functions->validate_token($request->user_id,$request->app_token);

            // $ffmpeg = FFMpeg\FFMpeg::create();
            // $ffprobe = FFMpeg\FFProbe::create();

            if($token_res>0){
                //video file upload
                $videoPath = 'public/videos/'.$request->user_id;
                $videoFilePath = $request->file('video')->store($videoPath);            
                Storage::setVisibility($videoFilePath, 'public');
                $time_folder=time();
                
                $videoFileArray = explode('/',$videoFilePath);  
                $videoFileName = array_pop($videoFileArray); 
                $videoFileUrl = asset(config('app.video_path').$request->user_id."/".$videoFileName);
                if($request->sound_id==0){
                    $streamStatus=Functions::ffmpegCheckVideoStream( asset(Storage::url($videoPath .'/'. $videoFileName)));
                    if ($streamStatus > 0) {
                        
                        $audio = $this->ffmpeg->open(asset(Storage::url($videoPath .'/'. $videoFileName)));
                        $audio_format = new FFMpeg\Format\Audio\Mp3();
            
                        $audio->save($audio_format, storage_path('app/public/sounds/'.$request->user_id.'/'.$time_folder.'.mp3'));

                        $duration = $this->ffprobe
                                    ->streams(asset(Storage::url('public/sounds/'.$request->user_id.'/'.$time_folder.'.mp3')))                 
                                    ->audios()
                                    ->first()                  
                                    ->get('duration');
                           
                            $audio_duration=round($duration);

                            $track = new GetId3(new File(storage_path('app/public/sounds/'.$request->user_id.'/'.$time_folder.'.mp3' )));
                          
                            $title=$track->getTitle();
                            $album=$track->getAlbum();
                            $artist=$track->getArtist();


                             $audioData = array(
                                'user_id' => $request->user_id,
                                'cat_id' => 0,
                                'title'     => ($title!=null) ? $title : "",
                                'album'     => ($album!=null) ? $album : "",
                                'artist'    => ($artist!=null) ? $artist : "",
                                'sound_name' => $time_folder.'.mp3',
                                // 'tags'     => $hashtags,
                                'duration' =>$audio_duration,
                                'used_times' =>1,
                                'created_at' => date('Y-m-d H:i:s')
                            ); 

                            $s_id=DB::table('sounds')->insertGetId($audioData);
                            $sound_id=$s_id;

                    }else{
                        $response = array("status" => "failed","msg"=>"A video without audio stream can not be uploaded.");
                        return $response;
                    }
                }else{
                    $sound_id=$request->sound_id;
                }
                //thumb file upload
                $thumbPath = 'public/videos/'.$request->user_id.'/thumb';
                $thumbFilePath = $request->file('thumbnail_file')->store($thumbPath);            
                Storage::setVisibility($thumbFilePath, 'public');
                $thumbFileArray = explode('/',$thumbFilePath);  
                $thumbFileName = array_pop($thumbFileArray); 
                $thumbFileUrl = asset(config('app.video_path').$request->user_id."/thumb/".$thumbFileName);

                //gif file upload
                // $gifPath = 'public/videos/'.$request->user_id.'/gif';
                // $gifFilePath = $request->file('gif_file')->store($gifPath);            
                // Storage::setVisibility($gifFilePath, 'public');
                // $gifFileArray = explode('/',$gifFilePath);  
                // $gifFileName = array_pop($gifFileArray); 
                // $gifFileUrl = secure_asset(config('app.video_path').$request->user_id."/gif/".$gifFileName);
                $hashtags='';
                if(isset($request->description)) {
                    if(stripos($request->description,'#')!==false) {
                        $str = $request->description;
                        preg_match_all('/#([^\s]+)/', $str, $matches);
                        $hashtags = implode(',', $matches[1]);
                    }
                }

                if($hashtags!='') {
                    $data['tags'] = $hashtags;
                }
                $nsfw = DB::table("nsfw_settings")->where("ns_id",1)->first();
                $nudity = array();
                if($nsfw){
                    if($nsfw->status==1){
                        $nsfw_filters = [];
                        if($nsfw->nudity==1){
                            $nsfw_filters[] = 'nudity';
                        }
                        if($nsfw->wad==1){
                            $nsfw_filters[] = 'wad';
                        }
                        if($nsfw->offensive==1){
                            $nsfw_filters[] = 'offensive';
                        }
                        if($nsfw->api_key!='' && $nsfw->api_secret!=''){
                            $client = new SightengineClient($nsfw->api_key, $nsfw->api_secret);
                            $v_path=asset(Storage::url($videoPath.'/'.$videoFileName));
                            // $ffprobe = FFMpeg\FFProbe::create();
                            $video_duration = $this->ffprobe
                                    ->streams($v_path)
                                    ->videos()                   
                                    ->first()                  
                                    ->get('duration');

                            // $mediaOpener = FFMpeg::open('public/videos/'.$request->user_id.'/'.$videoFileName);
                            // $video_duration = $mediaOpener->getDurationInSeconds();
                            $pic_frames = array();
                            $secds = 0;
                            $images = [];
                            do{

                                $pic_frames[] = $secds;
                                $secds = $secds+3;

                            }while($secds<$video_duration);
                            // dd($pic_frames);
                            foreach ($pic_frames as $key => $seconds) {
                                $video = $this->ffmpeg->open($v_path);
                                $video
                                    ->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(0))
                                    ->save(storage_path('app/'.$videoPath.'/'."thumb_{$key}.jpg"));
                                // $mediaOpener = $mediaOpener->getFrameFromSeconds($seconds)
                                // ->export()
                                // ->save('public/videos/'.$request->user_id.'/'."thumb_{$key}.jpg");
                                $imgName = secure_url('storage/videos/'.$request->user_id.'/'. "thumb_{$key}.jpg");
                                $images[] = storage_path('app/public/videos/'.$request->user_id.'/'."thumb_{$key}.jpg");
                                try{
                                    $output = $client->check($nsfw_filters)->set_url($imgName);
                                    if($output->status=="success"){
                                        if(in_array('wad',$nsfw_filters)){
                                            if($output->weapon > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }elseif($output->alcohol > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }elseif($output->drugs > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }
                                        }
                                        if(in_array('nudity',$nsfw_filters)){
                                            if(isset($output->nudity)){
                                                $raw_nudity = $output->nudity;
                                                if($raw_nudity->raw> 0.50){
                                                    $nudity[] = $imgName;
                                                    break;
                                                }
                                            }
                                        }

                                        if(in_array('offensive',$nsfw_filters)){
                                            if(isset($output->offensive)){
                                                $offensive = $output->offensive;
                                                if($offensive->prob> 0.50){
                                                    $nudity[] = $imgName;
                                                    break;
                                                }
                                            }
                                        }
                                    }else{
                                        echo "fail";
                                    }
                                }
                                catch(ClientException $e) {
                                    $msg = $e->getResponse()->getReasonPhrase();
                                    $mail_settings = DB::table("mail_settings")->where("m_id",1)->first();
                                    if($mail_settings){
                                        $company_settings = DB::table("settings")->where("setting_id",1)->first();
                                        $admin_email = $company_settings->site_email;
                                        $site_name = $company_settings->site_name;
                                        $from_email = $mail_settings->from_email;
                                        $mailBody = '
                                        <b style="font-size:16px;color:#333333;margin:0;padding-bottom:10px;text-transform:capitalize">
                                        Video Moderation API warning
                                        </b>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">
                                        '.$msg.'
                                        </p>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you</p>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">'.$site_name.'</p>
                                        ';
                                            // dd($mailBody);
                                            // $ref_id
                                        $from_email = config("app.from_mail");
                                        $array = array('subject'=>$site_name.' - Video Moderation API warning','view'=>'emails.site.company_panel','body' => $mailBody);
                                        if(filter_var($from_email, FILTER_VALIDATE_EMAIL)){
                                            $array['from'] = $from_email;
                                        }
                                        if(strpos($_SERVER['SERVER_NAME'], "localhost")===false && strpos($_SERVER['SERVER_NAME'], "leukewebpanel.local")===false){
                                            Mail::to($admin_email)->send(new SendMail($array));  
                                        }
                                    }
                                    break;
                                }
                            }
                            foreach($images as $val) {
                                unlink($val);
                            }
                        }

                    }

                }
                $data['user_id'] = $request->user_id;
                $data['video'] = $videoFileName;
                $data['thumb'] = $thumbFileName;
                // $data['gif'] = $gifFileName;
                $data['gif'] = "";
                $data['description'] = strip_tags($request->description);
                $data['duration'] = 15;
                $data['sound_id'] = $sound_id;
                $data['enabled'] = 1;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                 $flagged=0;
                if(count($nudity)>0){
                    $response = array("status" => "failed","msg"=>"Video is flagged by our system and its under moderation.");
                    $flagged = 1;
                    $data['flag']=1;
                }
                $video_id = DB::table('videos')->insertGetId($data);
                if($flagged==0){
                 $response = array("status" => "success",'msg'=>'Video uploaded successfully' , 'video' => $videoFileUrl,'thumb' => $thumbFileName);   
                }

                return response()->json($response);

            }else{
                return response()->json([
                    "status" => "error", "msg" => "Unauthorized user!"
                ]);
            }
        }
    }

    public function uploadVideo_old(Request $request){
        // print_r($request->all());
        // exit;
        $validator = Validator::make($request->all(), [ 
            'user_id'          => 'required',              
            'app_token'          => 'required', 
            'description' => 'required', 
            'video'          => 'required|mimes:mp4,mov,ogg,qt',  
            'thumbnail_file' => 'required|image|mimes:jpeg,png,jpg,gif,svg', 
            // 'gif_file' => 'required|image|mimes:gif',
        ],[ 
            'user_id.required'   => 'User Id  is required.',
            'app_token.required'   => 'App Token is required.',
            'description.required'   => 'Description is required',
            'video.required'   => 'Video is required',
            'thumbnail_file.required'   => 'Thumbnail File is required',
            // 'gif_file.required'   => 'Gif File is required',
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            $functions = new Functions();
            $token_res= $functions->validate_token($request->user_id,$request->app_token);
            if($token_res>0){
                //video file upload
                $videoPath = 'public/videos/'.$request->user_id;
                $videoFilePath = $request->file('video')->store($videoPath);            
                Storage::setVisibility($videoFilePath, 'public');
                $videoFileArray = explode('/',$videoFilePath);  
                $videoFileName = array_pop($videoFileArray); 
                $videoFileUrl = secure_asset(config('app.video_path').$request->user_id."/".$videoFileName);

                //thumb file upload
                $thumbPath = 'public/videos/'.$request->user_id.'/thumb';
                $thumbFilePath = $request->file('thumbnail_file')->store($thumbPath);            
                Storage::setVisibility($thumbFilePath, 'public');
                $thumbFileArray = explode('/',$thumbFilePath);  
                $thumbFileName = array_pop($thumbFileArray); 
                $thumbFileUrl = secure_asset(config('app.video_path').$request->user_id."/thumb/".$thumbFileName);

                //gif file upload
                // $gifPath = 'public/videos/'.$request->user_id.'/gif';
                // $gifFilePath = $request->file('gif_file')->store($gifPath);            
                // Storage::setVisibility($gifFilePath, 'public');
                // $gifFileArray = explode('/',$gifFilePath);  
                // $gifFileName = array_pop($gifFileArray); 
                // $gifFileUrl = secure_asset(config('app.video_path').$request->user_id."/gif/".$gifFileName);
                $hashtags='';
                if(isset($request->description)) {
                    if(stripos($request->description,'#')!==false) {
                        $str = $request->description;
                        preg_match_all('/#([^\s]+)/', $str, $matches);
                        $hashtags = implode(',', $matches[1]);
                    }
                }

                if($hashtags!='') {
                    $data['tags'] = $hashtags;
                }
                $nsfw = DB::table("nsfw_settings")->where("ns_id",1)->first();
                $nudity = array();
                if($nsfw){
                    if($nsfw->status==1){
                        $nsfw_filters = [];
                        if($nsfw->nudity==1){
                            $nsfw_filters[] = 'nudity';
                        }
                        if($nsfw->wad==1){
                            $nsfw_filters[] = 'wad';
                        }
                        if($nsfw->offensive==1){
                            $nsfw_filters[] = 'offensive';
                        }
                        if($nsfw->api_key!='' && $nsfw->api_secret!=''){
                            $client = new SightengineClient($nsfw->api_key, $nsfw->api_secret);
                            $mediaOpener = FFMpeg::open('public/videos/'.$request->user_id.'/'.$videoFileName);
                            $video_duration = $mediaOpener->getDurationInSeconds();
                            $pic_frames = array();
                            $secds = 0;
                            $images = [];
                            do{

                                $pic_frames[] = $secds;
                                $secds = $secds+3;

                            }while($secds<$video_duration);
                            // dd($pic_frames);
                            foreach ($pic_frames as $key => $seconds) {
                                $mediaOpener = $mediaOpener->getFrameFromSeconds($seconds)
                                ->export()
                                ->save('public/videos/'.$request->user_id.'/'."thumb_{$key}.jpg");
                                $imgName = secure_url('storage/videos/'.$request->user_id.'/'. "thumb_{$key}.jpg");
                                $images[] = storage_path('app/public/videos/'.$request->user_id.'/'."thumb_{$key}.jpg");
                                try{
                                    $output = $client->check($nsfw_filters)->set_url($imgName);
                                    if($output->status=="success"){
                                        if(in_array('wad',$nsfw_filters)){
                                            if($output->weapon > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }elseif($output->alcohol > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }elseif($output->drugs > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }
                                        }
                                        if(in_array('nudity',$nsfw_filters)){
                                            if(isset($output->nudity)){
                                                $raw_nudity = $output->nudity;
                                                if($raw_nudity->raw> 0.50){
                                                    $nudity[] = $imgName;
                                                    break;
                                                }
                                            }
                                        }

                                        if(in_array('offensive',$nsfw_filters)){
                                            if(isset($output->offensive)){
                                                $offensive = $output->offensive;
                                                if($offensive->prob> 0.50){
                                                    $nudity[] = $imgName;
                                                    break;
                                                }
                                            }
                                        }
                                    }else{
                                        echo "fail";
                                    }
                                }
                                catch(ClientException $e) {
                                    $msg = $e->getResponse()->getReasonPhrase();
                                    $mail_settings = DB::table("mail_settings")->where("m_id",1)->first();
                                    if($mail_settings){
                                        $company_settings = DB::table("settings")->where("setting_id",1)->first();
                                        $admin_email = $company_settings->site_email;
                                        $site_name = $company_settings->site_name;
                                        $from_email = $mail_settings->from_email;
                                        $mailBody = '
                                        <b style="font-size:16px;color:#333333;margin:0;padding-bottom:10px;text-transform:capitalize">
                                        Video Moderation API warning
                                        </b>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">
                                        '.$msg.'
                                        </p>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you</p>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">'.$site_name.'</p>
                                        ';
                                            // dd($mailBody);
                                            // $ref_id
                                        $from_email = config("app.from_mail");
                                        $array = array('subject'=>$site_name.' - Video Moderation API warning','view'=>'emails.site.company_panel','body' => $mailBody);
                                        if(filter_var($from_email, FILTER_VALIDATE_EMAIL)){
                                            $array['from'] = $from_email;
                                        }
                                        if(strpos($_SERVER['SERVER_NAME'], "localhost")===false){
                                            Mail::to($admin_email)->send(new SendMail($array));  
                                        }
                                    }
                                    break;
                                }
                            }
                            foreach($images as $val) {
                                unlink($val);
                            }
                        }

                    }

                }
                $data['user_id'] = $request->user_id;
                $data['video'] = $videoFileName;
                $data['thumb'] = $thumbFileName;
                // $data['gif'] = $gifFileName;
                $data['gif'] = "";
                $data['description'] = strip_tags($request->description);
                $data['duration'] = 15;
                $data['sound_id'] = $request->sound_id;
                $data['enabled'] = 1;
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                 $flagged=0;
                if(count($nudity)>0){
                    $response = array("status" => "failed","msg"=>"Video is flagged by our system and its under moderation.");
                    $flagged = 1;
                    $data['flag']=1;
                }
                $video_id = DB::table('videos')->insertGetId($data);
                if($flagged==0){
                 $response = array("status" => "success",'msg'=>'Video uploaded successfully' , 'video' => $videoFileUrl,'thumb' => $thumbFileName);   
                }

                return response()->json($response);

            }else{
                return response()->json([
                    "status" => "error", "msg" => "Unauthorized user!"
                ]);
            }
        }
    }



    public function videoLikes(Request $request){
        $validator = Validator::make($request->all(), [ 
            'video_id'    => 'required'
        ],[ 
            'video_id.required'      => 'Video id is required',       
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
           
            $total_likes = 0;
            if(auth()->guard('api')->user()){
                $user_id=auth()->guard('api')->user()->user_id;
                $fetchTotalVideoLikes = DB::table("videos")
                ->select(DB::raw("total_likes"))
                ->where("video_id",$request->video_id)
                ->first();

                $total_likes = $fetchTotalVideoLikes->total_likes;
                $checkExistLike = DB::table("likes")
                ->select("like_id")
                ->where("user_id",$user_id)
                ->where("video_id",$request->video_id)
                ->first();
                if($checkExistLike) {
                    DB::table('likes')->where('like_id', $checkExistLike->like_id)->delete();
                    $total_likes = $total_likes - 1;
                    $response = array("status" => "success",'is_like'=>0 , 'total_likes' => Functions::digitsFormate($total_likes));
                } else {
                    $insertData = array();
                    $insertData['user_id'] = $user_id;
                    $insertData['video_id'] = $request->video_id;
                    $insertData['liked_on'] = date("Y-m-d H:i:s");
                    DB::table("likes")->insert($insertData);
                    $total_likes = $total_likes + 1;
                    
                    // notification
                    $toUser = DB::table('videos')->where('video_id', $request->video_id)->first();
                    if ($user_id != $toUser->user_id) {
                        $user_to = User::find($toUser->user_id);
                        $user = User::find($user_id);
                        $lastName = (isset($user->lname)) ? $user->lname : '';
                        $title = $user->fname . ' ' . $lastName . ' likes ' . $toUser->title;
                         
                        $notification_settings = DB::table('notification_settings')->where('user_id', $toUser->user_id)->first();
                        if($notification_settings && $notification_settings->like == 1) {
                            $file_path = '';
                            $small_file_path = '';
                           
                            if ($user->photo != '' && $user->photo != null) {
                                if (stripos($user->photo, 'https://') !== false) {
                                    $file_path = $user->photo;
                                    $small_file_path = $user->photo;
                                } else {
                                    $file_path = asset(Storage::url('profile_pic/' . $user->id . "/" . $user->photo));
                                    $small_file_path = asset(Storage::url('profile_pic/' . $user->id . "/small/" . $user->photo));
                                }
                            }
                       
                            $description = 'like video';
                            $param = ['id' => strval($request->video_id), 'type' => 'like'];
                            if($user_to->fcm_token!=''){
                                try{
                                   $user_to->notify(new UserNotification($title, $description, $small_file_path, $param));
                                }catch (\Exception $e){
                                    
                                }
                            }
                            
                         }
                        
                        $nData['notify_by']=$user_id;
                        $nData['notify_to']=$toUser->user_id;
                        $nData['video_id'] = $request->video_id;
                        $nData['message'] = $title;
                        $nData['type'] = 'L';
                        $nData['read'] = 0;
                        $nData['added_on'] = date('Y-m-d H:i:s') ;
                        
                        DB::table('notifications')->insert($nData);
                    }
                    
                    $response = array("status" => "success",'is_like'=>1 , 'total_likes' => Functions::digitsFormate($total_likes));
                }
                DB::table("videos")->where('video_id',$request->video_id)->update(['total_likes' => $total_likes]);
                return response()->json($response);
            } else {
                return response()->json([
                    "status" => "error", "msg" => "Unauthorized user!"
                ]);
            }
        }
    }

    public function addComment(Request $request){
        $validator = Validator::make($request->all(), [ 
            'video_id'    => 'required',
            'comment' => 'required',
        ],[ 
            'video_id.required'      => 'Video id is required',
            'comment.required'      => 'Comment is required',
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            
            if(auth()->guard('api')->user()){

                $user_id=auth()->guard('api')->user()->user_id;
                $fetchTotalVideoComments = DB::table("videos")
                ->select(DB::raw("total_comments"))
                ->where("video_id",$request->video_id)
                ->first();

                $total_comments = $fetchTotalVideoComments->total_comments;
                $total_comments = $total_comments + 1;
                $data = array();
                $data['video_id'] = $request->video_id;
                $data['user_id'] = $user_id;
                $data['comment'] = strip_tags($request->comment);
                $data['added_on'] = date("Y-m-d H:i:s");
                $data['updated_on'] = date("Y-m-d H:i:s");
                $c_id=DB::table("comments")->insertGetId($data);
                DB::table("videos")->where('video_id',$request->video_id)->update(['total_comments' => $total_comments]);
                
                 $video = DB::table('videos')->where('video_id',$request->video_id)->first();
               
                ////// notification
                if ($user_id != $video->user_id) {
                    $user_to = User::find($video->user_id);
                    $user = User::find($user_id);
                    $lastName = (isset($user->lname)) ? $user->lname : '';
                    $title = $user->fname . ' ' . $lastName . ' commented on ' . $video->title;
                            
                    $notification_settings = DB::table('notification_settings')->where('user_id', $video->user_id)->first();
                        if($notification_settings && $notification_settings->comment == 1) {
                    
                            $file_path = '';
                            $small_file_path = '';
                            
                            if ($user->user_dp != '' && $user->user_dp != null) {
                                if (stripos($user->user_dp, 'https://') !== false) {
                                    $file_path = $user->user_dp;
                                    $small_file_path = $user->user_dp;
                                } else {
                                    $file_path = asset(Storage::url('profile_pic/' . $user->user_id . "/" . $user->user_dp));
                                    $small_file_path = asset(Storage::url('profile_pic/' . $user->user_id . "/small/" . $user->user_dp));
                                }
                            }
                           
                            $description = $video->title;
                            $param = ['id' => strval($request->video_id), 'type' => 'comment'];
                            // $user_to->notify(new UserNotification($title='aaa', $description='ddddddd', $file_path='ttttt', $param=[]));
                            if($user_to->fcm_token!=''){
                                try{
                                   $user_to->notify(new UserNotification($title, $description, $small_file_path, $param));
                                }catch (\Exception $e){
                                    
                                }
                            }
                            
                        }
                    
                    
                    $nData['notify_by']=$user_id;
                    $nData['notify_to']=$user_to->user_id;
                    $nData['video_id'] = $request->video_id;
                    $nData['message'] = $title;
                    $nData['type'] = 'C';
                    $nData['read'] = 0;
                    $nData['added_on'] = date('Y-m-d H:i:s') ;
                    
                    DB::table('notifications')->insert($nData);
                        
                }


                $response = array("status" => "success", "total_comments" => Functions::digitsFormate($total_comments),"comment_id"=>$c_id,"id"=>$request->video_id);
            } else {
                return response()->json([
                    "status" => "error", "msg" => "Unauthorized user!"
                ]);
            }
            return response()->json($response);
        }
    }

    public function fetchVideoComments(Request $request){
        $validator = Validator::make($request->all(), [ 
            'video_id'    => 'required'
        ],[ 
            'video_id.required'      => 'Video id is required',       
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            $functions = new Functions();
            $limit = 10;
            $comments = DB::table("comments as c")
            ->select(DB::raw("c.*,u.user_id,u.username,u.user_dp,IF(uv.verified='A', true, false) as isVerified"))
            ->join("users as u","c.user_id","u.user_id")
            // ->leftJoin("user_verify as uv","uv.user_id","c.user_id")
            ->leftJoin('user_verify as uv', function ($join){
                $join->on('uv.user_id','=','c.user_id')
                ->where('uv.verified','A');
            })
            ->where("c.video_id",$request->video_id)
            ->where("c.active",1)
            ->orderBy("c.added_on","desc")
            ->paginate($limit);
            $total_records=$comments->total();                              
            $data= array();
            if(count($comments) > 0) {
                foreach($comments as $key => $comment) {
                    $data[$key]['name'] = $comment->username;
                    if(stripos($comment->user_dp,'https://')!==false){
                        $file_path=$comment->user_dp;
                    }else{
                        $file_path = asset(Storage::url('public/profile_pic/'.$comment->user_id."/small/".$comment->user_dp));
                        // $file_path = secure_asset(config('app.profile_path').$comment->user_id."/small/".$comment->user_dp);
                        if($file_path==""){
                            $file_path=asset('default/default.png');
                        }
                    }
                    $data[$key]['pic'] = $file_path;
                    $data[$key]['comment'] = strip_tags((strlen($comment->comment) > 100) ? mb_substr($comment->comment,0,100).'..' : $comment->comment);
                    $data[$key]['comment_id'] = $comment->comment_id;
                    $data[$key]['user_id'] = $comment->user_id;
                    $data[$key]['isVerified'] = $comment->isVerified;
                    $data[$key]['timing'] = Functions::time_elapsed_string($comment->added_on);
                }
            }
            $response = array("status" => "success", "data" => $data,'total_records'=>$total_records);
            return response()->json($response);
        }
    }

    public function uploadVideo2(Request $request){
        // dd(1122);
        $validator = Validator::make($request->all(), [ 
            'user_id'          => 'required',              
            'app_token'          => 'required', 
            'video'          => 'required|mimes:mp4,mov,ogg,qt',  
            // 'video'          => 'required',  

        ],[ 
            'user_id.required'   => 'User Id  is required.',
            'app_token.required'   => 'App Token is required.',
            'video.required'   => 'Video is required',
            

        ]);

        if (!$validator->passes()) {
            // dd( $this->_error_string($validator->errors()->all()));
            // dd($request->all());   
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
        }else{
            // dd($request->all());
            $functions = new Functions();
            $token_res= $functions->validate_token($request->user_id,$request->app_token);
    
            if($token_res>0){
                $storage_path=config('app.filesystem_driver');
                $time_folder=time();
                $videoPath = 'public/videos/'.$request->user_id;

                $hashtags='';
                if(isset($request->description)) {
                    if(stripos($request->description,'#')!==false) {
                        $str = $request->description;

                        preg_match_all('/#([^\s]+)/', $str, $matches);

                        $hashtags = implode(',', $matches[1]);

                        //var_dump($hashtags);
                    }else{
                        $hashtags='';
                    }
                }

                $videoFileName=$this->CleanFileNameMp4($time_folder."_".$request->file('video')->getClientOriginalName());
            
                $request->video->storeAs( $videoPath, $videoFileName);
                Storage::setVisibility($videoPath.'/'.$videoFileName, 'public');
                // dd(Storage::url($videoPath.'/'.$videoFileName));
                $nsfw = DB::table("nsfw_settings")->where("ns_id",1)->first();
                $nudity = array();
                if($nsfw){
                    if($nsfw->status==1){
                        $nsfw_filters = [];
                        if($nsfw->nudity==1){
                            $nsfw_filters[] = 'nudity';
                        }
                        if($nsfw->wad==1){
                            $nsfw_filters[] = 'wad';
                        }
                        if($nsfw->offensive==1){
                            $nsfw_filters[] = 'offensive';
                        }
                        if($nsfw->api_key!='' && $nsfw->api_secret!=''){
                            $client = new SightengineClient($nsfw->api_key, $nsfw->api_secret);
                            $mediaOpener = FFMpeg::fromDisk($storage_path)->open($videoPath.'/'.$videoFileName);
                            $video_duration = $mediaOpener->getDurationInSeconds();
                            $pic_frames = array();
                            $secds = 0;
                            $images = [];
                            do{

                                $pic_frames[] = $secds;
                                $secds = $secds+3;

                            }while($secds<$video_duration);
                            // dd($pic_frames);
                            foreach ($pic_frames as $key => $seconds) {
                                                         
                                $mediaOpener = $mediaOpener->getFrameFromSeconds($seconds)
                                ->export()
                                ->toDisk($storage_path)
                                ->save($videoPath.'/'.$time_folder.'/'."thumb_{$key}.jpg");
                                Storage::setVisibility($videoPath.'/'.$time_folder.'/'."thumb_{$key}.jpg", 'public');
                
                                $imgName = asset(Storage::url($videoPath.'/'.$time_folder.'/'. "thumb_{$key}.jpg"));
                                $images[] = asset(Storage::url($videoPath.'/'.$time_folder.'/'."thumb_{$key}.jpg"));
                                
                                try{
                                    $output = $client->check($nsfw_filters)->set_url($imgName);
                                    if($output->status=="success"){
                                        if(in_array('wad',$nsfw_filters)){
                                            if($output->weapon > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }elseif($output->alcohol > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }elseif($output->drugs > 0.50){
                                                $nudity[] = $imgName;
                                                break;
                                            }
                                        }
                                        if(in_array('nudity',$nsfw_filters)){
                                            if(isset($output->nudity)){
                                                $raw_nudity = $output->nudity;
                                                if($raw_nudity->raw> 0.50){
                                                    $nudity[] = $imgName;
                                                    break;
                                                }
                                            }
                                        }

                                        if(in_array('offensive',$nsfw_filters)){
                                            if(isset($output->offensive)){
                                                $offensive = $output->offensive;
                                                if($offensive->prob> 0.50){
                                                    $nudity[] = $imgName;
                                                    break;
                                                }
                                            }
                                        }
                                    }else{
                                        echo "fail";
                                    }
                                }
                                catch(ClientException $e) {
                                    $msg = $e->getResponse()->getReasonPhrase();
                                    $mail_settings = DB::table("mail_settings")->where("m_id",1)->first();
                                    if($mail_settings){
                                        $company_settings = DB::table("settings")->where("setting_id",1)->first();
                                        $admin_email = $company_settings->site_email;
                                        $site_name = $company_settings->site_name;
                                        $from_email = $mail_settings->from_email;
                                        $mailBody = '
                                        <b style="font-size:16px;color:#333333;margin:0;padding-bottom:10px;text-transform:capitalize">
                                        Video Moderation API warning
                                        </b>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">
                                        '.$msg.'
                                        </p>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you</p>
                                        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">'.$site_name.'</p>
                                        ';
                                            // dd($mailBody);
                                            // $ref_id
                                        $from_email = config("app.from_mail");
                                        $array = array('subject'=>$site_name.' - Video Moderation API warning','view'=>'emails.site.company_panel','body' => $mailBody);
                                        if(filter_var($from_email, FILTER_VALIDATE_EMAIL)){
                                            $array['from'] = $from_email;
                                        }
                                        if(strpos($_SERVER['SERVER_NAME'], "localhost")===false){
                                            Mail::to($admin_email)->send(new SendMail($array));  
                                        }
                                    }
                                    break;
                                }
                            }
                            foreach($images as $val) {
                                Storage::delete($val);
                            }
                        }

                    }

                }
        

            $watermark = DB::table('settings')->first();
            // dd($watermark_img);
            $sound_id=0;
            if($request->sound_id>0){
                $sound_id=$request->sound_id;
                DB::table("sounds")->where("sound_id",$sound_id)->update([
                    'used_times'=> DB::raw('used_times+1'), 
                ]);
                $soundName = DB::table("sounds")
                ->select(DB::raw("sound_name,user_id"))
                ->where("sound_id",$request->sound_id)
                ->first();

                $video_media = FFMpeg::open('public/videos/'.$request->user_id.'/'.$videoFileName);
                $video_duration = $video_media->getDurationInSeconds();
                
                $soundPath = 'public/sounds/'. $soundName->sound_name;

                
                if($soundName->user_id>0){
                    $soundPathFile = "public/sounds/".$soundName->user_id.'/';
                }else{
                    $soundPathFile = "public/sounds/";
                }


                $ffmpeg = FFMpeg1\FFMpeg::create();
                // $ffmpeg = FFMpeg1\FFMpeg::create(array(
                //     'ffmpeg.binaries'  => 'E:/ffmpeg/bin/ffmpeg.exe',
                //     'ffprobe.binaries' => 'E:/ffmpeg/bin/ffprobe.exe',
                //     'timeout'          => 3600, // The timeout for the underlying process
                //     'ffmpeg.threads'   => 12,   // The number of threads that FFMpeg should use
                // ));
                // crop audio file
                $audio = $ffmpeg->open(asset(Storage::url($soundPathFile.$soundName->sound_name)));
                $audio->filters()->clip(FFMpeg1\Coordinate\TimeCode::fromSeconds(0), FFMpeg1\Coordinate\TimeCode::fromSeconds($video_duration));
                $audio_format =  new X264('aac', 'libx264');

                // Extract the audio into a new file as mp3
                $audio->save($audio_format, 'public/'. $soundName->sound_name);
                Storage::setVisibility('public/'. $soundName->sound_name, 'public');
                
                //audio and video mapping
                
                //watermark
                $format = new X264('aac', 'libx264');
                if($watermark){
                    $watermark_img = $watermark->watermark;
                    if($watermark_img!="") {
                        FFMpeg::fromDisk($storage_path)
                            ->open([$videoPath.'/'.$videoFileName, $soundPath])
                            ->export()
                            ->addFormatOutputMapping(new X264('libmp3lame', 'libx264'), Media::make($storage_path, $videoPath.'/'.$time_folder.'/master.mp4'), ['0:v', '1:a'])
                            ->save();
                        Storage::setVisibility($videoPath.'/'.$time_folder.'/master.mp4', 'public');

                        FFMpeg::fromDisk($storage_path)
                            ->open($videoPath.'/'.$time_folder.'/master.mp4')
                            ->addFilter(function (VideoFilters $filters) use($watermark_img) {
                                    //$filters->resize(new \FFMpeg\Coordinate\Dimension(960, 540));
                                    $filters->watermark("public/uploads/logos/".$watermark_img, [
                                        'position' => 'relative',
                                        'top' => 10,
                                        'right' => 10,
                                    ]);
                                    })
                            ->export()
                            ->toDisk($storage_path)
                            ->inFormat($format)
                            ->save($videoPath.'/'.$time_folder.'/'.$videoFileName);
                            Storage::setVisibility($videoPath.'/'.$time_folder.'/'.$videoFileName, 'public');

                        Storage::delete($videoPath.'/'.$time_folder.'/master.mp4');
                    } else {
                        FFMpeg::fromDisk($storage_path)
                            ->open([$videoPath.'/'.$videoFileName, $soundPath])
                            ->export()
                            ->addFormatOutputMapping(new X264('libmp3lame', 'libx264'), Media::make($storage_path, $videoPath.'/'.$time_folder.'/'.$videoFileName), ['0:v', '1:a'])
                            ->save();
                            Storage::setVisibility($videoPath.'/'.$time_folder.'/'.$videoFileName, 'public');
                    }
                    
                }else{
                    FFMpeg::fromDisk($storage_path)
                    ->open([$videoPath.'/'.$videoFileName, $soundPath])
                    ->export()
                    ->addFormatOutputMapping(new X264('libmp3lame', 'libx264'), Media::make($storage_path, $videoPath.'/'.$time_folder.'/'.$videoFileName), ['0:v', '1:a'])
                    ->save();
                    Storage::setVisibility($videoPath.'/'.$time_folder.'/'.$videoFileName, 'public');
                }
                
                Storage::delete("public/".$soundName->sound_name);   
                //   $soundPath = 'public/sounds/';
                //   if($soundName->user_id>0){
                //      $soundPath .= $soundName->user_id.'/';
                //   }
                //   $soundPath .= $soundName->sound_name;

                //  FFMpeg::fromDisk('local')
                //          ->open([$videoPath.'/'.$videoFileName, $soundPath])
                //          ->export()
                //          ->addFormatOutputMapping(new X264('libmp3lame', 'libx264'), Media::make('local', 'public/videos/'.$request->user_id.'/'.$time_folder.'/'.$videoFileName), ['0:v', '1:a'])
                //          ->save();


            }else{
                $format = new X264('aac', 'libx264');

                // FFMpeg::fromDisk('local')
                //     ->open($videoPath.'/'.$videoFileName)
                //     ->export()
                //     ->toDisk('local')
                //     ->inFormat($format)
                //                 //->inFormat(new \FFMpeg\Format\Audio\Aac)
                //     ->save('public/sounds/'.$request->user_id.'/'.$time_folder.'.aac');
                // $audio_media = FFMpeg::open('public/sounds/'.$request->user_id.'/'.$time_folder.'.aac');

                // $audio_duration = $audio_media->getDurationInSeconds();

                // $track = new GetId3($request->file('video'));
                // $title=$track->getTitle();
                // $album=$track->getAlbum();
                // $artist=$track->getArtist();

                // $audioData = array(
                //     'user_id' => $request->user_id,
                //     'cat_id' => 0,
                //     'title'     => ($title!=null) ? $title : "",
                //     'album'     => ($album!=null) ? $album : "",
                //     'artist'    => ($artist!=null) ? $artist : "",
                //     'sound_name' => $time_folder.'.aac',
                //     // 'tags'     => $hashtags,
                //     'duration' =>$audio_duration,
                //     'used_times' =>1,
                //     'created_at' => date('Y-m-d H:i:s')
                // ); 

                // $s_id=DB::table('sounds')->insertGetId($audioData);
                // $sound_id=$s_id;

                // watermark
                if($watermark){
                    $watermark_img = $watermark->watermark;
                    if($watermark_img!="") {
                        FFMpeg::fromDisk($storage_path)
                        ->open($videoPath.'/'.$videoFileName)
                        ->addFilter(function (VideoFilters $filters) use($watermark_img){
                            $filters->watermark("public/uploads/logos/".$watermark_img, [
                                'position' => 'relative',
                                'top' => 10,
                                'right' => 10,
                            ]);
                         })
                        ->export()
                        ->toDisk($storage_path)
                        ->inFormat($format)
                        //->inFormat(new \FFMpeg\Format\Audio\Aac)
                        ->save($videoPath.'/'.$time_folder.'/'.$videoFileName);     
                        Storage::setVisibility($videoPath.'/'.$time_folder.'/'.$videoFileName, 'public');
                    } else {
                        $videoFileName = $this->CleanFileNameMp4($time_folder."_".$request->file('video')->getClientOriginalName());
                        $request->video->storeAs($videoPath.'/'.$time_folder, $videoFileName);
                        Storage::setVisibility($videoPath.'/'.$time_folder.'/'.$videoFileName, 'public');
                    }
                }else{
                    $videoFileName = $this->CleanFileNameMp4($time_folder."_".$request->file('video')->getClientOriginalName());
                    $request->video->storeAs($videoPath.'/'.$time_folder, $videoFileName);
                    Storage::setVisibility($videoPath.'/'.$time_folder.'/'.$videoFileName, 'public');
                }
            }

            $file_path= $videoPath.'/'. $time_folder.'/'.$videoFileName;
            $c_path=  $this->getCleanFileName($time_folder.'/master.m3u8');
            

            FFMpeg::fromDisk($storage_path)
            ->open($videoPath.'/'.$videoFileName)
            ->getFrameFromSeconds(0)
            ->export()
            ->toDisk($storage_path)
            ->save($videoPath.'/thumb/'.$time_folder.'.jpg');
            Storage::setVisibility($videoPath.'/thumb/'.$time_folder.'.jpg', 'public');

            // $v_path=secure_asset(Storage::url($videoPath.'/'.$videoFileName));
            $v_path=asset(Storage::url($videoPath.'/'.$videoFileName));
           $gif_path=asset(Storage::url($videoPath."/gif"));
           $gif_storage_path=$gif_path.'/'.$time_folder.'.gif';


            $media = FFMpeg::open($videoPath.'/'.$videoFileName);
            $duration = $media->getDurationInSeconds();

            $ffmpeg = FFMpeg1\FFMpeg::create();
                    
            $video = $ffmpeg->open($v_path);

                // This array holds our "points" that we are going to extract from the
                // video. Each one represents a percentage into the video we will go in
                // extracitng a frame. 0%, 10%, 20% ..
            $points = range(0,100,50);
                //dd($points);
            $temp = asset(Storage::url("thumb"));
                // This will hold our finished frames.
            $frames = [];

            foreach ($points as $point) {

                    // Point is a percent, so get the actual seconds into the video.
                $time_secs = floor($duration * ($point / 100));

                    // Created a var to hold the point filename.
                $point_file = "$temp/$point.jpg";

                    // Extract the frame.
                $frame = $video->frame(TimeCode::fromSeconds($time_secs));
                $frame->save($point_file);

                    // If the frame was successfully extracted, resize it down to
                    // 320x200 keeping aspect ratio.
                if (file_exists($point_file)) {
                    $img = Image::make($point_file)->resize(400, 300, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });

                    $img->save($point_file, 40);
                    $img->destroy();
                }

                    // If the resize was successful, add it to the frames array.
                if (file_exists($point_file)) {
                    $frames[] = $point_file;
                }
            }

                // If we have frames that were successfully extracted.
            if (!empty($frames)) {

                    // We show each frame for 100 ms.
                $durations = array_fill(0, count($frames), 25);

                    // Create a new GIF and save it.
                $gc = new GifCreator();
                $gc->create($frames, $durations, 0);
                file_put_contents($gif_storage_path, $gc->getGif());

                    // Remove all the temporary frames.
                foreach ($frames as $file) {
                    Storage::delete($file);
                }
            }
           
            Storage::delete($videoPath.'/'.$videoFileName);

            $data =array(
                'user_id'       => $request->user_id,
                'video'         => $time_folder.'/'.$videoFileName,
                'thumb'         => $time_folder.'.jpg',
                'gif'         => $time_folder.'.gif',
                    //'title' => ($request->title==null)?'' : $request->title,
                'description' => ($request->description==null)? '' : strip_tags($request->description),
                'duration'    => $duration,
                'sound_id'     => $sound_id,
                'tags'      => strip_tags($hashtags),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            );
            $flagged=0;
            if(count($nudity)>0){
                $response = array("status" => "failed","msg"=>"Video is flagged by our system and its under moderation.");
                $flagged = 1;
            }
            $data['flag'] = $flagged;
            $v_id=DB::table('videos')->insertGetId($data);
            $video = array(
                'disk'          => $storage_path,
                'original_name' => $request->video->getClientOriginalName(),
                'path'          => $file_path,
                'c_path'        => $c_path,
                'title'         => $request->title,
                'video_id'      => $v_id,
                'user_id'       => $request->user_id
            );

            ConvertVideoForStreaming::dispatch($video);

            // FFMpeg::cleanupTemporaryFiles();
            if($flagged==1){
                return response()->json($response);    
            }

                // $data =array(
                //     'master_video' => $c_path,
                //     'updated_at' => date('Y-m-d H:i:s')
                // );
                // DB::table('videos')->where('video_id',$v_id)->update($data);
            $full_video_path=asset(Storage::url($videoPath.'/'. $time_folder.'/'.$videoFileName));
            $full_thumb_path=asset(Storage::url($videoPath.'/thumb/'. $time_folder.'.jpg'));
            $response = array("status" => "success",'msg'=>'Your video will be available shortly after we process it','file_path'=>$full_video_path,'video_id'=>$v_id,'thumb_path'=>$full_thumb_path);
            return response()->json($response);
            }
        }
    }

    private function CleanFileNameMp4($filename){
        $fname= preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename) . '.mp4';
        return str_replace(' ', '-', $fname);
    }

    public function filterUploadVideo(Request $request){

        $validator = Validator::make($request->all(), [
            'user_id'          => 'required',              
            'app_token'          => 'required', 
            'video'          => 'required|mimes:mp4,mov,ogg,qt',  

        ],[
            'user_id.required'   => 'User Id  is required.',
            'app_token.required'   => 'App Token is required.',
            'video.required'   => 'Video is required',

        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{

            $functions = new Functions();
            $token_res= $functions->validate_token($request->user_id,$request->app_token);
            if($token_res>0){

                $time_folder=time();
                $videoPath = 'public/videos/'.$request->user_id;

                $hashtags='';
                if(isset($request->description)) {
                    if(stripos($request->description,'#')!==false) {
                        $str = $request->description;

                        preg_match_all('/#([^\s]+)/', $str, $matches);

                        $hashtags = implode(',', $matches[1]);

                        //var_dump($hashtags);

                    }else{
                        $hashtags='';
                    }
                }

                $videoFileName=$request->file('video')->getClientOriginalName();
                $request->video->storeAs("public/videos/".$request->user_id, $videoFileName);
                $multiCurl = array();
                // multi handle
                $mh = curl_multi_init();

                $mediaOpener = FFMpeg::open('public/videos/'.$request->user_id.'/'.$videoFileName);
                $video_duration = $mediaOpener->getDurationInSeconds();
                $pic_frames = array();
                $secds = 0;
                $nudity = array();
                $images = [];
                do{

                    $pic_frames[] = $secds;
                    $secds = $secds+3;

                }while($secds<$video_duration);
                // dd($pic_frames);
                foreach ($pic_frames as $key => $seconds) {
                    $mediaOpener = $mediaOpener->getFrameFromSeconds($seconds)
                    ->export()
                    ->save('public/videos/'.$request->user_id.'/'."thumb_{$key}.jpg");
                    $imgName = secure_url('storage/videos/'.$request->user_id.'/'. "thumb_{$key}.jpg");
                    echo $imgName."<br/>";
                    $images[] = storage_path('app/public/videos/'.$request->user_id.'/'."thumb_{$key}.jpg");
                    $fetchURL = 'http://api.rest7.com/v1/detect_nudity.php?url='.$imgName;
            //      echo $fetchURL."<br>";
                    $multiCurl[$key] = curl_init();
                    curl_setopt($multiCurl[$key], CURLOPT_URL,$fetchURL);
                    curl_setopt($multiCurl[$key], CURLOPT_HEADER,0);
                    curl_setopt($multiCurl[$key], CURLOPT_RETURNTRANSFER,1);
                    curl_multi_add_handle($mh, $multiCurl[$key]);
                }

                $index=null;
                do {
                    curl_multi_exec($mh,$index);
                } while($index > 0);
                // get content and remove handles
                foreach($multiCurl as $k => $ch) {
                    $result = json_decode(curl_multi_getcontent($ch),true);
                    print_r($result);
                    if($result['nudity']==true && $result['nudity_percentage']>=0.65){
                        print_r($result);
                        $nudity[] = $result['nudity_percentage'];
            //      echo $images[$k];

                    }

                    unlink($images[$k]);
                    curl_multi_remove_handle($mh, $ch);
                }
                // close
                curl_multi_close($mh);

                if(count($nudity)>0){
                    $response = array("status" => "failed","msg"=>"Your video contains nudity and is flagged by our system. It can't be uploaded.");
                    return response()->json($response);
                }
                $sound_id=0;
                if($request->sound_id>0){
                    $sound_id=$request->sound_id;
                    DB::table("sounds")->where("sound_id",$sound_id)->update([
                        'used_times'=> DB::raw('used_times+1'), 
                    ]);
                    $soundName = DB::table("sounds")
                    ->select(DB::raw("sound_name,user_id"))
                    ->where("sound_id",$request->sound_id)
                    ->first();
                    
                    $video_media = FFMpeg::open('public/videos/'.$request->user_id.'/'.$videoFileName);
                    $video_duration = $video_media->getDurationInSeconds();

                    $soundPath = 'public/'. $soundName->sound_name;
                    

                    if($soundName->user_id>0){
                        $soundPathFile = "app/public/sounds/".$soundName->user_id.'/';
                    }else{
                        $soundPathFile = "app/public/sounds/";
                    }


                    $ffmpeg = FFMpeg1\FFMpeg::create();
                    $audio = $ffmpeg->open(storage_path($soundPathFile.$soundName->sound_name));
                    $audio->filters()->clip(FFMpeg1\Coordinate\TimeCode::fromSeconds(0), FFMpeg1\Coordinate\TimeCode::fromSeconds($video_duration));
                    $audio_format =  new X264('aac', 'libx264');

                // Extract the audio into a new file as mp3
                    $audio->save($audio_format, 'storage/'. $soundName->sound_name);
                    FFMpeg::fromDisk('local')
                    ->open([$videoPath.'/'.$videoFileName, $soundPath])
                    ->export()
                    ->addFormatOutputMapping(new X264('libmp3lame', 'libx264'), Media::make('local', 'public/videos/'.$request->user_id.'/'.$time_folder.'/'.$videoFileName), ['0:v', '1:a'])
                    ->save();

                    unlink(storage_path()."/app/public/".$soundName->sound_name);   
                //   $soundPath = 'public/sounds/';
                //   if($soundName->user_id>0){
                //      $soundPath .= $soundName->user_id.'/';
                //   }
                //   $soundPath .= $soundName->sound_name;

                //  FFMpeg::fromDisk('local')
                //          ->open([$videoPath.'/'.$videoFileName, $soundPath])
                //          ->export()
                //          ->addFormatOutputMapping(new X264('libmp3lame', 'libx264'), Media::make('local', 'public/videos/'.$request->user_id.'/'.$time_folder.'/'.$videoFileName), ['0:v', '1:a'])
                //          ->save();


                }else{
                    $format = new X264('aac', 'libx264');
                    
                    FFMpeg::fromDisk('local')
                    ->open($videoPath.'/'.$videoFileName)
                    ->export()
                    ->toDisk('local')
                    ->inFormat($format)
                            //->inFormat(new \FFMpeg\Format\Audio\Aac)
                    ->save('public/sounds/'.$request->user_id.'/'.$time_folder.'.aac');
                    
                    $audio_media = FFMpeg::open('public/sounds/'.$request->user_id.'/'.$time_folder.'.aac');

                    $audio_duration = $audio_media->getDurationInSeconds();

                    $track = new GetId3($request->file('video'));
                    $title=$track->getTitle();
                    $album=$track->getAlbum();
                    $artist=$track->getArtist();

                    $audioData = array(
                        'user_id' => $request->user_id,
                        'cat_id' => 0,
                        'title'     => ($title!=null) ? $title : "",
                        'album'     => ($album!=null) ? $album : "",
                        'artist'    => ($artist!=null) ? $artist : "",
                        'sound_name' => $time_folder.'.aac',
                        'tags'     => $hashtags,
                        'duration' =>$audio_duration,
                        'used_times' =>1,
                        'created_at' => date('Y-m-d H:i:s')
                    ); 

                    $s_id=DB::table('sounds')->insertGetId($audioData);
                    $sound_id=$s_id;
                    $videoFileName=$request->file('video')->getClientOriginalName();
                    $request->video->storeAs("public/videos/".$request->user_id.'/'.$time_folder, $videoFileName);
                }
                
                $file_path= "public/videos/".$request->user_id.'/'. $time_folder.'/'.$videoFileName;
                $c_path=  $this->getCleanFileName($time_folder.'/master.m3u8');


                FFMpeg::fromDisk('local')
                ->open($videoPath.'/'.$videoFileName)
                ->getFrameFromSeconds(0)
                ->export()
                ->toDisk('local')
                ->save('public/videos/'.$request->user_id.'/thumb/'.$time_folder.'.jpg');
                
                $v_path=storage_path("app/public/videos/".$request->user_id.'/'.$videoFileName);
                
                $gif_path=storage_path("app/public/videos/".$request->user_id."/gif");
                $gif_storage_path=$gif_path.'/'.$time_folder.'.gif';
                

                $media = FFMpeg::open('public/videos/'.$request->user_id.'/'.$videoFileName);
                $duration = $media->getDurationInSeconds();
                
                $ffmpeg = FFMpeg1\FFMpeg::create();
                $video = $ffmpeg->open($v_path);

                // This array holds our "points" that we are going to extract from the
                // video. Each one represents a percentage into the video we will go in
                // extracitng a frame. 0%, 10%, 20% ..
                $points = range(0,100,50);
                //dd($points);
                $temp = storage_path() . "/thumb";
                // This will hold our finished frames.
                $frames = [];

                foreach ($points as $point) {

                    // Point is a percent, so get the actual seconds into the video.
                    $time_secs = floor($duration * ($point / 100));

                    // Created a var to hold the point filename.
                    $point_file = "$temp/$point.jpg";

                    // Extract the frame.
                    $frame = $video->frame(TimeCode::fromSeconds($time_secs));
                    $frame->save($point_file);

                    // If the frame was successfully extracted, resize it down to
                    // 320x200 keeping aspect ratio.
                    if (file_exists($point_file)) {
                        $img = Image::make($point_file)->resize(400, 300, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });

                        $img->save($point_file, 40);
                        $img->destroy();
                    }

                    // If the resize was successful, add it to the frames array.
                    if (file_exists($point_file)) {
                        $frames[] = $point_file;
                    }
                }

                // If we have frames that were successfully extracted.
                if (!empty($frames)) {

                    // We show each frame for 100 ms.
                    $durations = array_fill(0, count($frames), 25);

                    // Create a new GIF and save it.
                    $gc = new GifCreator();
                    $gc->create($frames, $durations, 0);
                    file_put_contents($gif_storage_path, $gc->getGif());

                    // Remove all the temporary frames.
                    foreach ($frames as $file) {
                        unlink($file);
                    }
                }
                unlink(storage_path()."/app/public/videos/".$request->user_id.'/'.$videoFileName);
                
                $data = array(
                    'user_id'     => $request->user_id,
                    'video'       => $time_folder.'/'.$videoFileName,
                    'thumb'       => $time_folder.'.jpg',
                    'gif'         => $time_folder.'.gif',
                    //'title' => ($request->title==null) ? '' : $request->title,
                    'description' => strip_tags(($request->description==null)? '' : $request->description),
                    'duration'    => $duration,
                    'sound_id'    => $sound_id,
                    'tags'        => strip_tags($hashtags),
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s')
                );

                $v_id = DB::table('videos')->insertGetId($data);

                $video = array(
                    'disk'          => 'local',
                    'original_name' => $request->video->getClientOriginalName(),
                    'path'          => $file_path,
                    'c_path'        => $c_path,
                    'title'         => $request->title,
                    'video_id'      => $v_id,
                    'user_id'       => $request->user_id
                );

                ConvertVideoForStreaming::dispatch($video);

                FFMpeg::cleanupTemporaryFiles();
                // $data =array(
                //     'master_video' => $c_path,
                //     'updated_at' => date('Y-m-d H:i:s')
                // );
                // DB::table('videos')->where('video_id',$v_id)->update($data);
                $full_video_path=secure_url('storage/videos/'.$request->user_id.'/'. $time_folder.'/'.$videoFileName);
                $full_thumb_path=secure_url('storage/videos/'.$request->user_id.'/thumb/'. $time_folder.'.jpg');
                $response = array("status" => "success",'msg'=>'Your video will be available shortly after we process it','file_path'=>$full_video_path,'video_id'=>$v_id,'thumb_path'=>$full_thumb_path);
                return response()->json($response);
            }
        }
    }


    private function getCleanFileName($filename){
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename) . '.m3u8';
    }

    public function hashTagVideos(Request $request){
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $videoStoragePath  = asset(Storage::url("public/videos"));
        $limit = 30;
        if(auth()->guard('api')->user()){
            $login_id=auth()->guard('api')->user()->user_id;
        }else{
            $login_id=0;
        }
        
        $videos = DB::table("videos as v")->select(DB::raw("v.video_id,v.user_id, case when u.user_dp!='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',v.user_id,'/small/',u.user_dp) END ELSE '' END as user_dp,ifnull(case when thumb='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',thumb) end,'') as thumb,concat('@',u.username) as username,
            v.tags,IF(uv.verified='A', true, false) as isVerified"))
        ->join("users as u","v.user_id","u.user_id")
        // ->leftJoin("user_verify as uv","uv.user_id","u.user_id")
        ->leftJoin('user_verify as uv', function ($join){
            $join->on('uv.user_id','=','u.user_id')
            ->where('uv.verified','A');
        })
        ->where("v.deleted",0)
        ->where("v.enabled",1)
        ->where("v.active",1)
        ->where("v.flag",0);
        // ->where("v.user_id",'<>',$request->user_id);
        if($request->user_id > 0  && $request->user_id == $login_id) {
            //$videos = $videos->whereRaw(DB::raw("v.privacy=1")); 
            $videos = $videos->where("v.user_id","=", $request->user_id); 
        } else {
            $videos = $videos->where("v.privacy","<>", "1");    
        }
        if($login_id > 0 && $login_id!=$request->user_id) {
            $videos = $videos->leftJoin('blocked_users as bu1', function ($join)use ($request,$login_id){
                $join->on('v.user_id','=','bu1.user_id');
                // $join->whereRaw(DB::raw(" ( bu1.blocked_by=".$login_id." OR bu1.user_id=".$login_id." )" ));
                $join->whereRaw(DB::raw(" ( bu1.blocked_by=".$login_id." )" ));
            });

            $videos = $videos->leftJoin('blocked_users as bu2', function ($join)use ($request,$login_id){
                $join->on('v.user_id','=','bu2.blocked_by');
                // $join->whereRaw(DB::raw(" ( bu2.blocked_by=".$login_id." OR bu2.user_id=".$login_id." )" ));
                $join->whereRaw(DB::raw(" ( bu2.user_id=".$login_id." )" ));
            });
            $videos = $videos->whereRaw( DB::Raw(' bu1.block_id is null and bu2.block_id is null '));
            $videos = $videos->leftJoin('follow as f2', function ($join) use ($request,$login_id){
                $join->on('v.user_id','=','f2.follow_to')
                ->where('f2.follow_by',$login_id);
            });
            
            $videos = $videos->leftJoin('reports as rp', function ($join)use ($request,$login_id){
                $join->on('v.video_id','=','rp.video_id');
                $join->whereRaw(DB::raw(" ( rp.user_id=".$login_id." )" ));
            });
            $videos = $videos->whereRaw( DB::Raw(' rp.report_id is null '));

            if($request->user_id != $login_id) {
                $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f2.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END '));
            }
        }
        if($request->user_id > 0) {
            $videos = $videos->leftJoin('blocked_users as bu', function ($join)use ($request){
                $join->on('v.user_id','=','bu.user_id')->orOn('v.user_id','=','bu.blocked_by')
                ->whereRaw(DB::raw(" (bu.blocked_by=".$request->user_id." OR bu.user_id=".$request->user_id.")" ));
            });
            $videos = $videos->whereRaw( DB::Raw(' bu.block_id is null '));                                 
        }
        
        if(isset($request->hashtag) && $request->hashtag){
            $videos=$videos->whereRaw("FIND_IN_SET('$request->hashtag',v.tags)");
        }
        
        if(isset($request->search) && $request->search!=""){
            $search = $request->search;
            $videos = $videos->whereRaw(DB::raw("((v.title like '%" . $search . "%') or (v.tags like '%" . $search . "%') or (concat('@',u.username) like '%" . $search . "%') or (u.fname like '%" . $search . "%') or (u.lname like '%" . $search . "%') or (v.description like '%" . $search . "%'))"));
        }
        
       
        $videos = $videos->orderBy("v.video_id","desc");
        $videos= $videos->paginate($limit);
        $total_records=$videos->total();   

        $tagBannersPath = asset(Storage::url('public/banners'));
        $videoTagBanners = DB::table("video_tags")
        ->select(DB::raw("tag_id,tag as tag_name,concat('".$tagBannersPath."/',banner) as banner"))
        ->orderBy("tag_id","desc")
        ->get();

        $videoTagBannersData = array();
        if( count($videoTagBanners) > 0 ) {
            foreach($videoTagBanners as $key=>$value) {
                $videoTagBannersData[$key]['tag_id'] = $value->tag_id;
                $videoTagBannersData[$key]['tag'] = $value->tag_name;
                $videoTagBannersData[$key]['banner'] = $value->banner;
            }
        }
        
        $custom = collect(['tagBanners'=>$videoTagBannersData]);

        $videos = $custom->merge($videos);
        
        $response = array("status" => "success",'data' => $videos,'total_records'=>$total_records,'tagBanners' => $videoTagBannersData);
        return response()->json($response); 

    }

    public function mostViewedVideoUsers(Request $request){

            if(auth()->guard('api')->user()) {
                $user_id=auth()->guard('api')->user()->user_id;
                $userDpPath = asset(Storage::url('public/profile_pic'));
                $videoStoragePath  = asset(Storage::url("public/videos"));
                $limit = 15;
                $users = DB::table("users as u")->select(DB::raw("u.user_id,max(v.video_id) as video_id,
                    case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',v.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp,ifnull(case when max(thumb)='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',max(thumb)) end,'') as thumb,
                    concat('@',u.username) as username, case when f.follow_id > 0 THEN 'Following' ELSE 'Follow' END as followText"))
                ->join('videos as v', function ($join) use ($request){
                    $join->on('v.user_id','=','u.user_id')
                    ->orderBy('max(v.total_views)')
                    ->limit(1);
                })
                ->leftJoin('follow as f', function ($join) use ($request,$user_id){
                    $join->on('u.user_id','=','f.follow_to')
                    ->where('f.follow_by',$user_id);
                })
                ->where("v.deleted",0)
                ->where("v.enabled",1)
                ->where("u.deleted",0)
                ->where("u.active",1)
                ->where("u.user_id",'<>',$user_id)
                ->where("f.follow_id",null)
                ->groupBY("u.user_id","f.follow_id","u.user_dp","v.user_id","u.username");

                if(isset($request->search) && $request->search!=""){
                    $search = $request->search;
                    $users = $users->whereRaw(DB::raw("((v.title like '%" . $search . "%') or (v.tags like '%" . $search . "%') or (u.username like '%" . $search . "%') or (u.fname like '%" . $search . "%') or (u.lname like '%" . $search . "%'))"));
                    //where('v.title', 'like', '%' . $search . '%')->orWhere('v.tags', 'like', '%' . $search . '%')->orWhere('v.tags', 'like', '%' . $search . '%')->orWhere('u.username', 'like', '%' . $search . '%')->orWhere('u.fname', 'like', '%' . $search . '%')->orWhere('u.lname', 'like', '%' . $search . '%');
                }

                $users = $users->orderBy("u.user_id","desc");
                $users= $users->paginate($limit);
                $total_records=$users->total();   

                $response = array("status" => "success",'data' => $users,'total_records'=>$total_records);
            } else {
                return response()->json([
                    "status" => "error", "msg" => "Unauthorized user!"
                ]);
            }
        
        return response()->json($response); 
    }

    public function video_enabled(Request $request){
        $validator = Validator::make($request->all(), [ 
            'video_id'          => 'required',
            'description'    => 'required'
        ],[  
            'video_id.required'   => 'Video Id is required',
            'description.required'   => 'Description is required',
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            $hashtags='';
            $title='';
            $users_res = DB::table("users as u")
            ->select(DB::raw("u.username as username,v.sound_id as sound_id"))
            ->join("videos as v","v.user_id","u.user_id")
            ->where('v.video_id',$request->video_id)
            ->first();
            $sound_res = DB::table("sounds")
            ->select(DB::raw("sound_id,cat_id"))
            ->where('sound_id',$users_res->sound_id)
            ->first();

            if(isset($request->description)) {
                if(stripos($request->description,'#')!==false) {
                    $str = $request->description;

                    preg_match_all('/#([^\s]+)/', $str, $matches);

                    $hashtags = implode(',', $matches[1]);
                    $title = implode('-', $matches[1]);
                        //var_dump($hashtags);

                }else{
                    $hashtags='';
                    if(stripos($request->description,' ')!==false) {
                        $desc=explode(' ',$request->description);

                        $title=$desc[0].'-'.$desc[1];
                    }
                    else{
                        $title=$request->description;
                    }
                }
            }
            $title=$users_res->username.'-'.$title;
            if( $sound_res->cat_id==0 ){
                $audio['tags'] = $hashtags;
                $audio['title'] = $title;
                DB::table("sounds")->where('sound_id',$users_res->sound_id)->update($audio);
            }

            $data['tags'] = $hashtags;
            $data['title'] = strip_tags($title);
            $data['enabled'] = '1';
            $data['description'] = strip_tags($request->description);
            $data['privacy'] = $request->privacy;
            DB::table("videos")->where('video_id',$request->video_id)->update($data);
            $response = array("status" => "success",'msg'=> 'Video enabled Successfully.');
            return response()->json($response);
        }
    }
    
    public function deleteVideo(Request $request){

        $validator = Validator::make($request->all(), [ 
            'video_id'          => 'required'
        ],[  
            'video_id.required'   => 'Video Id is required'
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            
            if(auth()->guard('api')->user()) {
               $user_id=auth()->guard('api')->user()->user_id;
                $video_detail=DB::table('videos')
                ->where('user_id', $user_id)
                ->where('video_id', $request->video_id)
                ->first();
                if($video_detail){
                        $name=$video_detail->thumb;
                        $f_name=explode('.',$name);

                        $folder_name=$user_id.'/'.$f_name[0];
                        $thumb_name=$user_id.'/thumb/'.$f_name[0].'.jpg';
                        // $gif_name=$this->authUser->user_id.'/gif/'.$f_name[0].'.gif';
                    
                        Storage::deleteDirectory("public/videos/" . $folder_name);
                        Storage::delete("public/videos/" . $thumb_name);

                        DB::table("videos")->where('video_id',$request->video_id)->delete();
                        $response = array("status" => "success",'msg'=> 'Video deleted Successfully.');
                }else{
                    $response = array("status" => "error",'msg'=> 'Invalid Video id.');
                }
                return response()->json($response);
            }else{
                $response = array("status" => "Failed",'msg'=> 'App Token Not Verify');
                return response()->json();
            }
        }
    }
    
    public function video_views(Request $request){
        $validator = Validator::make($request->all(), [ 
            'video_id'          => 'required', 
        ],[ 
            'video_id.required'   => 'Video Id is required',
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            if($request->unique_token){
                $unique_res = DB::table('unique_users_ids')
                ->select(DB::raw('unique_id'))
                ->where('unique_token',$request->unique_token)
                ->first();
                $unique_id=$unique_res->unique_id;
            }else{
                $unique_id=0;
            }
            if(auth()->guard('api')->user()){
                $u_id=auth()->guard('api')->user()->user_id;
            }else{
                $u_id=0;
            }
            //DB::enableQueryLog();     
//          $check_view = DB::table('video_views')
//                  ->select(DB::raw('view_id'))
//                  ->where('video_id',$request->video_id)
//                  ->where(DB::raw('(user_id='.$request->user_id.' or unique_id='.$unique_id.')'))
//                  ->whereDate('viewed_on','=',date('Y-m-d'))
//                  ->first(); 
            $check_view =DB::select("select view_id from `video_views` where `video_id` = $request->video_id and (user_id=$u_id or unique_id=$unique_id) and
                DATE(`viewed_on`) = '".date('Y-m-d')."'  limit 1");
    //   dd($check_view);
            $views=0;
            $views_res = DB::table('videos')
            ->select(DB::raw('total_views'))
            ->where('video_id',$request->video_id)
            ->first();

            $views=$views_res->total_views;
            
            if(empty($check_view)){
                DB::table('video_views')->insert(['user_id' => $u_id,'video_id'=>$request->video_id,'viewed_on'=>date('Y-m-d H:i:s'),'unique_id'=>$unique_id]);
                $views=$views+1;
                DB::table('videos')->where('video_id',$request->video_id)->update(['total_views' => $views]);
            }
             // dd(DB::getQueryLog()); 
            $response = array("status" => "success",'total_views'=> $views);
            return response()->json($response);

        }
    }

    public function getWatermark(Request $request){
        $watermark = DB::table('settings')->first();
        $status = "failure";
        if($watermark){
            $watermark_img = $watermark->watermark;
            if($watermark_img!="") {
               $url =  asset(Storage::url("public/uploads/logos/small_".$watermark_img));
               $status = "success";
            }else{
                $url="";
            }
        }else{
            $url = "";
        }
        $response = array("status" => $status,'watermark'=> $url);

        return response()->json($response);
    }

    // public function fetchVideoDescription(Request $request){

    //     $validator = Validator::make($request->all(), [ 
    //         'video_id'          => 'required',
    //         'user_id'    => 'required',
    //         'app_token'  => 'required'
    //     ],[  
    //         'video_id.required'   => 'Video Id is required',
    //         'user_id.required'   => 'User Id is required',
    //         'app_token.required'   => 'App token is required',
    //     ]);

    //     if (!$validator->passes()) {
    //         return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
    //     }else{
    //         $functions = new Functions();
    //         $token_res= $functions->validate_token($request->user_id,$request->app_token);
       
    //         if($token_res>0) {
    //             $data=DB::table("videos")->select('description')->where('video_id',$request->video_id)->first();
    //             if($data){
    //                 $response = array("status" => "success",'description'=> $data->description);
    //             }else{
    //                 $response = array("status" => "Failed",'msg'=> 'Invalid Video id');
    //             }
    //             return response()->json($response);
    //         }else{
    //             $response = array("status" => "Failed",'msg'=> 'App Token Not Verify');
    //             return response()->json($response);
    //         }
    //     }
    // }

    public function updateVideoDescription(Request $request){

        $validator = Validator::make($request->all(), [ 
            'video_id'          => 'required',
            'description' => 'required',
            'privacy' => 'required'
        ],[  
            'video_id.required'   => 'Video Id is required',
            'description.required'   => 'Description is required',
            'privacy.required'   => 'Privacy is required'
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
            
            if(auth()->guard('api')->user()) {
                DB::table("videos")->where('video_id',$request->video_id)->update(['description'=>$request->description,'privacy'=>$request->privacy]);
            
                $response = array("status" => "success",'mag'=> 'video description update successfully');
               
                return response()->json($response);
            }else{
                $response = array("status" => "Failed",'msg'=> 'App Token Not Verify');
                return response()->json($response);
            }
        }
    }

}   