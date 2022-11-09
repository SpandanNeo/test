<?php

namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AppController extends Controller
{
    private function _error_string($errArray)
    {
        $error_string = '';
    foreach ($errArray as $key) {
            $error_string.= $key."\n";
        }
        return $error_string;
    }

    public function index(){
     
        $app_login_page = DB::table("app_login_page")->select(DB::raw("*"))->first();
        if($app_login_page){
            if($app_login_page->logo){
                $logo= asset(Storage::url('public/uploads/logos/'.$app_login_page->logo));
            }else{
                $logo='';
            }
            if($app_login_page->background_img){
                $background_img= asset(Storage::url('public/uploads/background_img/'.$app_login_page->background_img));
            }else{
                $background_img='';
            }
            $data=array("appLoginId"=>$app_login_page->app_login_page_id,
                    "logo" => $logo,
                    "backgroundImg" => $background_img,
                    "title" => strip_tags($app_login_page->title),
                    "description"     => strip_tags($app_login_page->description),
                    "fbLogin" => $app_login_page->fb_login,
                    "googleLogin" => $app_login_page->google_login,
                    "appleLogin" => $app_login_page->apple_login,
                    "privacyPolicy" => strip_tags($app_login_page->privacy_policy)   
            );
            $response = array( "status" => "success", "data" => $data ); 
        } else {
            $response = array( "status" => "failed", "msg" => "No Record" );
        }
        
        return response()->json($response);   
        // }else{
        //     $data=array("status"=>'error');
        // }
        // $response = $data;
        // return response()->json($response); 
    }

    public function endUserLicenseAgreement(){
        $data= DB::table('pages')->where('type','EULA')->first();
        // dd($data->content);
        if($data){
            $res['title'] = $data->title;
            $res['content'] = $data->content;
            $response = array( "status" => "success", "data" => $res );
        }else{
            $response = array( "status" => "error","msg"=>"no record", "data" => [] );
        }
    
        return response()->json($response);  
    }

    public function appConfig(){
        $data=DB::table("app_settings")->select(DB::raw('*'))->first();
        $res=array(
            'bgColor' => $data->bg_color,
            'accentColor' => $data->accent_color,
            'buttonColor' => $data->button_color,
            'textColor' => $data->text_color,
            'buttonTextColor' => $data->button_text_color,
            'senderMsgColor' => $data->sender_msg_color,
            'senderMsgTextColor'=> $data->sender_msg_text_color,
            'myMsgColor' => $data->my_msg_color,
            'myMsgTextColor' => $data->my_msg_text_color,
            'headingColor' => $data->heading_color,
            'subHeadingColor' => $data->sub_heading_color,
            'iconColor' => $data->icon_color,
            'dashboardIconColor' => $data->dashboard_icon_color,
            'gridItemBorderColor' => $data->grid_item_border_color,
            'gridBorderRadius' => $data->grid_border_radius,
            'dividerColor' => $data->divider_color,
            'dpBorderColor' => $data->dp_border_color,
            'inactiveButtonColor' => $data->inactive_button_color,
            'inactiveButtonTextColor' => $data->inactive_button_text_color,
            'videoTimeLimits' => $data->video_time_limit,
            'headerBgColor' => $data->header_bg_color,
            'bottomNav' => $data->bottom_nav,
            'bgShade' => $data->bg_shade
        );
        $response = array( "status" => "success", "data" => $res );
        return response()->json($response);  
    }

    public function search(Request $request){
        $search_term = request()->get('search');
        if ($request->user_id) {
        $user_id= $request->user_id;
        }else{
        $user_id=0;
        }
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $videoStoragePath = asset(Storage::url("public/videos"));
        
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
        $join->whereRaw(DB::raw(" ( bu2.user_id=".$user_id." )" ));
        });
        
        $users = $users->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        }
        $users= $users->select(DB::raw(" u.user_id,concat('@',u.username) as username,u.fname,u.lname,case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp) END ELSE '' END as user_dp,case when f.follow_id > 0 THEN 'Following' ELSE 'Follow' END as followText"))
        ->where('u.active',1)
        ->orderBy('u.username', 'asc')
        ->paginate(10);
        
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
        $join->whereRaw(DB::raw(" ( bu2.user_id=".$user_id." )" ));
        });
        
        $videos = $videos->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        $videos = $videos->leftJoin('follow as f', function ($join)use ($user_id){
        $join->on('v.user_id','=','f.follow_to');
        $join->whereRaw(DB::raw(" ( f.follow_by=".$user_id." )" ));
        });
        $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END '));
        
        }else{
        $videos= $videos->where('v.privacy',0);
        }
        
        $videos=$videos->select(DB::raw("v.video_id,v.user_id,v.description,v.title,concat('".$videoStoragePath."/',u.user_id,'/thumb/',v.thumb) as thumb,concat('@',u.username) as username,case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp) END ELSE '' END as user_dp"))
        ->where('v.active',1)
        ->where('v.deleted',0)
        ->where('u.active',1)
        ->where('v.flag',0)
        ->orderBy('v.title', 'asc')
        ->paginate(10);
        
        
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
        $join->whereRaw(DB::raw(" ( bu2.user_id=".$user_id." )" ));
        });
        
        $videoTags = $videoTags->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        $videoTags = $videoTags->leftJoin('follow as f', function ($join)use ($user_id){
        $join->on('v.user_id','=','f.follow_to');
        $join->whereRaw(DB::raw(" ( f.follow_by=".$user_id." )" ));
        });
        $videoTags = $videoTags->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END '));
        
        }else{
        $videoTags= $videoTags->where('v.privacy',0);
        }
        
        $videoTags=$videoTags->select('v.tags')
        ->where('v.active',1)
        ->where('v.deleted',0)
        ->where('u.active',1)
        ->where('v.flag',0)
        ->where('v.tags','!=','')
        ->orderBy('v.title', 'asc')
        ->paginate(10);
        $hashTags=[];
        
        foreach($videoTags as $t){
        array_push($hashTags,$t->tags);
        }
        
        $response = array( "status" => "success", "users" => $users->items(), "videos" => $videos->items(), "hashTags" => $hashTags );
        return response()->json($response);
    }
    public function searchUsers(Request $request){
        $search_term = request()->get('search');
        if ($request->user_id) {
        $user_id= $request->user_id;
        }else{
        $user_id=0;
        }
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $videoStoragePath = asset(Storage::url("public/videos"));
        
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
        $join->whereRaw(DB::raw(" ( bu2.user_id=".$user_id." )" ));
        });
        
        $users = $users->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        }
        $users= $users->select(DB::raw(" u.user_id,concat('@',u.username) as username,u.fname,u.lname,case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp) END ELSE '' END as user_dp,case when f.follow_id > 0 THEN 'Following' ELSE 'Follow' END as followText"))
        ->where('u.active',1)
        ->orderBy('u.username', 'asc');
        $usersCount= $users->count();
        $users= $users->paginate(10);
        
        
        $response = array( "status" => "success", "users" => $users->items(),'total'=>$usersCount );
        return response()->json($response);
    }

    public function searchVideos(Request $request){
        $search_term = request()->get('search');
        if ($request->user_id) {
        $user_id= $request->user_id;
        }else{
        $user_id=0;
        }
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $videoStoragePath = asset(Storage::url("public/videos"));
        
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
        $join->whereRaw(DB::raw(" ( bu2.user_id=".$user_id." )" ));
        });
        
        $videos = $videos->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        $videos = $videos->leftJoin('follow as f', function ($join)use ($user_id){
        $join->on('v.user_id','=','f.follow_to');
        $join->whereRaw(DB::raw(" ( f.follow_by=".$user_id." )" ));
        });
        $videos = $videos->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END '));
        
        }else{
        $videos= $videos->where('v.privacy',0);
        }
        
        $videos=$videos->select(DB::raw("v.video_id,v.user_id,v.description,case when u.user_dp!='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',v.user_id,'/small/',u.user_dp) END ELSE '' END as user_dp,concat('@',u.username) as username,v.title,concat('".$videoStoragePath."/',u.user_id,'/thumb/',v.thumb) as thumb"))
        ->where('v.active',1)
        ->where('v.deleted',0)
        ->where('u.active',1)
        ->where('v.flag',0)
        ->orderBy('v.title', 'asc');
        $videosCount=$videos->count();
        $videos=$videos->paginate(10);
        
        
        $response = array( "status" => "success", "videos" => $videos->items(),"total"=>$videosCount );
        return response()->json($response);
    }

    public function searchTags(Request $request){
        $search_term = request()->get('search');
        if ($request->user_id) {
        $user_id= $request->user_id;
        }else{
        $user_id=0;
        }
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
        $join->whereRaw(DB::raw(" ( bu2.user_id=".$user_id." )" ));
        });
        
        $videoTags = $videoTags->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
        $videoTags = $videoTags->leftJoin('follow as f', function ($join)use ($user_id){
        $join->on('v.user_id','=','f.follow_to');
        $join->whereRaw(DB::raw(" ( f.follow_by=".$user_id." )" ));
        });
        $videoTags = $videoTags->whereRaw( DB::Raw(' CASE WHEN (f.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END '));
        
        }else{
        $videoTags= $videoTags->where('v.privacy',0);
        }
        
        $videoTags=$videoTags->select('v.tags')
        ->where('v.active',1)
        ->where('v.deleted',0)
        ->where('u.active',1)
        ->where('v.flag',0)
        ->where('v.tags','!=','')
        ->orderBy('v.title', 'asc');
        $count=$videoTags->count();
        $videoTags=$videoTags
        ->paginate(10);
        $hashTags=[];
        
        foreach($videoTags as $t){
        array_push($hashTags,$t->tags);
        }
        
        $response = array( "status" => "success", "hashTags" => $hashTags,'total'=>$count );
        return response()->json($response);
    }
    public function hashTagVideos(Request $request){

        $userDpPath = asset(Storage::url('public/profile_pic'));
        $videoStoragePath = asset(Storage::url("public/videos"));
        $limit = 9;
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
        
        if($request->user_id > 0) {
        $videos = $videos->leftJoin('blocked_users as bu', function ($join)use ($request){
        $join->on('v.user_id','=','bu.user_id')->orOn('v.user_id','=','bu.blocked_by')
        ->whereRaw(DB::raw(" (bu.blocked_by=".$request->user_id." OR bu.user_id=".$request->user_id.")" ));
        });
        $videos = $videos->whereRaw( DB::Raw(' bu.block_id is null '));
        }
        if(isset($request->hash) && $request->hash!=""){
        $search = $request->hash;
        $videos = $videos->whereRaw(DB::raw("(concat(' ',v.tags,' ') like '% " . $search . " %')"));
        }
        $videos = $videos->orderBy("v.video_id","desc");
        $videos= $videos->paginate($limit);
        $total_records=$videos->total();
        
        
        $response = array("status" => "success",'data' => $videos,'total_records'=>$total_records);
        return response()->json($response);
    
    }

}   