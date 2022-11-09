<?php
namespace App\Http\Controllers\Web;

use App\Events\MyEvent;
use App\Helpers\Common\Functions;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Storage;
use Auth;
use Dotenv\Result\Success;
use Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Exception;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Facades\Socialite;
use stdClass;

class WebController extends WebBaseController
{

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    //protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function home(Request $request)
    {
        $cookieExists = Cookie :: get('videoViewed');
        // dd($cookieExists);
        if ($cookieExists == null) {
            $cookieTime = time() + 60 * 60 * 24 * 30; // 30 days
            $token = str_random(32);

            Cookie :: queue('videoViewed', $token, $cookieTime);
            DB::table('unique_users_ids')->insert(['unique_token' => $token]);

        }
        $notifIds = explode(',', request()->get('notify_ids'));

        if (!empty($notifIds)) { 
            DB::table('notifications')
                ->whereIn('notify_id', $notifIds)
                ->update(['read' => 1,]);
        }

        $video = DB::table('videos')->where('video_id', intval(request()->get('videoId')))->first();
        $sharedVideoId = '';
        $sharedVideoSrc = '';

        $homeHeaderVideo = DB::table('videos as v')->select('v.video','v.user_id')
                            ->leftJoin('users as u','u.user_id','v.user_id')
                            ->where('u.active',1)
                            ->where('u.deleted',0)
                            ->where('v.active',1)
                            ->where('v.flag',0)
                            ->where('v.privacy',0)
                            ->where('v.deleted',0)
                            ->inRandomOrder()->first();
                            if($homeHeaderVideo){
        $homeHeaderVideoUrl = asset(Storage::url('public/videos/' . $homeHeaderVideo->user_id . '/' . $homeHeaderVideo->video ));
        // dd($homeHeaderVideo);
                            }else{
                                $homeHeaderVideoUrl='';
                            }
        if (Auth::guard('web')->check()) {
            $user_id= $this->authUser->user_id;
        }else{
            $user_id=0;
        }
        $limit=config('app.videos_per_page');
        if($request->page){
            if($request->type){
                $offset=0;
                $limit=$request->page * $limit;
            }else{
                $offset=($request->page-1) * $limit;
            }
        }else{
            $offset=0;
        }
        
        $videos = DB :: table('videos as v')
                    ->select(DB::raw('v.user_id, v.video_id,u.username, v.video,v.title,v.description, v.thumb, v.gif, v.total_likes, v.total_comments, v.total_views, ifnull(l.like_id, 0) as liked,uv.verified as verified,u.user_dp,CONCAT(u.fname," ",u.lname) as name,v.total_comments'))
                    // ->leftJoin('likes as l','l.')
                    ->leftJoin('likes as l', function ($join)use ($user_id){
                        $join->on('l.video_id','=','v.video_id')
                            ->whereRaw(DB::raw(" l.user_id=".$user_id ));
                        })
                    // ->leftJoin('user_verify as uv','uv.user_id','=','v.user_id')
                    ->leftJoin('user_verify as uv', function ($join){
                        $join->on('uv.user_id','=','v.user_id')
                        ->where('uv.verified','A');
                    });

                    if($user_id > 0) {
                        $videos = $videos->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu.user_id');
                            $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                        });

                        $videos = $videos->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu2.blocked_by');
                            $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                        });

                        $videos = $videos->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                       
                        $videos = $videos->leftJoin('follow as f', function ($join)use ($user_id){
                            $join->on('v.user_id','=','f.follow_to');
                            $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                        });
                        $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                    }else{
                        $videos= $videos->where('v.privacy',0);
                    }
                    
                    $videos = $videos->join('users as u','u.user_id','=','v.user_id')
                    ->inRandomOrder()
                    //->orderBy('v.video_id','desc')
                    // ->orderBy('v.total_likes')->orderBy('v.total_views')
                    ->where(['v.active' => 1, 'v.enabled' => 1, 'u.active'=>1,'v.flag'=>0])
                    ->skip($offset)->take($limit)->get();
                    // ->paginate($limit);
        $videosCount = DB :: table('videos as v')
                ->leftJoin('likes as l', function ($join)use ($user_id){
                    $join->on('l.video_id','=','v.video_id')
                        ->whereRaw(DB::raw(" l.user_id=".$user_id ));
                    })
                // ->leftJoin('user_verify as uv','uv.user_id','=','v.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','v.user_id')
                    ->where('uv.verified','A');
                });
                if($user_id > 0) {
                    $videosCount = $videosCount->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                        $join->on('v.user_id','=','bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                    });

                    $videosCount = $videosCount->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                        $join->on('v.user_id','=','bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                    });

                    $videosCount = $videosCount->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                    $videosCount = $videosCount->leftJoin('follow as f', function ($join)use ($user_id){
                        $join->on('v.user_id','=','f.follow_to');
                        $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                    });
                    $videosCount = $videosCount->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                }else{
                    $videosCount= $videosCount->where('v.privacy',0);
                }
                $videosCount = $videosCount->join('users as u','u.user_id','=','v.user_id')
                ->orderBy('v.total_likes')->orderBy('v.total_views')
                ->where(['v.active' => 1, 'v.enabled' => 1, 'u.active'=>1,'v.flag'=>0,'v.privacy'=>0])
                ->count();
                    // dd($videos);
        if (!empty($video)) {
            $sharedVideoId = request()->get('videoId');
            $sharedVideoSrc = asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video ));
        }
        $home_data=DB::table('home_settings')->first();
        if($request->page){
            $count=$offset;
            $html='';
            foreach($videos as $video){
            $html.='<div class="col-lg-4 col-md-6 col-12 video p-2" style="text-align:center;">
            <div style="box-shadow: 0px 2px 8px #ccc;border-radius: 5px;padding:10px;">
                <div class="row container_top_header"  onclick="openModal(\'video_'.$count.'\')">
                    <div class="col-md-3 col-3 userdp_div">';
                    
                    if($video->user_dp!=""){
                    if(strpos($video->user_dp,'facebook.com') !== false || strpos($video->user_dp,'fbsbx.com') !== false || strpos($video->user_dp,'googleusercontent.com') !== false){ 
                        $u_dp=$video->user_dp;
                     }else{
                        // $exists = Storage::disk(config('app.filesystem_driver'))->exists('public/profile_pic/'.$video->user_id.'/small/'.$video->user_dp);
                        // if($exists){ 
                            $u_dp=asset(Storage::url('public/profile_pic').'/'.$video->user_id.'/small/'.$video->user_dp) ;
                        //  }else{ 
                        // $u_dp= asset('storage/profile_pic/default.png');
                        // } 
                    }
                }else{ 
                        $u_dp= asset('default/default.png');
                        } 
                       
                $html.='<img class="img-fluid" src="'.$u_dp.'">';
                $html.='</div>';
                $html.='<div class="col-md-9 col-9 text-left pl-0">';
                $html .= '<h5 class="username_div">'.$video->name.'</h5>';
                $html .= '<div class="title_div">'.((strlen($video->description) > 20 ) ? mb_substr($video->description, 0, 20).'...' : $video->description).'</div>';
                // $html .= '<p class="title_div">'.((strlen($video->title) > 22 ) ? mb_substr($video->title, 0, 22).'...' : $video->title).'</p>';
                $html.='</div>';
                $html.='</div>';
                $html.='<div class="video-container">';
                $html.='<video muted="muted" id="video_'.$count.'" data-bs-toggle="modal" data-bs-target="#LeukeModal"  
                        onmouseover="hoverVideo('.$count.')" onmouseout="hideVideo('.$count.')" class="img-responsive" style="height:100%;border-radius: 8px;background: #000;"
                        loop preload="none" onclick="modelbox_open(\''.asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )).'\', '.$video->video_id .', video_'.$count.')"
                        poster="'.asset(Storage::url('public/videos/' . $video->user_id . '/thumb/' . $video->thumb )) .'">
                        <source src="'. asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )) .'" type="video/mp4">
                    </video>';
                    $html.='</div>';
                    $html.='<div class="user_name">';
                $html.='<div>@'.$video->username;
                     if($video->verified=='A'){ 
                        $html.=' <img src="'. asset('default/verified-icon-blue.png') .'" alt="" style="width:15px;height:15px;">';
                   } 
                   $html.='</div>';
                   $html.='<div class="video_view" id="video_view_'.$video->video_id.'" style="'.Functions::getTopbarColor().'"><i class="fa fa-eye"></i> '.$video->total_views.'</div>';
                   $html.='</div>';
                   $html.='<div class="views row m-1" onclick="openModal(\'video_'.$count.'\')">';
                   $html.='<div class="col-md-6 col-6 text-center" id="video_like_'.$video->video_id.'">';
                    $html.='<i class="fa fa-heart-o" aria-hidden="true"></i> '.$video->total_likes ;
                    $html.='</div>';
                    $html.='<div class="col-md-6 col-6 text-center">';
                    $html.='<i class="fa fa-comment-o" aria-hidden="true"></i> '.$video->total_comments.
                    '</div>
                </div>
            </div>
           
        </div>';
        $count++;
                }
            return response()->json(['html'=> $html,'videos' => $videos, 'sharedVideoId' => $sharedVideoId, 'sharedVideoSrc' => $sharedVideoSrc,'home_data'=>$home_data,'videosCount'=>$videosCount ]);
        }
        return view('web.home', ['videos' => $videos, 'sharedVideoId' => $sharedVideoId, 'sharedVideoSrc' => $sharedVideoSrc,'home_data'=>$home_data,'videosCount'=>$videosCount,'homeHeaderVideoUrl'=>$homeHeaderVideoUrl ]);
    }

    public function videoInfo($videoId)
    {
        $video = DB::table('videos as v')
                ->join('users as u', 'u.user_id', 'v.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','v.user_id')
                    ->where('uv.verified','A');
                })
                // ->leftJoin('user_verify as uv', 'uv.user_id', 'v.user_id')
                ->select(DB::raw('u.user_id,u.username,v.title,v.description, v.created_at,v.tags, v.total_views, v.video_id, u.user_dp, u.login_type, CONCAT(fname," ",lname) as name, uv.verified'))
                ->where('v.video_id', $videoId)
                ->first();
                // dd($video);
            $hashtags='';
            $hashtagsHtml='';
          if($video->tags){
              $tagsArr=explode(' ',$video->tags);
                        foreach($tagsArr as $val){
                            if(strpos($val,'#') !== false){
                                if((strpos($val,'#'))==0){
                                    $value=$val;
                                    $par= trim($val,'#');
                                }else{
                                    $value= '#'.$val;
                                    $par=$val;
                                }

                            }else{
                                $value='#'.$val;
                                $par=$val;
                            } 

                            $hashtagsHtml.='<a class="pjax text-white" href="'.route('web.tagVideos', $par).'">
                                '.$value.'
                            </a>';
                        }
                        $hashtags = $hashtagsHtml;
          }  
        if(!empty($video->user_dp)){
            if((strpos($video->user_dp,'facebook.com') !== false) || (strpos($video->user_dp,'fbsbx.com') !== false) || (strpos($video->user_dp,'googleusercontent.com') !== false)){
                $video->user_dp=$video->user_dp;
            }else{
                // $exists = Storage::disk(config('app.filesystem_driver'))->exists('public/profile_pic/'.$video->user_id.'/small/'.$video->user_dp);
                // if($exists){
                // if(file_exists(public_path('storage/profile_pic').'/'.$user->user_id.'/small/'.$user->user_dp)){
                    $video->user_dp=asset(Storage::url('public/profile_pic/' . $video->user_id. '/small/' . $video->user_dp));
                // }else{
                //     $video->user_dp=asset('storage/profile_pic/default.png');
                // } 
            }  
        }else{
            $video->user_dp=asset('default/default.png');
        }    
                // dd($video);
        $comment_html = '';
        $profileImg = '';
        $userLikedVideo = 'false';
      
        $comments = DB::table('comments as c')
                    //->leftJoin('videos as v', 'c.video_id', 'v.video_id')
                    ->join('users as u', 'u.user_id', 'c.user_id')
                    // ->leftJoin('user_verify as uv', 'uv.user_id', 'c.user_id')
                    ->leftJoin('user_verify as uv', function ($join){
                        $join->on('uv.user_id','=','c.user_id')
                        ->where('uv.verified','A');
                    })
                    ->select('u.user_id', 'u.username', 'c.comment', 'c.updated_on', 'u.login_type', 'u.user_dp','uv.verified')
                    ->where('c.video_id', $videoId)
                    ->paginate(config('app.view_per_page'));
            $morePages = $comments->hasMorePages();
                    // ->limit($comments_per_page)
                    // ->get();

        if (Auth::guard('web')->check()) {
            $comment_html = view('partials.comments', ['comments' => $comments,'morePages' =>$morePages])->render();
            $u_id = $this->authUser->user_id;
        }else{
            $u_id= 0;
        }
        

        $is_follow=DB::table('follow')
                    ->where('follow_to',$video->user_id)
                    ->where('follow_by',$u_id)
                    ->exists();
                    if($is_follow){
                        $f_label="Unfollow";
                    }else{
                        $f_label="Follow";
                    }
                    $follow_html="";
                    $main_color=Functions::getTopbarColor();
                    if($video->user_id!=$u_id){
        $follow_html='<span onclick="homefollowUnfollow('.$video->user_id.');return false;"><span class="follow_btn" id="followUnfollow" style="'.$main_color.'">'.$f_label.'</span></span>';
        // $follow_html='<span><span class="follow_btn" id="followUnfollow">'.$f_label.'</span></span>';
                    }
        $profileImg = Functions::getProfileImageUrl($this->authUser);
        // if(!empty($video->user_dp)) {
        //     if($video->login_type == 'O') {
        //        $profileImg =  asset('storage/profile_pic/' . $video->user_id) . '/small/' . $video->user_dp;
        //     } else {
        //        $profileImg = $video->user_dp; 
        //     }
        // } else {
        //     $profileImg = asset('storage/profile_pic/default.png');
        // }

        if (Auth::guard('web')->check()) {
            $userLikedVideo = DB::table('likes')->where(['user_id' => $this->authUser->user_id, 'video_id' => $videoId])->exists();
        }

        $cookieExists = Cookie :: get('videoViewed');
        // dd($cookieExists);
        if ($cookieExists == null) {
            $cookieTime = time() + 60 * 60 * 24 * 30; // 30 days
            $token = str_random(32);

            Cookie :: queue('videoViewed', $token, $cookieTime);
            DB::table('unique_users_ids')->insert(['unique_token' => $token]);

        }
        
        return response()->json(['video' => $video, 'comment_html' => $comment_html, 'profileImg' => $profileImg, 
                'userLikedVideo' => $userLikedVideo,'follow_html' => $follow_html,'hashtags'=>$hashtags]);
    }

    public function videoViewed($videoId, Request $request) {

        $cookieExists = Cookie :: get('videoViewed');
        // dd($cookieExists);
        if ($cookieExists == null) {
            $cookieTime = time() + 60 * 60 * 24 * 30; // 30 days
            $token = str_random(32);

            Cookie :: queue('videoViewed', $token, $cookieTime);
            DB::table('unique_users_ids')->insert(['unique_token' => $token]);
            $cookieExists = Cookie :: get('videoViewed');
        }
        if($cookieExists){
            $unique_res = DB::table('unique_users_ids')
                ->select('unique_id')
                ->where('unique_token',$cookieExists)
                ->first();
                if($unique_res){
                    $unique_id=$unique_res->unique_id;
                }
                
            }
        
     
        $userId = Auth::guard('web')->check() ? $this->authUser->user_id : 0;
        $check_view =DB::select("select view_id from `video_views` where `video_id` = $videoId and  unique_id=$unique_id and DATE(`viewed_on`) = '".date('Y-m-d')."' limit 1");
        // $check_view =DB::select("select view_id from `video_views` where `video_id` = $videoId and (user_id=" . 
        //     $userId . " or unique_id=$unique_id) and DATE(`viewed_on`) = '".date('Y-m-d')."' limit 1");
        // dd($check_view);
        $views=0;
        $views_res = DB::table('videos')
        ->select(DB::raw('total_views'))
        ->where('video_id',$videoId)
        ->first();

        $views=$views_res->total_views;
        
        if(empty($check_view)){
            DB::table('video_views')->insert([
                    'user_id' => $userId,
                    'video_id'=>$videoId,
                    'viewed_on'=>date('Y-m-d H:i:s'),
                    'unique_id'=>$unique_id]);
            $views=$views+1;
            DB::table('videos')->where('video_id',$videoId)->update(['total_views' => $views]);
        }
         // dd(DB::getQueryLog()); 
        $response = array("status" => "success",'total_views'=> $views);
        return response()->json($response);

    }

    public function videoComments($videoId, $type)
    {
        $morePages = '';
        $comments =  DB::table('comments as c')
                    ->join('users as u', 'u.user_id', 'c.user_id')
                    // ->leftJoin('user_verify as uv', 'uv.user_id', 'c.user_id')
                    ->leftJoin('user_verify as uv', function ($join){
                        $join->on('uv.user_id','=','c.user_id')
                        ->where('uv.verified','A');
                    })
                    ->select('u.user_id', 'u.username', 'c.comment', 'c.updated_on', 'u.login_type', 'u.user_dp','uv.verified')
                    ->where('c.video_id', $videoId);
        if ($type == 'scroll') {
            
            $comments = $comments->paginate(config('app.view_per_page'));
            $morePages = $comments->hasMorePages();
        } else {
            $comments = $comments->orderBy('c.added_on', 'desc')
                        ->first();
            $comments = collect([$comments]);
        }

        $comment_html = view('partials.comments', ['comments' => $comments, 'morePages' => $morePages])->render();
        
        return response()->json(['comment_html' => $comment_html, 'morePages' => $morePages]);
    }

    public function videoLike($videoId)
    {
        $success = false;
        $liked = false;
        $msg = "Liked you video";
        $type = "L";

        $video = DB::table('videos')->where('video_id', $videoId)->first();
        if (empty($video)) {
            return response()->json(['success' => $success, 'liked' => $liked]);
        }
        
        $dt = date('Y-m-d h:i:s');
        $isVideoLiked = DB::table('likes')
                        ->where(['video_id' => $videoId, 'user_id' => $this->authUser->user_id])
                        ->exists();
        
        if (!$isVideoLiked) {
            DB::table('likes')
                ->insert([
                    'liked_on' => $dt,
                    'video_id' => $videoId,
                    'user_id' => $this->authUser->user_id,
                ]);
            DB::table('videos')->where('video_id', $videoId)->increment('total_likes');
            $success = true;
            $liked = true;
        } else {
            DB::table('likes')->where(['video_id' => $videoId, 'user_id' => $this->authUser->user_id])->delete();
            DB::table('videos')->where('video_id', $videoId)->decrement('total_likes');
            $msg = "Disliked your video";
            $type = "UL";
            $liked = false;
            $success = true;
            //change the notification status of user video liked to read.
            DB::table('notifications')
            ->where(['notify_by' => $this->authUser->user_id, 'notify_to' => $video->user_id, 'video_id' => $videoId, 'type' => 'L'])
            ->update(['read' => 1]);
        }
        $totalLikes=Db::table('likes')->where('video_id',$videoId)->count();
        $profileImg = Functions::getProfileImageUrl($this->authUser);
        // if(!empty($authUser->user_dp)) {
            //     if($authUser->login_type == 'O') {
                //        $profileImg =  asset('storage/profile_pic/' . $authUser->user_id) . '/small/' . $authUser->user_dp;
                //     } else {
                    //        $profileImg = $authUser->user_dp; 
                    //     }
                    // } else {
                        //     $profileImg = asset('storage/profile_pic/default.png');
                        // }
                        
        if ($video->user_id != $this->authUser->user_id) {

            $notify_ids = DB::table('notifications')
                ->insertGetId([
                    'notify_by' =>  $this->authUser->user_id,
                    'notify_to' => $video->user_id,
                    'message' => $msg,
                    'Video_id' => $videoId,
                    'type' => $type,
                    'added_on' => $dt,
                ]);
            $checkUserVerified =Functions::checkUserVerified();
            $notifications = new stdClass();
            $notifications->profileImg = $profileImg;
            $notifications->user_id = $this->authUser->user_id;
            $notifications->username = $this->authUser->username;
            $notifications->type = $type;
            $notifications->video_id = $videoId;
            $notifications->added_on = $dt;
            $notifications->message = $msg;
            $notifications->notify_to = $video->user_id;
            $notifications->notify_total = 0;
            $notifications->notify_ids = $notify_ids;
            $notifications->read = 0;
            $notifications->verified =$checkUserVerified;
            /* $notify = [
                'notifyUserName' => $this->authUser->username,
                'notifyUserId' => $this->authUser->user_id,
                // 'follow' => $follow,
                'type' => $type,
                'notify_to' => $video->user_id,
                'video_id' => $videoId,
                'msg' => $msg,
                'date' => date('d.m.Y , H:i', strtotime($dt)),
                'profileImg' => $profileImg,
            ]; */

            $notifications = collect([$notifications]);

            $notifyHtml = view('partials.notification', ['notifications' => $notifications])->render();

            // event(new MyEvent($notify, $video->user_id));
            event(new MyEvent($notifyHtml, $video->user_id));
        }

        return response()->json(['success' => $success, 'liked' => $liked,'totalLikes'=>$totalLikes]);
    }

    public function videoPostComments($videoId, Request $request)
    {
        $success = false;
        $type = "C";
        $video = DB::table('videos')->where('video_id', $videoId)->first();
        if (empty($video)) {
            return response()->json(['success' => $success]);
        }

        $rules = [
            'video_comment' => ['required', 'string'],
        ];

        $messages = [
            'video_comment.required'            => 'You cant leave field empty',
        ];
        $this->validate($request, $rules, $messages);

        $dt = date('Y-m-d h:i:s');
        $msg = "Comment on your video";

        DB::table('comments')
            ->insert([
                'video_id' => $videoId,
                'user_id' => $this->authUser->user_id,
                'added_on' => $dt,
                'updated_on' => $dt,
                'comment' => strip_tags($request->video_comment)
            ]);
        DB::table('videos')->where('video_id', $videoId)
            ->update([
              'total_comments'=> DB::raw('total_comments+1')
            ]);
        $profileImg = Functions::getProfileImageUrl($this->authUser);
        
        if ($video->user_id != $this->authUser->user_id) {
            $checkUserVerified =Functions::checkUserVerified();
            $notify_ids = DB::table('notifications')
                ->insertGetId([
                    'notify_by' =>  $this->authUser->user_id,
                    'notify_to' => $video->user_id,
                    'message' => $msg,
                    'Video_id' => $videoId,
                    'type' => 'VC',
                    'added_on' => $dt,
                ]);
            /* $notify = [
                'notifyUserName' => $this->authUser->username,
                'notifyUserId' => $this->authUser->user_id,
                // 'follow' => $follow,
                'type' => $type,
                'notify_to' => $video->user_id,
                'video_id' => $videoId,
                'msg' => $msg,
                'date' => date('d.m.Y , H:i', strtotime($dt)),
                'profileImg' => $profileImg,
            ]; */

            $notifications = new stdClass();
            $notifications->profileImg = $profileImg;
            $notifications->user_id = $this->authUser->user_id;
            $notifications->username = $this->authUser->username;
            $notifications->type = $type;
            $notifications->video_id = $videoId;
            $notifications->added_on = $dt;
            $notifications->message = $msg;
            $notifications->notify_to = $video->user_id;
            $notifications->notify_total = 0;
            $notifications->notify_ids = $notify_ids;
            $notifications->read = 0;
            $notifications->verified =$checkUserVerified;

            $notifications = collect([$notifications]);

            $notifyHtml = view('partials.notification', ['notifications' => $notifications])->render();

            // event(new MyEvent($notify, $video->user_id));
            event(new MyEvent($notifyHtml, $video->user_id));
        }

        return response()->json(['success' => true]);
    }

    public function logout()
    {
        // Cookie::queue(Cookie::forget('videoViewed'));
        // $cookieExists = Cookie :: get('videoViewed');
        // dd($cookieExists);
        // if ($cookieExists == null) {
            // $cookieTime = time() + 60 * 60 * 24 * 30; // 30 days
            // $token = str_random(32);

            // Cookie :: queue('videoViewed', $token, $cookieTime);
           
            // DB::table('unique_users_ids')->insert(['unique_token' => $token]);

        // }
        Auth::guard('web')->logout();
        
        return redirect()->route('web.login');
    }

    public function searchAll(Request $request){
        $search_term = request()->get('search');
     
        if (Auth::guard('web')->check()) {
            $user_id= $this->authUser->user_id;
        }else{
            $user_id=0;
        }
        if(isset($request->active)){
            $active=$request->active;
        }else{
            $active='A';
        }
        $limit=8;
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $defaultDp = asset('default/default.png');
        $videoStoragePath  = asset(Storage::url("public/videos"));
        
            $users = DB::table('users as u')
                    ->where(function($query) use ($search_term) {
                        $query->where('username', 'like', '%'. $search_term . '%')
                            ->orWhere('fname', 'like', '%' . $search_term . '%')
                            ->orWhere('lname', 'like', '%' . $search_term . '%')
                            ->orWhere('email', 'like', '%' . $search_term . '%');
                    })
                    ->leftJoin('follow as f', function ($join) use ($user_id){
                        $join->on('u.user_id','=','f.follow_to')
                        ->where('f.follow_by',$user_id);
                    });
                    if($user_id > 0) {
                        $users = $users->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                            $join->on('u.user_id','=','bu.user_id');
                            $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                        });

                        $users = $users->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                            $join->on('u.user_id','=','bu2.blocked_by');
                            $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                        });

                        $users = $users->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                    }
                    $users= $users->select(DB::raw(" u.user_id,concat('@',u.username) as username,u.fname,u.lname,case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '".$defaultDp."' END as user_dp,ifnull(f.follow_id,0) as follow"))
                        ->where('u.active',1)
                        ->where('u.user_id','<>',$user_id)
                        ->orderBy('u.username', 'asc')
                        ->paginate($limit);

                //total users
                $totalusers = DB::table('users as u')
                    ->where(function($query) use ($search_term) {
                        $query->where('username', 'like', '%'. $search_term . '%')
                            ->orWhere('fname', 'like', '%' . $search_term . '%')
                            ->orWhere('lname', 'like', '%' . $search_term . '%')
                            ->orWhere('email', 'like', '%' . $search_term . '%');
                    })
                    ->leftJoin('follow as f', function ($join) use ($user_id){
                        $join->on('u.user_id','=','f.follow_to')
                        ->where('f.follow_by',$user_id);
                    });
                    if($user_id > 0) {
                        $totalusers = $totalusers->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                            $join->on('u.user_id','=','bu.user_id');
                            $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                        });

                        $totalusers = $totalusers->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                            $join->on('u.user_id','=','bu2.blocked_by');
                            $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                        });

                        $totalusers = $totalusers->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                    }
                    $totalusers= $totalusers->select(DB::raw(" u.user_id,concat('@',u.username) as username,u.fname,u.lname,case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '".$defaultDp."' END as user_dp,ifnull(f.follow_id,0) as follow"))
                        ->where('u.active',1)
                        ->where('u.user_id','<>',$user_id)
                        ->orderBy('u.username', 'asc')
                        ->count();
            $loadedUsers=count($users);

            $videos = DB::table('videos as v')
                    ->leftJoin('users as u','u.user_id','v.user_id')
                    ->where(function($query) use ($search_term) {
                        $query->where('title', 'like', '%'. $search_term . '%')
                            ->orWhere('description', 'like', '%' . $search_term . '%');
                    });
                    if($user_id > 0) {
                        $videos = $videos->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu.user_id');
                            $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                        });

                        $videos = $videos->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu2.blocked_by');
                            $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                        });

                        $videos = $videos->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                        $videos = $videos->leftJoin('follow as f', function ($join)use ($user_id){
                            $join->on('v.user_id','=','f.follow_to');
                            $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                        });
                        $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                        
                    }else{
                        $videos= $videos->where('v.privacy',0);
                    }
                    
                    $videos=$videos->select(DB::raw("v.video_id,v.video,v.user_id,v.description,v.title,concat('@',u.username) as username,concat('".$videoStoragePath."/',u.user_id,'/thumb/',v.thumb) as thumb"))
                    ->where('v.active',1)
                    ->where('v.deleted',0)
                    ->where('u.active',1)
                    ->where('u.user_id','<>',$user_id)
                    ->where('v.flag',0)
                    ->orderBy('v.title', 'asc')
                    ->paginate($limit);

                //total videos
                $totalVideos = DB::table('videos as v')
                    ->leftJoin('users as u','u.user_id','v.user_id')
                    ->where(function($query) use ($search_term) {
                        $query->where('title', 'like', '%'. $search_term . '%')
                            ->orWhere('description', 'like', '%' . $search_term . '%');
                    });
                    if($user_id > 0) {
                        $totalVideos = $totalVideos->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu.user_id');
                            $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                        });

                        $totalVideos = $totalVideos->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu2.blocked_by');
                            $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                        });

                        $totalVideos = $totalVideos->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                        $totalVideos = $totalVideos->leftJoin('follow as f', function ($join)use ($user_id){
                            $join->on('v.user_id','=','f.follow_to');
                            $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                        });
                        $totalVideos = $totalVideos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                        
                    }else{
                        $totalVideos= $totalVideos->where('v.privacy',0);
                    }
                    
                    $totalVideos=$totalVideos->select(DB::raw("v.video_id,v.user_id,v.description,v.title,concat('@',u.username) as username,concat('".$videoStoragePath."/',u.user_id,'/thumb/',v.thumb) as thumb"))
                        ->where('v.active',1)
                        ->where('v.deleted',0)
                        ->where('u.active',1)
                        ->where('u.user_id','<>',$user_id)
                        ->where('v.flag',0)
                        ->orderBy('v.title', 'asc')
                        ->count();
          
                        $loadedVideos=count($videos);

            $videoTags = DB::table('videos as v')
                    ->leftJoin('users as u','u.user_id','v.user_id')
                    ->where(function($query) use ($search_term) {
                        $query->where('tags', 'like', '%' . $search_term . '%');
                    });
                    if($user_id > 0) {
                        $videoTags = $videoTags->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu.user_id');
                            $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                        });

                        $videoTags = $videoTags->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu2.blocked_by');
                            $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                        });

                        $videoTags = $videoTags->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                        $videoTags = $videoTags->leftJoin('follow as f', function ($join)use ($user_id){
                            $join->on('v.user_id','=','f.follow_to');
                            $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                        });
                        $videoTags = $videoTags->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                        
                    }else{
                        $videoTags= $videoTags->where('v.privacy',0);
                    }
                    
                $videoTags=$videoTags->select('v.tags')
                        ->where('v.active',1)
                        ->where('v.deleted',0)
                        ->where('u.active',1)
                        ->where('u.user_id','<>',$user_id)
                        ->where('v.flag',0)
                        ->where('v.tags','!=','')
                        ->orderBy('v.title', 'asc')
                        ->paginate($limit);

                //total video tags

                $totalVideoTags = DB::table('videos as v')
                    ->leftJoin('users as u','u.user_id','v.user_id')
                    ->where(function($query) use ($search_term) {
                        $query->where('tags', 'like', '%' . $search_term . '%');
                    });
                    if($user_id > 0) {
                        $totalVideoTags = $totalVideoTags->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu.user_id');
                            $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                        });

                        $totalVideoTags = $totalVideoTags->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                            $join->on('v.user_id','=','bu2.blocked_by');
                            $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                        });

                        $totalVideoTags = $totalVideoTags->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                        $totalVideoTags = $totalVideoTags->leftJoin('follow as f', function ($join)use ($user_id){
                            $join->on('v.user_id','=','f.follow_to');
                            $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                        });
                        $totalVideoTags = $totalVideoTags->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                        
                    }else{
                        $totalVideoTags= $totalVideoTags->where('v.privacy',0);
                    }
                    
                $totalVideoTags=$totalVideoTags->select('v.tags')
                        ->where('v.active',1)
                        ->where('v.deleted',0)
                        ->where('u.active',1)
                        ->where('u.user_id','<>',$user_id)
                        ->where('v.flag',0)
                        ->where('v.tags','!=','')
                        ->orderBy('v.title', 'asc')
                        ->count();
                $loadedVideoTags=count($videoTags);
                // dd($loadedVideoTags);
                $hashTags=[];

                foreach($videoTags as $t){
                    array_push($hashTags,$t->tags);
                }
        //ajax data load
                if(isset($request->page) && isset($request->type)){
                    $html='';
                    $hashTags=[];
                    if($request->type=='T'){
                        if(count($videoTags)>0){
                            foreach($videoTags as $t){ 
                                $tagArr= explode(' ',$t->tags);
                                if(count($tagArr)>0){
                                    foreach($tagArr as $val){
                                        if($val!=""){
                                            if(strpos($val,$search_term)  !== false){ 
                                                $html.='<div class="col-md-3 col-12">';
                                                $html.='<div class="card text-white bg-dark p-0 mb-3" style="max-width: 18rem;">';
                                                $html.='<div class="card-body p-1 text-center">';
                                                $html.='<h5 class="card-title m-1">#'.$val.'</h5>';
                                                $html.='</div>';
                                                $html.='</div>';
                                                $html.='</div>';
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if($request->type=='P'){
                        if(count($users)>0){
                            foreach($users as $user){ 
                                $html.='<div class="col-lg-3 col-md-6 col-12">';
                                $html.='<div class="card mb-4" >';
                                $html.='<img class="card-img-top" src="'.$user->user_dp.'" alt="Card image cap">';
                                $html.='<div class="card-body text-center">';
                                $html.='<h4 class="card-title mb-0">'.ucfirst($user->fname) .' '. ucfirst($user->lname).'</h4>';
                                $html.='<p class="card-text mb-1">'.strtolower($user->username).'</p>';
                                        
                                $html.='<span class="follow_btn" data-id="'.$user->user_id.'" style="'.Functions::getTopbarColor().'">';
                                            if($user->follow>0){
                                                $html.='Unfollow';
                                            }else{
                                                $html.='Follow';
                                            }
                                $html.='</span>';
                                $html.='</div>';
                                $html.='</div>';
                                $html.='</div>';
                            } 
                        }
                    }

                    if($request->type=='V'){
                        $count=($request->page-1) * $limit;
                        if(count($videos)>0){
                            foreach($videos as $video){ 
                                $html.='<div class="col-lg-3 col-md-6 col-12">';
                                $html.='<div class="card mb-4 pt-0" >';
                                $html.='<div class="video-thumb">';
                                // $html.='<img class="card-img-top" src="'.$video->thumb.'" alt="Card image cap">';
                                $html.='<video muted="muted" id="video_'.$count.'" data-bs-toggle="modal" data-bs-target="#LeukeModal"  
                                        onmouseover="hoverVideo('.$count.')" onmouseout="hideVideo('.$count.')" class="img-responsive" style="height:100%;border-radius: 8px;background: #000;"
                                        loop preload="none" onclick="modelbox_open(\''.asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )).'\', '.$video->video_id .', video_'.$count.')"
                                        poster="'. $video->thumb .'">
                                        <source src="'. asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )) .'" type="video/mp4">
                                    </video>';
                                $html.='</div>';
                                $html.='<div class="card-body">';
                                $html.='<h5 class="card-title mb-0">'.strtolower($video->username) .'</h5>';
                                $html.='<p class="card-text mb-1">'.(strlen($video->description) > 30 ) ? mb_substr($video->description, 0, 30).'...' : $video->description.'</p>';
                                
                                $html.='</div>';
                                $html.='</div>';
                                $html.='</div>';
                                $count++;
                            } 
                        }
                    }

                    return response()->json(['html'=> $html,'totalVideoTags'=>$totalVideoTags,'loadedVideoTags'=>$loadedVideoTags,'totalPeople'=> $totalusers,'loadedPeople'=> $loadedUsers,'totalVideos'=> $totalVideos,'loadedVideos'=> $loadedVideos]);
                }
                return view('web.searchResult', ['active'=> $active,'videos' => $videos, 'hashTags' => $hashTags,'users'=>$users,'totalVideoTags'=>$totalVideoTags,'totalPeople'=> $totalusers,'totalVideos'=> $totalVideos,'search'=>$search_term,'totalVideoTags'=>$totalVideoTags,'loadedVideoTags'=>$loadedVideoTags,'totalPeople'=> $totalusers,'loadedPeople'=> $loadedUsers,'totalVideos'=> $totalVideos,'loadedVideos'=> $loadedVideos ]);
    }

    public function searchTagVideos(Request $request,$val){
        // dd($val);
        $search_term = $val;
     
        if (Auth::guard('web')->check()) {
            $user_id= $this->authUser->user_id;
        }else{
            $user_id=0;
        }

        $videoStoragePath  = asset(Storage::url("public/videos"));
        $limit=8;
        $videos = DB::table('videos as v')
                ->leftJoin('users as u','u.user_id','v.user_id')
                ->where(function($query) use ($search_term) {
                    $query->where('tags', 'like', '%' . $search_term . '%');
                });
                if($user_id > 0) {
                    $videos = $videos->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                        $join->on('v.user_id','=','bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                    });

                    $videos = $videos->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                        $join->on('v.user_id','=','bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                    });

                    $videos = $videos->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                    $videos = $videos->leftJoin('follow as f', function ($join)use ($user_id){
                        $join->on('v.user_id','=','f.follow_to');
                        $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                    });
                    $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                    
                }else{
                    $videos= $videos->where('v.privacy',0);
                }
                
                $videos=$videos->select(DB::raw("v.video_id,v.video,v.user_id,v.description,v.title,concat('@',u.username) as username,concat('".$videoStoragePath."/',u.user_id,'/thumb/',v.thumb) as thumb"))
                ->where('v.active',1)
                ->where('v.deleted',0)
                ->where('u.active',1)
                ->where('v.flag',0)
                ->orderBy('v.title', 'asc')
                ->paginate($limit);

            //total videos
            $totalVideos = DB::table('videos as v')
                ->leftJoin('users as u','u.user_id','v.user_id')
                ->where(function($query) use ($search_term) {
                    $query->where('tags', 'like', '%'. $search_term . '%');
                });
                if($user_id > 0) {
                    $totalVideos = $totalVideos->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                        $join->on('v.user_id','=','bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                    });

                    $totalVideos = $totalVideos->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                        $join->on('v.user_id','=','bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                    });

                    $totalVideos = $totalVideos->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                    $totalVideos = $totalVideos->leftJoin('follow as f', function ($join)use ($user_id){
                        $join->on('v.user_id','=','f.follow_to');
                        $join->whereRaw(DB::raw(" (  f.follow_by=".$user_id." )" ));
                    });
                    $totalVideos = $totalVideos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
                    
                }else{
                    $totalVideos= $totalVideos->where('v.privacy',0);
                }
                
                $totalVideos=$totalVideos->select(DB::raw("v.video_id,v.user_id,v.description,v.title,concat('@',u.username) as username,concat('".$videoStoragePath."/',u.user_id,'/thumb/',v.thumb) as thumb"))
                    ->where('v.active',1)
                    ->where('v.deleted',0)
                    ->where('u.active',1)
                    ->where('v.flag',0)
                    ->orderBy('v.title', 'asc')
                    ->count();

                $loadedVideos=count($videos);
// dd($totalVideos);
                if(isset($request->page)){
                    $html='';
                    $count=($request->page-1) * $limit;
                    if(count($videos)>0){
                        foreach($videos as $video){ 
                            $html.='<div class="col-lg-3 col-md-6 col-12">';
                            $html.='<div class="card mb-4 pt-0" >';
                            $html.='<div class="video-thumb">';
                            // $html.='<img class="card-img-top" src="'.$video->thumb.'" alt="Card image cap">';
                            $html.='<video muted="muted" id="video_'.$count.'" data-bs-toggle="modal" data-bs-target="#LeukeModal"  
                                    onmouseover="hoverVideo('.$count.')" onmouseout="hideVideo('.$count.')" class="img-responsive" style="height:100%;border-radius: 8px;background: #000;"
                                    loop preload="none" onclick="modelbox_open(\''.asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )).'\', '.$video->video_id .', video_'.$count.')"
                                    poster="'. $video->thumb .'">
                                    <source src="'. asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )) .'" type="video/mp4">
                                </video>';
                            $html.='</div>';
                            $html.='<div class="card-body">';
                            $html.='<h5 class="card-title mb-0">'.strtolower($video->username) .'</h5>';
                            $html.='<p class="card-text mb-1">'.(strlen($video->description) > 27 ) ? mb_substr($video->description, 0, 25).'...' : $video->description.'</p>';
                            
                            $html.='</div>';
                            $html.='</div>';
                            $html.='</div>';
                            $count++;
                        } 
                    }
                    return response()->json(['html'=> $html,'totalVideos'=> $totalVideos,'loadedVideos'=> $loadedVideos]);
                }
                return view('web.tagVideosResult', ['videos'=> $videos,'search'=>$search_term,'totalVideos'=> $totalVideos,'loadedVideos'=> $loadedVideos ]);
    }

}