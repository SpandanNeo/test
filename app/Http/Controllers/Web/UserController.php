<?php
namespace App\Http\Controllers\Web;

use Auth;
use Mail;
use Route;
use App\User;
use stdClass;
use Exception;
use FFMpeg as FFMpeg;
use App\Mail\SendMail;
use App\Events\MyEvent;
use FFProbe as FFProbe;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use FFMpeg\Format\Video\X264;
use Owenoj\LaravelGetId3\GetId3;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\DB;
use \Sightengine\SightengineClient;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Jobs\ConvertVideoForStreaming;
use FFMpeg\Filters\Video\VideoFilters;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class UserController extends WebBaseController
{
    private $ffmpeg;
    private $ffprobe;
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
        parent::__construct();
    }

    public function editUserProfile($userId)
    {
        $user = DB::table('users')->where('user_id', intval($userId))->first();
        $authUserId = $this->authUser->user_id;

        if (empty($user) || $userId != $authUserId) {
            return redirect()->route('web.home');
        }

        return view('web.auth.editUser', ['user' => $user]);
    }

    public function updateUserProfile($userId, Request $request)
    {
        $user = User::find(intval($userId));

        if (empty($user) || $user->user_id != $this->authUser->user_id) {
            Auth::guard('web')->logout();
        }
        $before_date = date('Y-m-d', strtotime('-13 years'));
        $rules = [
            'username' => ['required', 'string', 'max:255','regex:/^[0-9A-Za-z.\-_]*$/'],
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $userId . ',user_id'],
            'mobile' => ['nullable','regex:/^([0-9\s\-\+\(\)]*)$/','min:10'],
            // 'gender' => ['required', 'string', 'max:255'],
            'dob' => ['nullable', 'before_or_equal:' . $before_date],
            // 'image' => ['nullable', 'mimes:jgp,png,mpeg'],
        ];

        $messages = [
            'fname.required' => 'You cant leave First name field empty',
            'lname.required' => 'You cant leave Last name field empty',
            'email.required'            => 'You cant leave User name field empty',
            'email.email'            => 'You must enter an email address',
            'email.unique'            => 'The email must be unique',
            'password.required'         => 'You cant leave Password field empty',
            'password.min'              => 'Password has to be 6 chars long'
        ];
        $this->validate($request, $rules, $messages);
        
        $user->fname = strip_tags($request->get('fname'));
        $user->lname = strip_tags($request->get('lname'));
        $user->username = strip_tags($request->get('username'));
        $user->gender = $request->get('gender');
        $user->mobile = !empty($request->get('mobile')) ? $request->get('mobile') : '';
        $user->dob = $request->get('dob');
        $user->email = $request->get('email');
        $user->login_type = 'O';
        $user->save();
     
        if ($request->hasFile('profile_pic')) {
            try {
                $image_file = $request->file('profile_pic');

                if($image_file->isvalid()) {
                    $functions = new Functions();
                
                $path = 'public/profile_pic/'.$user->user_id;
                
                $filenametostore = request()->file('profile_pic')->store($path);  
                Storage::setVisibility($filenametostore, 'public');
                $fileArray = explode('/',$filenametostore);  
                $fileName = array_pop($fileArray); 
                // dd(asset(Storage::url('public/profile_pic/'.$user->user_id.'/'.$fileName)));
                $functions->_cropImage($image_file,300,300,0,0,$path.'/small',$fileName);
                // $functions->createThumb(asset(Storage::url('public/profile_pic/'.$user->user_id.'/'.$fileName)),300,300,$path.'/small',$fileName);
                    
                // dd(66);
                $file_path = asset(Storage::url('public/profile_pic/'.$user->user_id."/".$fileName));
               
                $small_file_path = asset(Storage::url('public/profile_pic/'.$user->user_id."/small/".$fileName));
                if($file_path==""){
                    $file_path=asset(config('app.profile_path')).'default-user.png';
                }
                if($small_file_path==""){
                    $small_file_path=asset(config('app.profile_path')).'default-user.png';
                }
                
                $data =array(
                    'user_id'       => $user->user_id,
                    'image'         => $fileName				
                ); 
                
                DB::table('users')
                ->where('user_id', $user->user_id)
                ->update(['user_dp'=>$fileName]);
                }
            } Catch(Exception $ex) {
                dd($ex->getMessage());
                return redirect()->back();
            }
        }

        Session :: flash('success', 'Update successfull.');
        return redirect()->back();
    }

    public function userProfile(Request $request,$userId)
    {
        if (!Auth::guard('web')->check()) {
            return redirect()->route('web.login');
        }
        $user = User::find(intval($userId));

        if ($user->active==0) {
            return redirect()->route('web.home');
        }
        $followed = false;
        $blocked = false;
        $blockedTo = false;
        $authUserId = $this->authUser->user_id ?? 0;

        $notify_ids[] = explode(',', request()->get('notify_ids'));

        if (!empty($notify_ids)) {
            DB::table('notifications')
                ->whereIn('notify_id', $notify_ids)
                ->update(['read' => 1]);
        }
        
        if (empty($user)) {
            return redirect()->route('web.home');
        }

        $canFollow = $authUserId != $userId;
        $followed = DB::table('follow')
                    ->where(['follow_to' => $userId, 'follow_by' => $authUserId])->exists();
        $blocked = DB::table('blocked_users')
                    ->where(['user_id' => $userId, 'blocked_by' => $authUserId])->exists();

        $blockedTo = DB::table('blocked_users')
                    ->where(['blocked_by' => $userId, 'user_id' => $authUserId])->exists();
                    $userInfo = DB::table('users as u')
                    ->leftJoin('videos as v', function($join) {
                        $join->on('v.user_id', 'u.user_id')
                        ->where('v.active',1)
                        ->where('v.flag',0)
                        ->where('v.deleted',0)
                        ->where('v.enabled', 1);
                    })
                    // ->leftJoin('user_verify as uv','uv.user_id','=','v.user_id')
                    ->leftJoin('user_verify as uv', function ($join){
                        $join->on('uv.user_id','=','v.user_id')
                        ->where('uv.verified','A');
                    })
                    ->select('u.user_id', 'u.username', 'u.user_dp', 'u.login_type', 'u.fname', 'u.lname','uv.verified as verified')
                    ->selectRaw('count(v.video_id) as total_videos')
                    ->selectRaw('sum(v.total_likes) as total_likes')
                    ->groupBy('v.user_id', 'u.user_id', 'u.username', 'u.user_dp', 'u.login_type')
                    ->where('u.user_id', $userId)
                    
                    ->first();
                    $followers = Functions::userFollowersCount($userId);
                $following = Functions::userFollowingCount($userId);
        // $followers = DB::table('follow')->where('follow_to', $userId)->count();
        // $following = DB::table('follow')->where('follow_by', $userId)->count();
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
        $videos = DB::table('videos as v');
            if($canFollow){
                $videos = $videos->leftJoin('follow as f', function ($join)use ($authUserId){
                    $join->on('v.user_id','=','f.follow_to');
                    $join->whereRaw(DB::raw(" (  f.follow_by=".$authUserId." )" ));
                });
                $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
            }
            $videos=$videos->select('v.user_id', 'v.video_id', 'v.video', 'v.title','v.description', 'v.thumb', 'v.gif', 'v.total_likes', 'v.total_comments', 'v.total_views')
                    ->orderBy('v.video_id','desc')
                    // ->orderBy('total_likes')->orderBy('total_views')
                    ->where(['v.user_id' => $userId, 'v.active' => 1, 'v.enabled' => 1])
                    ->skip($offset)->take($limit)->get();
                    // ->paginate($limit);
            $videosCount = DB :: table('videos as v');
            if($canFollow){
                $videosCount = $videosCount->leftJoin('follow as f', function ($join)use ($authUserId){
                    $join->on('v.user_id','=','f.follow_to');
                    $join->whereRaw(DB::raw(" (  f.follow_by=".$authUserId." )" ));
                });
                $videosCount = $videosCount->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END  '));
            }
            $videosCount=$videosCount->orderBy('v.video_id','desc')
                    ->where(['v.user_id' => $userId, 'v.active' => 1, 'v.enabled' => 1])
                    ->count();
        if($request->page){
            $count=$offset;
            $x=$offset+1;
            $html='';
            foreach($videos as $video){
                $html.='<div class="col-lg-3 col-md-6 col-12 video p-2" style="text-align:center;">';
                $html.='<div style="box-shadow: 0px 2px 8px #ccc;border-radius: 5px;padding:10px;">';
                $html.='<div class="row container_top_header">';
                $html.='<div class="col-md-3 col-3 userdp_div">';
                      
                        if($userInfo->user_dp!=""){
                            if(strpos($userInfo->user_dp,'facebook.com') !== false || strpos($userInfo->user_dp,'fbsbx.com') !== false || strpos($userInfo->user_dp,'googleusercontent.com') !== false){ 
                                $u_dp=$userInfo->user_dp;
                            }else{
                             $u_dp=asset(Storage::url('public/profile_pic').'/'.$userInfo->user_id.'/small/'.$userInfo->user_dp) ;
                      
                            }
                        }else{ 
                                $u_dp= asset('default/default.png');
                            } 
                $html.='<img class="img-fluid" src="'.$u_dp.'">';
                $html.='</div>';
                $html .='<div class="col-md-8 col-8 text-left pl-0">';
                $html.= '<div class="row">';
                $html.= '<div class="col-md-11 col-11"  onclick="openModal(\'video_'.$count.'\')">';
                $html .='<h5 class="username_div">'.$userInfo->fname.' '.$userInfo->lname.'</h5>';
                $html .= '</div>';
                $html .= '<div class="col-md-1 col-1 p-0 text-center">';
                if (auth()->guard('web')->user()->user_id == $userInfo->user_id){
                    $html .='<div class="dropdown">';
                    $html .='<i class="fa fa-ellipsis-v dropdown-toggle" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="margin-top: 4px;"></i>';
                    $html .='<div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
                    $html .='<a class="dropdown-item" href="'.route('web.video-info-update',['id' => $video->video_id]).'">Edit</a>';
                    $html .='</div>';
                    $html .='</div>';
                }
                $html .='</div>';
                $html .='<div class="col-md-11 col-11" onclick="openModal(\'video_'.$count.'\')">';
                $html .= '<div class="title_div">'.((strlen($video->description) > 20 ) ? mb_substr($video->description, 0, 20).'...' : $video->description).'</div>';
                $html .='</div>';
                $html .='<div class="col-md-1 col-1 p-0">';
                if (auth()->guard('web')->user()->user_id == $userInfo->user_id){
                $html .='<input type="checkbox" name="chk[]" value="'.$video->video_id .'" class="checkbox_'.$x .'">';
                }
                $html .='</div>';
                $html .='</div>';
                $html .='</div>';
                // $html.='<div class="col-md-1 col-1">';
                //     if (auth()->guard('web')->user()->user_id == $userInfo->user_id){
                //         $html.='<input type="checkbox" value="'.$video->video_id.'" class="checkbox_'. $x .'">';
                //     }
                // $html.='</div>';
                $html.='</div>';
                $html.='<div class="video-container">';
                $html.='<video muted="muted" id="video_'.$count.'" data-bs-toggle="modal" data-bs-target="#LeukeModal" 
                            onmouseover="hoverVideo('.$count.')" onmouseout="hideVideo('.$count.')" class="" style="width:100%;height:100%;background: #000;border-radius: 8px;" 
                            loop preload="none" onclick="modelbox_open(\''.asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )).'\', '.$video->video_id .')"
                            poster="'. asset(Storage::url('public/videos/' . $video->user_id . '/thumb/' . $video->thumb )) .'">
                            <source src="'. asset(Storage::url('public/videos/' . $video->user_id . '/' . $video->video )) .'" type="video/mp4">
                            </video>';
                $html.='</div>';
                $html.='<div class="user_name">';
                $html.='<div>@'.$userInfo->username.'</div>';
                $html.='<div class="video_view" id="video_view_'.$video->video_id.'" style="'.Functions::getTopbarColor().'"><i class="fa fa-eye"></i> '.$video->total_views.'</div>';
                $html.='</div>';
              
                $html.='<div class="views row m-1" onclick="openModal(\'video_'.$count.'\')">
                        <div class="col-md-6 col-6 text-center" id="video_like_'.$video->video_id.'">
                            <i class="fa fa-heart-o" aria-hidden="true"></i> '. $video->total_likes .'
                        </div>
                        <div class="col-md-6 col-6 text-center">
                            <i class="fa fa-comment-o" aria-hidden="true"></i> '. $video->total_comments .'
                        </div>
                    </div>
                </div>
            </div>';
            $count++;
            $x++;
            }
            return response()->json(['html'=> $html,'videosCount'=>$videosCount,'userInfo' => $userInfo, 'videos' => $videos, 'canFollow' => $canFollow, 'followed' => $followed, 'blocked' => $blocked,
            'followers' => $followers,'following' =>$following,'blockedTo'=>$blockedTo ]);
        }
        return view('web.userProfile', ['videosCount'=>$videosCount ,'userInfo' => $userInfo, 'videos' => $videos, 'canFollow' => $canFollow, 'followed' => $followed, 'blocked' => $blocked,
                    'followers' => $followers,'following' =>$following,'blockedTo'=>$blockedTo ]);
    }

    public function blockUnblock($userId, Request $request)   
    {
        $success = false;
        $block = false;
        $dt = date('Y-m-d h:i:s');
        $userExists = DB::table('users')
                        ->where(['user_id' => intval($userId), 'active' => 1])
                        ->exists();
                        // dd($userExists);
        if ($userExists && $userId != $this->authUser->user_id) {
            $userBlocked = DB::table('blocked_users')
                                ->where(['user_id' => $userId, 'blocked_by' => $this->authUser->user_id])->exists();
            if ($userBlocked) {
                //user follow row gets deleted.
                DB::table('blocked_users')
                    ->where(['user_id' => $userId, 'blocked_by' => $this->authUser->user_id])->delete();
            
            } else {

                DB::table('follow')->where('follow_by', $this->authUser->user_id)->where('follow_to', $userId)->delete();
				DB::table('follow')->where('follow_to', $this->authUser->user_id)->where('follow_by', $userId)->delete();

                DB::table('blocked_users')
                ->insert([
                    'blocked_by' => $this->authUser->user_id,
                    'user_id' => $userId,
                    'blocked_on' => $dt,
                    ]);
                $block = true;
            }
            $success = true;
        } else {
            return response()->json(['success' => $success]);
        }
    
        return response()->json(['success' => $success, 'block' => $block]);
			
    }

    public function followUnfollowUser($userId, Request $request)   
    {
        $success = false;
        $follow = false;
        $dt = date('Y-m-d h:i:s');
        $userExists = DB::table('users')
                        ->where(['user_id' => intval($userId), 'active' => 1])
                        ->exists();
                        // dd($userExists);
        if ($userExists && $userId != $this->authUser->user_id) {
            $userFollowed = DB::table('follow')
                                ->where(['follow_to' => $userId, 'follow_by' => $this->authUser->user_id])->exists();
            if ($userFollowed) {
                //user follow row gets deleted.
                DB::table('follow')
                    ->where(['follow_to' => $userId, 'follow_by' => $this->authUser->user_id])->delete();
                //change the notification status of user followed to read.
                DB::table('notifications')
                    ->where(['notify_by' => $this->authUser->user_id, 'notify_to' => $userId, 'type' => 'F'])
                    ->update(['read' => 1]);
            } else {
                DB::table('follow')
                ->insert([
                    'follow_by' => $this->authUser->user_id,
                    'follow_to' => $userId,
                    'follow_on' => $dt,
                    ]);
                $follow = true;
            }
            $success = true;
        } else {
            return response()->json(['success' => $success]);
        }

        if ($follow) {
            $msg = 'Follow you.';
            $type = 'F';
        } else {
            $msg = 'Unfollow you.';
            $type = 'UF';
        }

        $profileImg = Functions::getProfileImageUrl($this->authUser);
        $checkUserVerified =Functions::checkUserVerified();
        // $profileImg = '';
        // if(!empty($authUser->user_dp)) {
        //     if($authUser->login_type == 'O') {
        //        $profileImg =  asset('storage/profile_pic/' . $authUser->user_id) . '/small/' . $authUser->user_dp;
        //     } else {
        //        $profileImg = $authUser->user_dp; 
        //     }
        // } else {
        //     $profileImg = asset('storage/profile_pic/default.png');
        // }

        $notify_ids = DB::table('notifications')
            ->insertGetId([
                'notify_by' =>  $this->authUser->user_id,
                'notify_to' => $userId,
                'message' => $msg,
                'video_id' => 0,
                'type' => $type,
                'added_on' => $dt,
            ]);
        /* $notify = [
            'notifyUserName' => $this->authUser->username,
            'notifyUserId' => $this->authUser->user_id,
            // 'follow' => $follow,
            'type' => $type,
            'notify_to' => $userId,
            'video_id' => 0,
            'msg' => $msg,
            'date' => date('d.m.Y , H:i', strtotime($dt)),
            'profileImg' => $profileImg,
        ]; */

        $notifications = new stdClass();
        $notifications->profileImg = $profileImg;
        $notifications->user_id = $this->authUser->user_id;
        $notifications->username = $this->authUser->username;
        $notifications->type = $type;
        $notifications->video_id = 0;
        $notifications->added_on = $dt;
        $notifications->message = $msg;
        $notifications->notify_to = $userId;
        $notifications->notify_total = 0;
        $notifications->notify_ids = $notify_ids;
        $notifications->read = 0;
        $notifications->verified =$checkUserVerified;

        $notifications = collect([$notifications]);

        $notifyHtml = view('partials.notification',  ['notifications' => $notifications])->render();

        // event(new MyEvent($notify, $userId));
        event(new MyEvent($notifyHtml, $userId));
        
        return response()->json(['success' => $success, 'follow' => $follow]);
			
    }
    
    public function userNotifications()
    {
        $authUserId = $this->authUser->user_id;

        $user_id =$authUserId;
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();

        $notifications = DB::table('notifications as n')
        ->join('users as u', 'u.user_id', 'n.notify_by')
        ->select('n.*', 'u.user_id', 'u.username', 'u.user_dp', 'u.login_type', DB::raw("GROUP_CONCAT(n.notify_id SEPARATOR ',') as notify_ids,uv.verified"),
                DB::raw("DATE_FORMAT(n.added_on, '%Y-%m-%d') as date"))
        // ->leftJoin('user_verify as uv','uv.user_id','u.user_id')
        ->leftJoin('user_verify as uv', function ($join){
            $join->on('uv.user_id','=','u.user_id')
            ->where('uv.verified','A');
        })
        ->selectRaw('count(n.type) as notify_total');
        if($user_id > 0) {
            $notifications = $notifications->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                $join->on('n.notify_by','=','bu.user_id');
                $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
            });

            $notifications = $notifications->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                $join->on('n.notify_by','=','bu2.blocked_by');
                $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
            });

            $notifications = $notifications->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
           
        }

        $notifications=$notifications->where([['n.notify_to',  $authUserId ?? 0]])
        ->whereNotIn('n.type', ['UF', 'UL'])
        ->groupBy('date', 'n.type')
        ->orderBy('n.added_on','desc')
        ->paginate(6);
        // dd($notifications);
        
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();

        return view('web.notifications',['notifications' => $notifications]);
    }

    public function notificationStatus()
    {
        $notificationHtml = '';
        $success = false;
        $type = request()->get('type');

        if (!empty($type)) {
            DB::table('notifications')->where('notify_to', $this->authUser->user_id)->update(['read' => 1]);
        } else {
            $notifications = $this->commonNotifications();
            $notificationHtml = view('partials.notification', ['notifications' => $notifications])->render();
        }

        $success = true;

        return response()->json(['success' => $success, 'notificationHtml' => $notificationHtml]);

    }

    public function uploadVideo()
    {
        return view('web.uploadVideo');
    }

    public function insertVideo(Request $request)
    {
        ini_set('post_max_size ','10240M');
        ini_set('upload_max_filesize','10240M');
        // dd($request->all());
        $rules = [ 
            // 'description' => 'required',
            'file'          => 'required|mimes:mp4,mov,ogg,qt',  
        ];
        $messages = [ 
            // 'description.required'   => 'Description is required',
            'file.required'   => 'Video is required',
        ];
        $this->validate($request, $rules, $messages);

            $format = new X264('libmp3lame', 'libx264');
            // $format = new X264('aac', 'libx264');
            $storage_path=config('app.filesystem_driver');
            $sound_id=0;
            $time_folder=time();
            $videoPath = 'public/videos/'. $this->authUser->user_id;
            $audioPath = 'public/sounds/'. $this->authUser->user_id;
            $videoFileName=$this->CleanFileNameMp4($request->file('file')->getClientOriginalName());
            $request->file->storeAs('public/temp' , $time_folder.'.mp4');
            Storage::setVisibility('public/temp/'. $time_folder.'.mp4', 'public');
            $waterMarkPath="";
            $watermark = DB::table('settings')->first();
            if($watermark){
                $watermark_img = $watermark->watermark;
                if($watermark_img!="") {
                    $watermarkImg=$watermark_img;
                    $waterMarkPath=asset(Storage::url('public/uploads/logos/small_'.$watermarkImg));
                }
            }
            Storage::disk('local')->makeDirectory('public/videos/'.$this->authUser->user_id.'/'.$time_folder);
            Storage::disk('local')->makeDirectory('public/videos/'.$this->authUser->user_id.'/thumb');
            Storage::disk('local')->makeDirectory('public/sounds/'.$this->authUser->user_id);

            $uploadStatus=Functions::ffmpegUpload(asset(Storage::url('public/temp/' .$time_folder.'.mp4')),storage_path('app/public/videos/'.$this->authUser->user_id.'/'.$time_folder.'/'.$videoFileName),'',storage_path('app/public/sounds/'.$this->authUser->user_id.'/'.$time_folder.'.mp3'),storage_path('app/'.$videoPath.'/thumb/'.$time_folder.'.jpg'),$waterMarkPath,$storage_path,$videoPath.'/'.$time_folder,$videoFileName,$audioPath,$time_folder.'.mp3',$videoPath.'/thumb',$time_folder.'.jpg');
            if($uploadStatus['status']=='error'){
                return response()->json(["status" => "error", "msg"=>"A video without audio stream can not be uploaded."]);
            }else{
                // $ffmpeg = FFMpeg\FFMpeg::create();
                // $ffprobe = FFMpeg\FFProbe::create();
                $streamCount = $this->ffprobe->streams(asset(Storage::url($videoPath . '/' .$time_folder.'/'. $videoFileName)))->audios()->count();
                //                         $streamCount = $ffprobe->streams(asset(Storage::url($videoPath . '/' .$time_folder.'/'. $videoFileName)));
                // dd($streamCount);
                if ($streamCount > 0) {
                    $duration = $this->ffprobe
                            ->streams(storage_path('app/public/sounds/'.$this->authUser->user_id.'/'.$time_folder.'.mp3' ))                 
                            ->audios()
                            ->first()                  
                            ->get('duration');
                    // dd($duration);
                    $audio_duration=round($duration);

                    $track = new GetId3(new File(storage_path('app/public/sounds/'.$this->authUser->user_id.'/'.$time_folder.'.mp3' )));
                    
                    $title=$track->getTitle();
                    $album=$track->getAlbum();
                    $artist=$track->getArtist();
                    if($storage_path=='s3'){
                        unlink(storage_path('app/public/sounds/'.$this->authUser->user_id.'/'.$time_folder.'.mp3' ));
                    }

                        $audioData = array(
                        'user_id' => $this->authUser->user_id,
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

            $v_path=asset(Storage::url($videoPath.'/'. $time_folder.'/'.$videoFileName));

            $video_duration = $this->ffprobe
                        ->streams($v_path)
                        ->videos()                   
                        ->first()                  
                        ->get('duration');

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
                             // $imgName = storage_path('app/'.$videoPath.'/'."thumb_{$key}.jpg");
                             $imgName = asset(Storage::disk('local')->url($videoPath.'/'."thumb_{$key}.jpg")); 
                             $images[] = 'public/videos/'.$this->authUser->user_id.'/'."thumb_{$key}.jpg";
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
            Storage::delete('public/temp/'.$time_folder.'.mp4');
            $data = [
                'enabled' => 0,
                'user_id' => $this->authUser->user_id,
                'sound_id' => $sound_id,
                'duration' =>$video_duration,
                //'title' => implode(' ', explode(' ', $description, 3)),
                // 'description' => $description,
                'video' => $time_folder . '/' . $videoFileName,
                'thumb' => $time_folder . '.jpg',
                'created_at' => date('Y-m-d h:i:s')
            ];
            $flagged=0;
            if(count($nudity)>0){
              
                // $response = array("status" => "failed","msg"=>"Video is flagged by our system and its under moderation.");
                $flagged = 1;
                $data['flag']=1;
            }

                $file_path= $videoPath.'/'. $time_folder.'/'.$videoFileName;
                $c_path=  $this->getCleanFileName($time_folder.'/master.m3u8');
                
                $v_id=DB::table('videos')
                    ->insertGetId($data);

                $video = array(
                    'disk'          => $storage_path,
                    'original_name' => $request->file('file')->getClientOriginalName(),
                    'path'          => $file_path,
                    'c_path'        => $c_path,
                    'title'         => $request->title,
                    'video_id'      => $v_id,
                    'user_id'       => $this->authUser->user_id
                );
    
                ConvertVideoForStreaming::dispatch($video);
                
                if($flagged==0){
                    return response()->json(["status" => "success", "data" => $data,"v_id" => $v_id]);
                    // $response = array("status" => "success",'msg'=>'Video uploaded successfully' , 'video' => $videoFileUrl,'thumb' => $thumbFileName);   
                   }else{
                        return response()->json(["status" => "error", "msg"=>"Video is flagged by our system and its under moderation."]);
                   }
                return response()->json(["status" => "success", "data" => $data,"v_id" => $v_id]);
            // return redirect()->route('web.userProfile', $this->authUser->user_id);

    }

    private function getCleanFileName($filename){
        return preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename) . '.m3u8';
    }

    private function CleanFileNameMp4($filename){
        $fname= preg_replace('/\\.[^.\\s]{3,4}$/', '', $filename) . '.mp4';
        return str_replace(' ', '-', $fname);
    }

    public function videoInfoUpdate($id){
        $video_detail= DB::table('videos')->select('description','title','tags','privacy')->where('video_id',$id)->first();
        if(!$video_detail){
            $video_detail = (object) array('description' => '', 'title'=> '', 'tags'=>'','privacy'=>0);
        }
        return view('web.videoDetail', ['id' => $id, 'user_id' => $this->authUser->user_id, 'video_detail' => $video_detail]);
    }
    public function videoInfoSubmit(Request $request){
    //   dd($request->all());
        $rules = [ 
            'description' => 'required',
            // 'title' => 'required',
            // 'hashtag' => 'required'
        
        ];
        $messages = [ 
            'description.required'   => 'Description is required',
            // 'title.required'   => 'Title is required',
            // 'hashtag.required'   => 'Hashtag is required',
            
        ];
        $this->validate($request, $rules, $messages);
        
        $data = [
            'enabled' => 1,
            // 'title' => strip_tags($request->title),
            'description' => strip_tags($request->description),
            'privacy' => $request->privacy,
            'tags' => strip_tags(($request->tags) ? $request->tags : null),
            'updated_at' => date('Y-m-d h:i:s')
        ];

        DB::table('videos')
            ->where('video_id',$request->id)
            ->where('user_id',$this->authUser->user_id)
            ->update($data);
        return redirect()->route('web.userProfile', $this->authUser->user_id);

    }
    public function deleteVideo()
    {
        $success = false;
        $videoIds = request()->get('videos');

        foreach($videoIds as $key=>$v_id){

            $video_detail=DB::table('videos')
                ->where('user_id', $this->authUser->user_id)
                ->where('video_id', $v_id)
                ->first();
        
                $name=$video_detail->thumb;
                $f_name=explode('.',$name);

                $folder_name=$this->authUser->user_id.'/'.$f_name[0];
                $thumb_name=$this->authUser->user_id.'/thumb/'.$f_name[0].'.jpg';
                $gif_name=$this->authUser->user_id.'/gif/'.$f_name[0].'.gif';
               
                Storage::deleteDirectory("public/videos/" . $folder_name);
                Storage::delete("public/videos/" . $thumb_name);
               
        }
        

        DB::table('videos')
            ->where('user_id', $this->authUser->user_id)
            ->whereIn('video_id', $videoIds)
            ->delete();
            // ->update(['active' => 0, 'deleted' => 1]);
        $success = true;
        return response()->json(['success' => $success]);
    }

    public function leukeSearch()
    {
        $success = false;
        $result = [];
        $search_term = request()->get('term');
        if (Auth::guard('web')->check()) {
            $user_id= $this->authUser->user_id;
        }else{
            $user_id=0;
        }

        $users = DB::table('users as u')
                    ->where(function($query) use ($search_term) {
                        $query->where('username', 'like', '%'. $search_term . '%')
                            ->orWhere('fname', 'like', '%' . $search_term . '%')
                            ->orWhere('lname', 'like', '%' . $search_term . '%')
                            ->orWhere('email', 'like', '%' . $search_term . '%');
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
                    $users= $users->select('u.user_id', 'u.username', 'u.user_dp', 'u.login_type')
                    ->where('u.active',1)
                    ->orderBy('u.username', 'asc')
                    ->get();
        $videos = DB::table('videos as v')
                    ->leftJoin('users as u','u.user_id','v.user_id')
                    ->where(function($query) use ($search_term) {
                        $query->where('title', 'like', '%'. $search_term . '%')
                            ->orWhere('description', 'like', '%' . $search_term . '%')
                            ->orWhere('tags', 'like', '%' . $search_term . '%');
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
                    
            $videos=$videos->select('v.video_id', 'v.user_id','v.description', 'v.title', 'v.thumb')
                    ->where('v.active',1)
                    ->where('v.deleted',0)
                    ->where('u.active',1)
                    ->where('v.flag',0)
                    ->orderBy('v.title', 'asc')
                    ->get();

        if ($users->count() > 0) {
            foreach ($users as $user) {
                $row['label'] = $user->username;
                $row['imgSrc'] = Functions::getProfileImageUrl($user);
                $row['url'] = route('web.userProfile', $user->user_id);
                $result[] = $row;
            }
        }

        if ($videos->count() > 0) {
            foreach ($videos as $video) {
                $row['label'] = (strlen($video->description) > 40 ) ? mb_substr($video->description, 0, 40).'...' : $video->description;
                $row['imgSrc'] = Functions::getVideoThumbUrl($video);
                $row['url'] = route('web.home', ['videoId' => $video->video_id]);
                $result[] = $row;
            }
        }

//         $result['success'] = true;
// dd($result);
        return json_encode(['result' => $result]);
    }

    public function changePassword()
    {
        return view('web.changePassword');
    }

    public function updatePassword(Request $request)
    {
        $user = $this->authUser;
        $rules = [
            'old_password' => ['required', function($attribute, $value, $fail) use ($user) {
                                if (!Hash :: check($value, $user->password)) {
                                    $fail('Your old password does not match');
                                }                
                            }],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:old_password'],
        ];
        $messages = [
            'password.different' => 'The old and the new password must be different',
        ];
        $this->validate($request, $rules, $messages);
        
        $id=auth()->user()->user_id;
        DB::table('users')->where('user_id',$id)->update(['password'=> Hash::make($request->password)]);

        Auth::logoutOtherDevices($request->password);
        Session :: flash('success', 'Password update successfull');
        
        return back();
    }

    public function followersList($id,Request $request){
        if (Auth::guard('web')->check()) {
            $user_id= $this->authUser->user_id;
        }else{
            $user_id=0;
        }
        $followersList =  DB::table('follow as f')
                ->join('users as u', 'u.user_id', 'f.follow_by')
                // ->leftJoin('user_verify as uv', 'uv.user_id', 'c.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','f.follow_by')
                    ->where('uv.verified','A');
                })
                ->leftJoin('follow as f2', function ($join) use($id){
                    $join->on('u.user_id','=','f2.follow_to')
                    ->where('f2.follow_by', $this->authUser->user_id);
                });
                if($user_id > 0) {
                    $followersList = $followersList->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                        $join->on('u.user_id','=','bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                    });

                    $followersList = $followersList->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                        $join->on('u.user_id','=','bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                    });

                    $followersList = $followersList->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                }
                $followersList= $followersList->select('u.user_id', 'u.username', 'u.fname','u.lname', 'u.login_type', 'u.user_dp','uv.verified',DB::raw('ifnull(f2.follow_id,0) as follow'))
                ->where('f.follow_to', $id)
                ->orderBy('u.fname', 'asc')
                ->where('u.active',1)
                ->where('u.deleted',0)
                ->paginate(10);
                // ->get();
            $loadedFollow=count($followersList);
            $userInfo = DB::table('users as u')
                ->leftJoin('videos as v', function($join) {
                    $join->on('v.user_id', 'u.user_id');
                })
                // ->leftJoin('user_verify as uv','uv.user_id','=','v.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','v.user_id')
                    ->where('uv.verified','A');
                })
                ->select('u.user_id', 'u.username', 'u.user_dp', 'u.login_type', 'u.fname', 'u.lname','uv.verified as verified')
                ->selectRaw('count(v.video_id) as total_videos')
                ->selectRaw('sum(v.total_likes) as total_likes')
                ->groupBy('v.user_id', 'u.user_id', 'u.username', 'u.user_dp', 'u.login_type')
                ->where('u.user_id', $id)
                ->first();
                $followers = Functions::userFollowersCount($id);
                $following = Functions::userFollowingCount($id);
                $authUserId = $this->authUser->user_id ?? 0;
                $canFollow = $authUserId != $id;
                $followed = DB::table('follow')
                    ->where(['follow_to' => $id, 'follow_by' => $authUserId])->exists();
                $blocked = DB::table('blocked_users')
                    ->where(['user_id' => $id, 'blocked_by' => $authUserId])->exists();

                    if(isset($request->page)){
                        $html='';
                        if(count($followersList)>0){
                            foreach($followersList as $follower){
                                $html.='<div class="card">';
                                $html.='<div class="card-body">';
                                    if($follower->user_dp!=""){
                                        if(strpos($follower->user_dp,'facebook.com') !== false || strpos($follower->user_dp,'fbsbx.com') !== false || strpos($follower->user_dp,'googleusercontent.com') !== false){ 
                                            $u_dp=$follower->user_dp;
                                        }else{
                                            $u_dp=asset(Storage::url('public/profile_pic').'/'.$follower->user_id.'/small/'.$follower->user_dp) ;
                                            
                                        } 
                                    }else{ 
                                        $u_dp= asset('default/default.png');
                                    }
                                $html.='<img src="'.$u_dp.'" class="avatar_img float-left rounded-circle">';
                                $html.='<div class="message">';
                                $html.='<h5 class="card-title"><a href="'.route('web.userProfile', $follower->user_id).'">'. $follower->fname.' '.$follower->lname;
                                  if($follower->verified=='A'){ 
                                    $html.='<img src="'. asset('default/verified-icon-blue.png') .'" alt="" style="width:15px;height:15px;">';
                                     }
                                     $html.='</a></h5>';
                                     $html.='<h6 class="card-subtitle mb-2 text-muted"> @' . $follower->username .'</h6>';
                                     if($follower->user_id!= auth()->guard('web')->user()->user_id){
                                        $html.='<span class="follow_btn" data-id="'.$follower->user_id.'" style="'.Functions::getTopbarColor().'">';
                                            if($follower->follow>0){
                                                $html.="Unfollow";
                                            }else{
                                                $html.="Follow";
                                            }
                                            $html.='</span>';
                                     }                                      
                                     $html.='</div>';
                                     $html.='</div>
                                </div>';
                            }
                        }
                        return response()->json(['html'=> $html,'totalFollow'=>$followers,'loadedFollow'=>$loadedFollow ]);
                    }

        return view('web.followersList', ['totalFollow'=>$followers,'loadedFollow'=>$loadedFollow,'followersList' => $followersList,'userInfo'=>$userInfo,'followers' => $followers,'following'=>$following,'canFollow'=>$canFollow,'followed'=>$followed,'blocked'=>$blocked]);
        // dd($followers);    
    }
    
    public function followingList($id,Request $request){
        if (Auth::guard('web')->check()) {
            $user_id= $this->authUser->user_id;
        }else{
            $user_id=0;
        }
        $followingList =  DB::table('follow as f')
                ->join('users as u', 'u.user_id', 'f.follow_to')
                // ->leftJoin('user_verify as uv', 'uv.user_id', 'c.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','f.follow_to')
                    ->where('uv.verified','A');
                })
                ->leftJoin('follow as f2', function ($join) use($id){
                    $join->on('u.user_id','=','f2.follow_to')
                    ->where('f2.follow_by', $this->authUser->user_id);
                });
                if($user_id > 0) {
                    $followingList = $followingList->leftJoin('blocked_users as bu', function ($join)use ($user_id){
                        $join->on('u.user_id','=','bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=".$user_id." )" ));
                    });

                    $followingList = $followingList->leftJoin('blocked_users as bu2', function ($join)use ($user_id){
                        $join->on('u.user_id','=','bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=".$user_id." )" ));
                    });

                    $followingList = $followingList->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                }
                $followingList=$followingList->select('u.user_id', 'u.username', 'u.fname','u.lname', 'u.login_type', 'u.user_dp','uv.verified',DB::raw('ifnull(f2.follow_id,0) as follow'))
                ->where('f.follow_by', $id)
                ->where('u.active',1)
                ->where('u.deleted',0)
                ->orderBy('u.fname', 'asc')
                ->paginate(5);
                // ->get();
            $loadedFollow=count($followingList);
            $userInfo = DB::table('users as u')
                ->leftJoin('videos as v', function($join) {
                    $join->on('v.user_id', 'u.user_id');
                })
                // ->leftJoin('user_verify as uv','uv.user_id','=','v.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','v.user_id')
                    ->where('uv.verified','A');
                })
                ->select('u.user_id', 'u.username', 'u.user_dp', 'u.login_type', 'u.fname', 'u.lname','uv.verified as verified')
                ->selectRaw('count(v.video_id) as total_videos')
                ->selectRaw('sum(v.total_likes) as total_likes')
                ->groupBy('v.user_id', 'u.user_id', 'u.username', 'u.user_dp', 'u.login_type')
                ->where('u.user_id', $id)
                ->first();
                $followers = Functions::userFollowersCount($id);
                $following = Functions::userFollowingCount($id);
         
            $authUserId = $this->authUser->user_id ?? 0;
            $canFollow = $authUserId != $id;
            $followed = DB::table('follow')
                    ->where(['follow_to' => $id, 'follow_by' => $authUserId])->exists();
            $blocked = DB::table('blocked_users')
                    ->where(['user_id' => $id, 'blocked_by' => $authUserId])->exists();
                if(isset($request->page)){
                    $html='';
                    if(count($followingList)>0){
                        foreach($followingList as $follower){
                            $html.='<div class="card">';
                            $html.='<div class="card-body">';
                                if($follower->user_dp!=""){
                                    if(strpos($follower->user_dp,'facebook.com') !== false || strpos($follower->user_dp,'fbsbx.com') !== false || strpos($follower->user_dp,'googleusercontent.com') !== false){ 
                                        $u_dp=$follower->user_dp;
                                    }else{
                                        $u_dp=asset(Storage::url('public/profile_pic').'/'.$follower->user_id.'/small/'.$follower->user_dp) ;
                                        
                                    } 
                                }else{ 
                                    $u_dp= asset('default/default.png');
                                }
                            $html.='<img src="'.$u_dp.'" class="avatar_img float-left rounded-circle">';
                            $html.='<div class="message">';
                            $html.='<h5 class="card-title"><a href="'.route('web.userProfile', $follower->user_id).'">'. $follower->fname.' '.$follower->lname;
                              if($follower->verified=='A'){ 
                                $html.='<img src="'. asset('default/verified-icon-blue.png') .'" alt="" style="width:15px;height:15px;">';
                                 }
                                 $html.='</a></h5>';
                                 $html.='<h6 class="card-subtitle mb-2 text-muted"> @' . $follower->username .'</h6>';
                                 if($follower->user_id!= auth()->guard('web')->user()->user_id){
                                    $html.='<span class="follow_btn" data-id="'.$follower->user_id.'" style="'.Functions::getTopbarColor().'">';
                                        if($follower->follow>0){
                                            $html.="Unfollow";
                                        }else{
                                            $html.="Follow";
                                        }
                                        $html.='</span>';
                                 } 
                                 $html.='</div>';
                                 $html.='</div>
                            </div>';
                          
                        }
                    }

                    return response()->json(['html'=> $html,'totalFollow'=>$following,'loadedFollow'=>$loadedFollow ]);

                }
        return view('web.followingList', ['totalFollow'=>$following,'loadedFollow'=>$loadedFollow,'followingList' => $followingList,'userInfo'=> $userInfo, 'followers'=>$followers, 'following' => $following ,'canFollow'=> $canFollow,'followed'=>$followed,'blocked'=>$blocked]);
        // dd($followers);    
    }

    public function blockUsersList(Request $request){

        $blockList =  DB::table('blocked_users as b')
                ->join('users as u', 'u.user_id', 'b.user_id')
                // ->leftJoin('user_verify as uv', 'uv.user_id', 'c.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','b.user_id')
                    ->where('uv.verified','A');
                })
                ->select('u.user_id', 'u.username', 'u.fname','u.lname', 'u.login_type', 'u.user_dp','uv.verified')
                ->where('b.blocked_by', $this->authUser->user_id)
                ->where('u.active',1)
                ->where('u.deleted',0)
                ->orderBy('u.fname', 'asc')
                ->paginate(10);

                $totalBlock =  DB::table('blocked_users as b')
                ->join('users as u', 'u.user_id', 'b.user_id')
                // ->leftJoin('user_verify as uv', 'uv.user_id', 'c.user_id')
                ->leftJoin('user_verify as uv', function ($join){
                    $join->on('uv.user_id','=','b.user_id')
                    ->where('uv.verified','A');
                })
                ->select('u.user_id', 'u.username', 'u.fname','u.lname', 'u.login_type', 'u.user_dp','uv.verified')
                ->where('b.blocked_by', $this->authUser->user_id)
                ->where('u.active',1)
                ->where('u.deleted',0)
                ->orderBy('u.fname', 'asc')
                ->count();

                // ->get();
            $loadedBlock=count($blockList);
      
                if(isset($request->page)){
                    $html='';
                    if(count($blockList)>0){
                        foreach($blockList as $block){
                            $html.='<div class="card">';
                            $html.='<div class="card-body">';
                                if($block->user_dp!=""){
                                    if(strpos($block->user_dp,'facebook.com') !== false || strpos($block->user_dp,'fbsbx.com') !== false || strpos($block->user_dp,'googleusercontent.com') !== false){ 
                                        $u_dp=$block->user_dp;
                                    }else{
                                        $u_dp=asset(Storage::url('public/profile_pic').'/'.$block->user_id.'/small/'.$block->user_dp) ;
                                        
                                    } 
                                }else{ 
                                    $u_dp= asset('default/default.png');
                                }
                            $html.='<img src="'.$u_dp.'" class="avatar_img float-left rounded-circle">';
                            $html.='<div class="message">';
                            $html.='<h5 class="card-title"><a href="'.route('web.userProfile', $block->user_id).'">'. $block->fname.' '.$block->lname;
                              if($block->verified=='A'){ 
                                $html.='<img src="'. asset('default/verified-icon-blue.png') .'" alt="" style="width:15px;height:15px;">';
                                 }
                                 $html.='</a></h5>';
                                 $html.='<h6 class="card-subtitle mb-2 text-muted"> @' . $block->username .'</h6>';
                                //  <a onclick="blockUnblock('{{ $userInfo->user_id }}')" class="btn bg-btn text-white" style="min-width: 110px;padding:6px 0px;{{MyFunctions::getTopbarColor()}}">
                                 if($block->user_id!= auth()->guard('web')->user()->user_id){
                                     $html.='<span class="block_btn text-white" data-id="'.$block->user_id.'" style="'.Functions::getTopbarColor().'">Unblock</span>';
                                    // $html.='<span class="block_btn text-white" onclick="blockUnblock('.$block->user_id.')" style="'.Functions::getTopbarColor().'">Unblock</span>';
                                 } 
                                 $html.='</div>';
                                 $html.='</div>
                            </div>';
                          
                        }
                    }

                    return response()->json(['html'=> $html,'totalBlock'=>$totalBlock,'loadedBlock'=>$loadedBlock ]);

                }
        return view('web.blockedUserList', ['totalBlock'=>$totalBlock,'loadedBlock'=>$loadedBlock,'blockList' => $blockList]);
        // dd($followers);    
    }

    public function removeProfilePic(Request $request){
        if(auth()->user()!=null){
            $user=auth()->user();
            $user_id=auth()->user()->user_id;
            DB::table('users')->where('user_id',$user_id)->update(['user_dp'=>'']);
            Session :: flash('success', 'Profile Photo Remove successfull.');
            return redirect()->back();
        }
        
    }
}