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
use Intervention\Image\ImageManagerStatic as Image;
use Auth;
// use Mail;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMail;
use Illuminate\Support\Facades\URL; 
use App\User;
use App\Notifications\UserNotification;
use Google\Cloud\Storage\Notification;

class UserController extends Controller
{
	private function _error_string($errArray)
	{
		$error_string = '';
		foreach ($errArray as $key) {
			$error_string.= $key."\n";
		}
		return $error_string;
	}

	public function index(Request $request){

	}
	
	public function addGuestUser(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'platform_id'          => 'required',
			'fcm_token'          => 'required'
		], [
			'platform_id.required'   => 'Platform id is required.',
			'fcm_token.required'   => 'FCM token is required.'
		]);

		if (!$validator->passes()) {
			return response()->json(['status' => false, 'msg' => $this->_error_string($validator->errors()->all())]);
		} else {
			$checkRecord = DB::table("guest_users")
				->where("platform_id", $request->platform_id)
				->first();
			if ($checkRecord) {
				$updateData = array();
				$updateData['platform_id'] = $request->platform_id;
				$updateData['fcm_token'] = $request->fcm_token;
				$updateData['updated_on'] = date("Y-m-d H:i:s");
				DB::table("guest_users")->where('id', $checkRecord->id)->update($updateData);
			} else {
				$insertData = array();
				$insertData['platform_id'] = $request->platform_id;
				$insertData['fcm_token'] = $request->fcm_token;
				$insertData['joined_on'] = date("Y-m-d H:i:s");
				DB::table("guest_users")->insert($insertData);
			}
			$response = array("status" => true);
		}
	}

	public function updateFcmToken(Request $request)
	{

			if (auth()->guard('api')->user()) {
				$user_id = auth()->guard('api')->user()->user_id;
				DB::table("users")->where('user_id', $user_id)->update(['fcm_token' => $request->fcm_token]);

				$count= DB::table('notifications as n')
				->join('users as u', 'u.user_id', 'n.notify_by')
				->select(DB::raw("u.user_id as user_id"))
				->where('notify_to',$user_id)
				->where('n.read', 0)
				->count();

				$response = array("status" => true, 'msg' => 'Fcm token updated successfully.', 'count'=> $count);
			} else {
				return response()->json([
					"status" => false, "msg" => "Unauthorized user!"
				]);
			}
			return response()->json($response);
		
	}
	
	public function updateUserProfilePic(Request $request){
	    if(auth()->guard('api')->user()){
    		$validator = Validator::make($request->all(), [ 
    // 			'user_id'          => 'required',              
    // 			'app_token'        => 'required',
    			'profile_pic'          => 'required|image|mimes:jpeg,png,jpg,gif,svg',             
    		],[
    // 			'user_id.required'      => 'Id is required',
    // 			'app_token.required'    => 'App Token is required',
    			'profile_pic.required'	=> 'Profile Image is required',
    		]);
    
    		if (!$validator->passes()) {
    			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
    		}else{
    			$functions = new Functions();
    			$user_id=auth()->guard('api')->user()->user_id;
    			$path = 'public/profile_pic/'.$user_id;
    			$filenametostore = $request->file('profile_pic')->store($path);  
    			Storage::setVisibility($filenametostore, 'public');
    			$fileArray = explode('/',$filenametostore);
    			$fileName = array_pop($fileArray);
    			$functions->_cropImage(asset(Storage::url('public/profile_pic/'.$user_id.'/'.$fileName)),500,500,0,0,$path.'/small',$fileName);
    			$file_path = asset(Storage::url('public/profile_pic/'.$user_id."/".$fileName));
    			$small_file_path = asset(Storage::url('public/profile_pic/'.$user_id."/small/".$fileName));
    			if($file_path==""){
    				$file_path=asset('default/default.png');
    			}
    			if($small_file_path==""){
    				$small_file_path=asset('default/default.png');
    			}
    			
    			$data =array(
    				'user_id'       => $user_id,
    				'image'         => $fileName
    				
    			); 
    			
    			DB::table('users')
    			->where('user_id',$user_id)
    			->update(['user_dp'=>$fileName]);
    			
    			$response = array("status" => "success",'msg'=>'Profile pic uploaded successfully' , 'large_pic' => $file_path ,'small_pic' => $small_file_path);
    			
    			
    			return response()->json($response); 
    			
    		}
	    }else{
            return response()->json([
                "status" => "error", "msg" => "Unauthorized user!"
            ]);
        }
	}
	
	public function fetchUserInformation(Request $request){
		$validator = Validator::make($request->all(), [ 
			'user_id'          => 'required',
		],[ 
			'user_id.required'      => 'User id is required',
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
			$functions = new Functions();
			$videoStoragePath  = asset(Storage::url("public/videos"));
			$limit=15;
			
			if(auth()->guard('api')->user()){
			    $login_id=auth()->guard('api')->user()->user_id;
			}else{
			    $login_id=0;
			}
			$userVideos = DB::table("videos as v")
			->select(DB::raw("v.video_id,case when v.user_id = 0  then concat('".$videoStoragePath."/',video) else concat('".$videoStoragePath."/',v.user_id,'/',video) end as video,case when thumb='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',thumb) end as thumb,ifnull(case when gif='' then '' else concat('".$videoStoragePath."/',v.user_id,'/gif/',gif) end,'') as gif,ifnull(s.title,'') as sound_title,concat('@',u.username) as username,v.duration,v.user_id,v.tags,ifnull(v.created_at,'NA') as created_at,ifnull(v.updated_at,'NA') as updated_at,
				v.total_likes as total_likes,v.total_views as total_views, v.total_comments as total_comments, IF(uv.verified='A', true, false) as isVerified"))
			->join("users as u","v.user_id","u.user_id")
			// ->leftJoin("user_verify as uv","uv.user_id","u.user_id")
			->leftJoin('user_verify as uv', function ($join){
				$join->on('uv.user_id','=','u.user_id')
				->where('uv.verified','A');
			})
			->leftJoin("sounds as s","s.sound_id","v.sound_id")
			->where("v.user_id",$request->user_id)
			->where("v.deleted",0)
	        ->where("v.enabled",1)
	        ->where("v.active",1)
	        ->where("v.flag",0);
			if($request->user_id > 0  && $request->user_id == $login_id) {
				//$videos = $videos->whereRaw(DB::raw("v.privacy=1")); 
				$userVideos = $userVideos->where("v.user_id","=", $request->user_id); 
			} else {
				$userVideos = $userVideos->where("v.privacy","<>", "1");    
			}
			if($login_id > 0 && $login_id!=$request->user_id) {
				$userVideos=$userVideos->leftJoin('follow as f2', function ($join) use ($request,$login_id){
					$join->on('v.user_id','=','f2.follow_to')
					->where('f2.follow_by',$login_id);
				});
				
				$userVideos=$userVideos->leftJoin('reports as rp', function ($join) use ($request,$login_id){
					$join->on('v.video_id','=','rp.video_id');
					$join->whereRaw(DB::raw(" ( rp.user_id=".$login_id." )" ));
				});
				$userVideos=$userVideos->whereRaw( DB::Raw(' rp.report_id is null '));
	
				if($request->user_id != $login_id) {
					$userVideos=$userVideos->whereRaw( DB::Raw(' CASE WHEN (f2.follow_id is not null ) THEN (v.privacy=2 OR v.privacy=0) ELSE v.privacy=0 END '));
				}
			}
			$userVideos=$userVideos->orderBy("v.video_id",'desc');
			
			$userVideos = $userVideos->paginate(9);
			$totalVideos = $userVideos->total();
			

			$userRecord = DB::table('users')
				->select(DB::raw("user_dp,user_id,fname,lname,bio"))
			->where('user_id',$request->user_id)
			->first();
			
			$name = $userRecord->fname." ".$userRecord->lname;
			if(stripos($userRecord->user_dp,'https://')!==false){
				$file_path=$userRecord->user_dp;
				$small_file_path=$userRecord->user_dp;
			}else{
				$file_path = asset(Storage::url('public/profile_pic/'.$request->user_id."/".$userRecord->user_dp));
				$small_file_path = asset(Storage::url('public/profile_pic/'.$request->user_id."/small/".$userRecord->user_dp));
				
				if($file_path==""){
					$file_path=asset('default/default.png');
				}
				if($small_file_path==""){
					$small_file_path=asset('default/default.png');
				}
			}
			$userFollowers = DB::table("follow as f")
				->select(DB::raw("count(*) as totalFollowers"))
				->join("users as u","f.follow_by","u.user_id")
				->where("f.follow_to",$request->user_id)
				->where('u.active',1)
				->where('u.deleted',0)
				->first();
			$totalFollowers = '0';
			if($userFollowers) {
				$totalFollowers = Functions::digitsFormate($userFollowers->totalFollowers);
			}
			
			$userFollowings = DB::table("follow as f")
				->select(DB::raw("count(follow_id) as totalFollowing"))
				->join("users as u","f.follow_to","u.user_id")
				->where("f.follow_by",$request->user_id)
				->where('u.active',1)
				->where('u.deleted',0)
				->first();
			
			$totalFollowing = '0';
			if($userFollowings) {
				$totalFollowing = Functions::digitsFormate($userFollowings->totalFollowing);
			}
			
			$userVideosLikes = DB::table("videos")
			->select(DB::raw("ifnull(sum(total_likes),0) as totalVideosLike"))
			->where("deleted",0)
			->where("user_id",$request->user_id)
			->first();
			
			$totalVideosLike = 0;
			if($userVideosLikes) {
				$totalVideosLike = Functions::digitsFormate($userVideosLikes->totalVideosLike);    
			}
			
			$followText = "Follow";
			$blockText = "no";
			if( isset($login_id) && $login_id>0 ) {
				$checkFollowFolloing = DB::table("follow")
				->select(DB::raw("follow_id"))
				->where("follow_by",$login_id)
				->where("follow_to",$request->user_id)
				->first();
				
				if($checkFollowFolloing) {
					$followText = "Following";
				}   
				
				$checkIsBloked = DB::table("blocked_users")
				->select(DB::raw("block_id"))
				->where("blocked_by",$login_id)
				->where("user_id",$request->user_id)
				->first();
				if($checkIsBloked) {
					$blockText = "yes";
				} 
			}
			$verified_status=0;
			$userVerify = DB::table("user_verify")
				->select(DB::raw("verified"))
				->where("user_id",$request->user_id)
				->first();
			if(isset($userVerify) && $userVerify->verified=='A'){
				$verified_status=1;
			}

			$userNameRes = DB::table("users")
					->select(DB::raw("concat('@',username) as username"))
					->where("user_id",$request->user_id)
					->first();

				$custom = collect(['blocked'=>$blockText,'totalRecords'=>$totalVideos, 'user_id' => (int) $request->user_id,'large_pic' => $file_path ,'small_pic' => $small_file_path,'name' => $name, 'bio' => $userRecord->bio,'totalVideosLike'=>$totalVideosLike, 'totalFollowings' => $totalFollowing, 'totalFollowers' => $totalFollowers, 'followText' => $followText,'totalVideos'=>Functions::digitsFormate($totalVideos),'isVerified'=>$verified_status,'username'=>$userNameRes->username]);

            $userVideos = $custom->merge($userVideos);

			$response = array("status" => "success", 'data' => $userVideos ,'blocked' => $blockText, 'totalRecords' => $totalVideos, 'large_pic' => $file_path, 'small_pic' => $small_file_path, 'name' => $name, 'bio' => $userRecord->bio, 'totalVideosLike' => $totalVideosLike, 'totalFollowings' => $totalFollowing, 'totalFollowers' => $totalFollowers, 'followText' => $followText, 'totalVideos' => Functions::digitsFormate($totalVideos));
			return response()->json($response); 	
		}
	}
	
	public function fetchLoginUserFavVideos(Request $request){
// 		$validator = Validator::make($request->all(), [ 
// 			'user_id'          => 'required',
// 			'app_token'          => 'required',
// 		],[ 
// 			'user_id.required'      => 'User id is required',
// 		]);

			
// 		if (!$validator->passes()) {
// 			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
// 		}else{
        if(auth()->guard('api')->user()){
		    
				$videoStoragePath  = asset(Storage::url("public/videos"));
				$limit=9;
				$userVideos = DB::table("likes as l")
				->select(DB::raw("v.video_id,case when v.user_id = 0  then concat('".$videoStoragePath."/',v.video) else concat('".$videoStoragePath."/',v.user_id,'/',v.video) end as video,case when v.thumb='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',v.thumb) end as thumb,ifnull(case when v.gif='' then '' else concat('".$videoStoragePath."/',v.user_id,'/gif/',v.gif) end,'') as gif,ifnull(s.title,'') as sound_title,concat('@',u.username) as username,v.duration,v.user_id,v.tags,ifnull(v.created_at,'NA') as created_at,ifnull(v.updated_at,'NA') as updated_at,v.total_likes as total_likes,v.total_views as total_views, v.total_comments as total_comments, IF(uv.verified='A', true, false) as isVerified,v.description,v.privacy"))
				->join("videos as v","l.video_id","v.video_id")
				->join("users as u","v.user_id","u.user_id")
				// ->leftJoin("user_verify as uv","uv.user_id","u.user_id")
				->leftJoin('user_verify as uv', function ($join){
					$join->on('uv.user_id','=','u.user_id')
					->where('uv.verified','A');
				})
				->leftJoin("sounds as s","s.sound_id","v.sound_id")
				->where("v.deleted",0)
				->where("l.user_id",auth()->guard('api')->user()->user_id)
		        ->where("v.enabled",1)
		        ->where("v.active",1)
		        ->where("v.flag",0)
				->orderBy("l.like_id",'desc');
			
				$userVideos = $userVideos->paginate($limit);
				$totalVideos = $userVideos->total();
				$response = array("status" => "success",'data' => $userVideos,'totalVideos'=>Functions::digitsFormate($totalVideos));
				return response()->json($response);
			
		}else{
            return response()->json([
                "status" => "error", "msg" => "Unauthorized user!"
            ]);
        }
	}


	public function fetchLoginUserInformation(Request $request){
// 		$validator = Validator::make($request->all(), [ 
// 			'user_id'          => 'required',
// 		],[ 
// 			'user_id.required'      => 'User id is required',
// 		]);
		
// 		if (!$validator->passes()) {
// 			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
// 		}else{
// 		    $functions = new Functions();
// 			$token_res= $functions->validate_token($request->user_id,$request->app_token);
			
// 			if($token_res>0){
			 if(auth()->guard('api')->user()){
				$user_id=auth()->guard('api')->user()->user_id;
				$videoStoragePath  = asset(Storage::url("public/videos"));
				$limit=9;
				$userVideos = DB::table("videos as v")
				->select(DB::raw("video_id,case when v.user_id = 0  then concat('".$videoStoragePath."/',video) else concat('".$videoStoragePath."/',v.user_id,'/',video) end as video,case when thumb='' then '' else concat('".$videoStoragePath."/',v.user_id,'/thumb/',thumb) end as thumb,ifnull(case when gif='' then '' else concat('".$videoStoragePath."/',v.user_id,'/gif/',gif) end,'') as gif,ifnull(s.title,'') as sound_title,concat('@',u.username) as username,v.duration,v.user_id,v.tags,ifnull(v.created_at,'NA') as created_at,ifnull(v.updated_at,'NA') as updated_at,v.total_likes as total_likes,v.total_views as total_views, v.total_comments as total_comments, IF(uv.verified='A', true, false) as isVerified,v.description,v.privacy"))
				->join("users as u","v.user_id","u.user_id")
				// ->leftJoin("user_verify as uv","uv.user_id","u.user_id")
				->leftJoin('user_verify as uv', function ($join){
					$join->on('uv.user_id','=','u.user_id')
					->where('uv.verified','A');
				})
				->leftJoin("sounds as s","s.sound_id","v.sound_id")
				->where("v.deleted",0)
				->where("v.user_id",$user_id)
		        ->where("v.enabled",1)
		        ->where("v.active",1)
		        ->where("v.flag",0)
				->orderBy("v.video_id",'desc');
				
				$userVideos = $userVideos->paginate($limit);
				$totalVideos = $userVideos->total();
				$userRecord = DB::table('users')
					->select(DB::raw("user_dp,user_id,fname,lname,bio"))
				->where('user_id',$user_id)
				->first();
				
				$name = $userRecord->fname." ".$userRecord->lname;
				if(stripos($userRecord->user_dp,'https://')!==false){
					$file_path=$userRecord->user_dp;
					$small_file_path=$userRecord->user_dp;
				}else{
					$file_path = asset(Storage::url('public/profile_pic/'.$user_id."/".$userRecord->user_dp));
					$small_file_path = asset(Storage::url('public/profile_pic/'.$user_id."/small/".$userRecord->user_dp));
					
					if($file_path==""){
						$file_path=asset('default/default.png');
					}
					if($small_file_path==""){
						$small_file_path=asset('default/default.png');
					}
				}
				
				$userFollowers = DB::table("follow as f")
				->select(DB::raw("count(*) as totalFollowers"))
				->join("users as u","f.follow_by","u.user_id")
				->where("f.follow_to",$user_id)
				->where("f.follow_by",'<>',$user_id)
				->where('u.active',1)
				->where('u.deleted',0)
				->first();
				
				$totalFollowers = '0';
				if($userFollowers) {
					$totalFollowers = Functions::digitsFormate($userFollowers->totalFollowers);
				}
				
				$userFollowings = DB::table("follow as f")
				->select(DB::raw("count(*) as totalFollowing"))
				->join("users as u","f.follow_to","u.user_id")
				->where("f.follow_to",'<>',$user_id)
				->where("f.follow_by",$user_id)
				->where('u.active',1)
				->where('u.deleted',0)
				->first();
				
				$totalFollowing = '0';
				if($userFollowings) {
					$totalFollowing = Functions::digitsFormate($userFollowings->totalFollowing);
				}
				
				$userVideosLikes = DB::table("videos")
				->select(DB::raw("ifnull(sum(total_likes),0) as totalVideosLike"))
				->where("deleted",0)
				->where("user_id",$user_id)
				->first();
				
				$totalVideosLike = 0;
				if($userVideosLikes) {
					$totalVideosLike = Functions::digitsFormate($userVideosLikes->totalVideosLike);    
				}
				$verified_status=0;
				$userVerify = DB::table("user_verify")
					->select(DB::raw("verified"))
					->where("user_id",$user_id)
					->first();
				if(isset($userVerify) && $userVerify->verified=='A'){
					$verified_status=1;
				}

				$userNameRes = DB::table("users")
					->select(DB::raw("concat('@',username) as username"))
					->where("user_id",$user_id)
					->first();
				
				$version="";
				$appVersion = DB::table("settings")
					->select(DB::raw("cur_version as version"))
					->first();
				if($appVersion){
					if(isset($appVersion->version)){
						$version=$appVersion->version;
					}
				}
				$custom = collect(['totalRecords' => $totalVideos, 'large_pic' => $file_path, 'small_pic' => $small_file_path, 'name' => $name, 'bio' => $userRecord->bio, 'totalVideosLike' => $totalVideosLike, 'totalFollowings' => $totalFollowing, 'totalFollowers' => $totalFollowers, 'totalVideos' => Functions::digitsFormate($totalVideos), 'isVerified' => $verified_status, 'username' => $userNameRes->username, 'version' => $version]);
                $userVideos = $custom->merge($userVideos);
				
				$response = array("status" => "success",'data' => $userVideos,'totalRecords'=>$totalVideos,'large_pic' => $file_path ,'small_pic' => $small_file_path,'name' => $name,'totalVideosLike'=>$totalVideosLike, 'totalFollowings' => $totalFollowing, 'totalFollowers' => $totalFollowers,'totalVideos'=>Functions::digitsFormate($totalVideos));
				return response()->json($response);
			}else{
	            return response()->json([
	                "status" => "error", "msg" => "Unauthorized user!"
	            ]);
            } 	
		//}
	}
	public function removeFollower(Request $request){
	    $validator = Validator::make($request->all(), [
			'remove_to'          => 'required'           
		],[ 
			'remove_to.required'	=> 'remove to is required',
		]);

		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
			$functions = new Functions();
			if(auth()->guard('api')->user()) {
			    $user_id=auth()->guard('api')->user()->user_id;
			    DB::table('follow')
    				->where('follow_by',$request->remove_to)
    				->where('follow_to',$user_id)
    				->delete();
    				
    			DB::table('follow')
    				->where('follow_to',$request->remove_to)
    				->where('follow_by',$user_id)
    				->delete();
    			$response = array("status" => "success");
			} else {
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}   
			return response()->json($response); 
		}
	}
	public function followUnfollowUser(Request $request){
		$validator = Validator::make($request->all(), [ 
// 			'follow_by'          => 'required',              
// 			'app_token'        => 'required',
			'follow_to'          => 'required'           
		],[ 
// 			'follow_by.required'    => 'Follow by is required',
// 			'app_token.required'    => 'App Token is required',
			'follow_to.required'	=> 'Follow to is required',
		]);

		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
			$functions = new Functions();
			if(auth()->guard('api')->user()) {
			    $follow_by=auth()->guard('api')->user()->user_id;
				$followRecord = DB::table('follow')
				->select(DB::raw("follow_id"))
				->where('follow_by',$follow_by)
				->where('follow_to',$request->follow_to)
				->first();
				
				if($followRecord) {
					DB::table('follow')->where('follow_id', $followRecord->follow_id)->delete();
					$follow_text = "Follow";    
				} else {
					$insertData = array();
					$insertData['follow_by'] = $follow_by;
					$insertData['follow_to'] = $request->follow_to;
					$insertData['follow_on'] = date("Y-m-d H:i:s");
					DB::table("follow")->insert($insertData);
					$follow_text = "Unfollow";
					
					$user_id=$follow_by;
				
				    $notification_settings = DB::table('notification_settings')->where('user_id', $request->follow_to)->first();
                    if ($user_id != $request->follow_to) {
                        $user = User::find($user_id);
                        $lastName = (isset($user->lname)) ? $user->lname : '';
                        $title = $user->fname . ' ' . $lastName . ' following you ';
                        
                        if($notification_settings && $notification_settings->follow == 1) {
                        
                            $user_to = User::find($request->follow_to);
                            $file_path = '';
                            $small_file_path = '';
                            
                            if ($user->photo != '' && $user->photo != null) {
                                if (stripos($user->photo, 'https://') !== false) {
                                    $file_path = $user->photo;
                                    $small_file_path = $user->photo;
                                } else {
                                    $file_path = asset(Storage::url('profile_pic/' . $user->user_id . "/" . $user->photo));
                                    $small_file_path = asset(Storage::url('profile_pic/' . $user->user_id . "/small/" . $user->photo));
                                }
                            }
                          
                            $description = 'following';
                            $param = ['id' => strval($follow_by), 'type' => 'follow'];
                            
                            $user_to->notify(new UserNotification($title, $description, $small_file_path, $param));
                        }
                        
                      
                            
                        $nData['notify_by']=$user_id;
                        $nData['notify_to']=$request->follow_to;
                        $nData['video_id'] = 0;
                        $nData['message'] = $title;
                        $nData['type'] = 'F';
                        $nData['read'] = 0;
                        $nData['added_on'] = date('Y-m-d H:i:s') ;
                        
                        DB::table('notifications')->insert($nData);
                    }				
					
                    
				}	
				$userFollowers = DB::table("follow")
				->select(DB::raw("count(*) as totalFollowers"))
				->where("follow_to",$request->follow_to)
				->first();
				
				$totalFollowers = '0';
				if($userFollowers) {
					$totalFollowers = Functions::digitsFormate($userFollowers->totalFollowers);
				}
				
				$is_following_videos = 0;
				$followingVideos = DB::table("follow")
				->select(DB::raw("follow_id"))
				->where("follow_by",$follow_by)
				->first(); 
				if($followingVideos) {
					$is_following_videos = 1;
				}
				
				$userFollowersSql = DB::table("follow")
				->select(DB::raw("count(*) as totalFollowers"))
				->where("follow_to",$follow_by)
				->first();
				
				$totalFollowersCount = '0';
				if($userFollowersSql) {
					$totalFollowersCount = Functions::digitsFormate($userFollowersSql->totalFollowers);
				}
				
				$userFollowingsSql = DB::table("follow")
				->select(DB::raw("count(*) as totalFollowing"))
				->where("follow_by",$follow_by)
				->first();
				
				$totalFollowingsCount = '0';
				if($userFollowingsSql) {
					$totalFollowingsCount = Functions::digitsFormate($userFollowingsSql->totalFollowing);
				}
				
				$response = array("status" => "success",'followText'=>$follow_text,'totalFollowers'=>$totalFollowers, 'is_following_videos' => $is_following_videos,'total_followings' => $totalFollowingsCount, 'total_followers' => $totalFollowersCount);
			} else {
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}   
			return response()->json($response); 
		}
	}
	
	public function FollowingUsersList(Request $request){
		$validator = Validator::make($request->all(), [ 
			'user_id'          => 'required',
// 			'login_id'          => 'required',
// 			'app_token'        => 'required',
		],[ 
			'user_id.required'      => 'User id is required',
// 			'app_token.required'    => 'App Token is required',
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
			if(auth()->guard('api')->user()){
			    $login_id=auth()->guard('api')->user()->user_id;
			    $userDpPath = asset(Storage::url('public/profile_pic'));
				$limit = 10;
				$users = DB::table("users as u")->select(DB::raw("u.user_id,
					case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp,
					concat('@',u.username) as username,u.fname,u.lname, case when f2.follow_id > 0 THEN 'Unfollow' ELSE 'Follow' END as followText"))
					->leftJoin('follow as f', function ($join) use ($request){
						$join->on('u.user_id','=','f.follow_to');
						// ->where('f.follow_by',$request->login_id);
					})
					->leftJoin('follow as f2', function ($join) use ($request,$login_id){
						$join->on('u.user_id','=','f2.follow_to')
						->where('f2.follow_by',$login_id);
					});
					if($login_id > 0) {
						$users = $users->leftJoin('blocked_users as bu', function ($join)use ($request,$login_id){
							$join->on('u.user_id','=','bu.user_id');
							$join->whereRaw(DB::raw(" ( bu.blocked_by=".$login_id." )" ));
						});
	
						$users = $users->leftJoin('blocked_users as bu2', function ($join)use ($request,$login_id){
							$join->on('u.user_id','=','bu2.blocked_by');
							$join->whereRaw(DB::raw(" (  bu2.user_id=".$login_id." )" ));
						});
	
						$users = $users->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
					}
					$users=$users->where('f.follow_to','<>', $request->user_id);
					$users=$users->where('f.follow_by', $request->user_id)
					->where("u.deleted",0)
					->where("u.active",1);
				
				if(isset($request->search) && $request->search!=""){
					$search = $request->search;
					$users = $users->where('u.username', 'like', '%' . $search . '%')->orWhere('u.fname', 'like', '%' . $search . '%')->orWhere('u.lname', 'like', '%' . $search . '%');
				}
				
				$users = $users->orderBy('u.user_id','desc');
				$users= $users->paginate($limit);
				$total_records=$users->total();   
				
				$response = array("status" => "success",'data' => $users,'total_records'=>$total_records);
			}else{
			    return response()->json([
                    "status" => "error", "msg" => "Unauthorized user!"
                ]);
			}
		}
		
		return response()->json($response); 
	
	}
	
	public function submitReport(Request $request){
		$validator = Validator::make($request->all(), [ 
			'video_id'        => 'required',
		],[ 
			'video_id.required'    => 'Video Id is required',
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
		
			if(auth()->guard('api')->user()) {
			 $user_id=auth()->guard('api')->user()->user_id;
				$insertData = array();
				$insertData['user_id'] = $user_id;
				$insertData['video_id'] = $request->video_id;
				$insertData['type'] = $request->type;
				$insertData['description'] = strip_tags(is_null($request->description) ? '' : $request->description);
				$insertData['report_on'] = date("Y-m-d H:i:s");
				DB::table("reports")->insert($insertData);
				
				$videoTotalReport = DB::table("videos")
				->select(DB::raw("total_report"))
				->where("video_id",$request->video_id)
				->first();
				$total_report = 0;
				if($videoTotalReport) {
					$total_report = $videoTotalReport->total_report;
				}
				$total_report = $total_report + 1;
				DB::table("videos")->where('video_id',$request->video_id)->update(['total_report' => $total_report]);
				$response = array("status" => "success",'msg' => 'Thanks for reporting.If we find this content to be in violation of our Guidelines, we will remove it.');
			} else {
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
		} 
		return response()->json($response); 
	}
	
	public function deleteComment(Request $request){
		$validator = Validator::make($request->all(), [ 
			'comment_id'        => 'required',
			'video_id'        => 'required',
		],[ 
			'comment_id.required'    => 'Comment Id is required',
			'video_id.required'    => 'Video Id is required'
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{

			if(auth()->guard('api')->user()) {
				DB::table('comments')->where('comment_id', $request->comment_id)->delete();
				$totalComments = DB::table("videos")
				->select(DB::raw("total_comments"))
				->where("video_id",$request->video_id)
				->first();
				$total_comments = 0;
				if($totalComments) {
					$total_comments = $totalComments->total_comments;
				}
				$total_comments = $total_comments - 1;
				DB::table("videos")->where('video_id',$request->video_id)->update(['total_comments' => $total_comments]);
				$response = array("status" => "success",'total_comments'=>Functions::digitsFormate($total_comments));
			} else {
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
		} 
		return response()->json($response); 
	}
	public function editComment(Request $request){
		$validator = Validator::make($request->all(), [ 
			'comment'         => 'required',           
            'comment_id'      => 'required',
            'video_id'         => 'required'
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
			$functions = new Functions();
            $user_id=auth()->guard('api')->user()->user_id;
			$comment_detail=DB::table('comments')->where('user_id',$user_id)->where('comment_id',$request->comment_id)->where('video_id',$request->video_id)->first();

			if($comment_detail){
				DB::table('comments')
						->where('user_id',$user_id)
						->where('comment_id',$request->comment_id)
						->where('video_id',$request->video_id)
						->update(['comment'=>$request->comment]);
			    $response = array("status" => "success",'msg'=>'Comment updated successfully');
				return response()->json($response);
			}else{
				return response()->json(['status'=>'error','msg'=> "Invalid Request"]);
			}
		}
	}
	public function FollowersList(Request $request){
		$validator = Validator::make($request->all(), [ 
			'user_id'          => 'required'
		],[ 
			'user_id.required'      => 'User id is required'
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
		        $login_id=auth()->guard('api')->user()->user_id;
				$userDpPath = asset(Storage::url('public/profile_pic'));
				$limit = 10;
				$users = DB::table("users as u")->select(DB::raw("u.user_id,
					case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp,
					concat('@',u.username) as username,u.fname,u.lname, case when f2.follow_id > 0 THEN 'Following' ELSE 'Follow' END as followText"))
				->leftJoin('follow as f', function ($join) use ($request){
					$join->on('u.user_id','=','f.follow_by');
					// ->where('f.follow_to',$request->login_id);
				})
				->leftJoin('follow as f2', function ($join) use ($request,$login_id){
						$join->on('u.user_id','=','f2.follow_to')
						->where('f2.follow_by',$login_id);
					});
				if($request->login_id > 0) {
                    $users = $users->leftJoin('blocked_users as bu', function ($join)use ($request,$login_id){
                        $join->on('u.user_id','=','bu.user_id');
                        $join->whereRaw(DB::raw(" ( bu.blocked_by=".$login_id." )" ));
                    });

                    $users = $users->leftJoin('blocked_users as bu2', function ($join)use ($request,$login_id){
                        $join->on('u.user_id','=','bu2.blocked_by');
                        $join->whereRaw(DB::raw(" (  bu2.user_id=".$login_id." )" ));
                    });

                    $users = $users->whereRaw( DB::Raw(' bu.block_id is null and bu2.block_id is null '));
                }
                $users=$users->where('f.follow_by','<>', $request->user_id);
				$users=$users->where('f.follow_to', $request->user_id)
				->where("u.deleted",0)
				->where("u.active",1);
				
				if(isset($request->search) && $request->search!=""){
					$search = $request->search;
					$users = $users->where('u.username', 'like', '%' . $search . '%')->orWhere('u.fname', 'like', '%' . $search . '%')->orWhere('u.lname', 'like', '%' . $search . '%');
				}
				
				$users = $users->orderBy('u.user_id','desc');
				$users= $users->paginate($limit);
				$total_records=$users->total();   
				
				$response = array("status" => "success",'data' => $users,'total_records'=>$total_records);
			
		} 
		return response()->json($response); 
	}
	
	public function unique_user_id(){
		$characters = "abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$string     = "";

		for($p = 0; $p < 15; $p++)
		{
			$string .= $characters[mt_rand(0, strlen($characters) - 1)];
		}
		
		$uniques_user_id_res = DB::table("unique_users_ids")->select("unique_token")->where('unique_token',$string)->first();
		if($uniques_user_id_res){
			$this->unique_user_id();
		}else{
			DB::table('unique_users_ids')->insert(['unique_token'=>$string]);   
		}
		
		$response = array("status" => "success" ,'unique_token' => $string);      
		return response()->json($response); 
	}
	
	public function blockUser(Request $request){
		$validator = Validator::make($request->all(), [ 
			'user_id'          => 'required'           
		],[
			'user_id.required'   => 'User Id  is required.'
			
		]);

		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
		    
			if(auth()->guard('api')->user()) {
			     $blocked_by=auth()->guard('api')->user()->user_id;
				$res=DB::table('blocked_users')
				->select(DB::raw('block_id'))
				->where('user_id',$request->user_id)
				->where('blocked_by',$blocked_by)
				->get();
				if($res->isEmpty()){
					
					$data =array(
						'user_id' => $request->user_id,
						'blocked_by' => $blocked_by,
						'blocked_on'  => date("Y-m-d H:i:s")                                                   
					); 
					DB::table('blocked_users')->insert($data);
					
                    //followers
					DB::table('follow')->where('follow_by', $request->user_id)->where('follow_to', $blocked_by)->delete();
					DB::table('follow')->where('follow_to', $request->user_id)->where('follow_by', $blocked_by)->delete();
					$response = array( "status" => "success", "msg" => "User blocked Successfully","block"=>'Unblocked');
					
                //exit();
				}else{
					DB::table('blocked_users')->where('user_id', $request->user_id)->where('blocked_by', $blocked_by)->delete();
					$response = array( "status" => "success", "msg" => "User unblocked Successfully","block"=>'Block');
				}  
				return response()->json($response); 
			}else{
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
		}
	}

	public function userVerify(Request $request){
		$validator = Validator::make($request->all(), [ 
		              
			'name'          => 'required',              
			'address'          => 'required',              
			'document1'          => 'required',              
		],[ 
		
			'name.required'   		=> 'Name is required.',
			'address.required'   => 'Address is required.',
			'document1.required'   => 'Id Proof  is required.',
			
		]);

		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
		
			if(auth()->guard('api')->user()) {
			 $user_id=auth()->guard('api')->user()->user_id;
			$exist_user=DB::table('user_verify')
					->select(DB::raw('user_verify_id,verified'))
					->where('user_id',$user_id)
					->orderBy('user_verify_id','desc')
					->first();
				if(!$exist_user || $exist_user->verified=='R'){
						$data=array(
							'user_id'=>$user_id,
							'name'=>strip_tags($request->name),
							'address'=>strip_tags($request->address),
							'added_on'=>date('Y-m-d H:i:s')
						);
					if($request->hasFile('document1')){
						$path = 'public/id_proof/'.$user_id;
				
						$filenametostore = request()->file('document1')->store($path);  
						Storage::setVisibility($filenametostore, 'public');
						$fileArray = explode('/',$filenametostore);  
						$fileName = array_pop($fileArray); 
						$data['front_idproof']=$fileName;
					}else{
						return response()->json([
							"status" => "error", "msg" => "Id Proof is required!"
						]);
					}
					if($request->hasFile('document2')){
						$path = 'public/id_proof/'.$user_id;
				
						$filenametostore = request()->file('document2')->store($path);  
						Storage::setVisibility($filenametostore, 'public');
						$fileArray = explode('/',$filenametostore);  
						$fileName = array_pop($fileArray); 
						$data['back_idproof']=$fileName;
					}	
						DB::table('user_verify')->insert($data);
					
						// DB::table('user_verify')->where('user_id',$request->user_id)->update($data);
						$response = array( "status" => "success", "msg" => "Your Request is submitted Successfully");
				}else{
					if($exist_user->verified=='P'){
						$response = array( "status" => "success", "msg" => "Your Request is Pending");
					}elseif($exist_user->verified=='A'){
						$response = array( "status" => "success", "msg" => "Your Request is Already Accepted");
					}
				}
					
	
				return response()->json($response); 
			}else{
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
		}
	}

	public function verifyStatusDetail(Request $request){
	
			if(auth()->guard('api')->user()) {
				$path=asset(Storage::url('public/id_proof/'));
				$user_id=auth()->guard('api')->user()->user_id;
				$userDetail=DB::table('user_verify')->select(DB::raw("case when front_idproof !='' THEN case when INSTR(front_idproof,'https://') > 0 THEN front_idproof ELSE concat('".$path."/',user_id,'/',front_idproof)  END ELSE '' END as document1,case when back_idproof !='' THEN case when INSTR(back_idproof,'https://') > 0 THEN back_idproof ELSE concat('".$path."/',user_id,'/',back_idproof)  END ELSE '' END as document2,name,address,user_id,rejected_reason,added_on,verified"))->where('user_id',$user_id)->orderBy('user_verify_id','desc')->first();
				if($userDetail){
					$response = array( "status" => "success", "data" => $userDetail ); 
				}else{
					$response = array( "status" => "success", "data" => array('verified'=>'NA') ); 
				}
				return response()->json($response);
			}else{
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
			
		
	}

	public function changePassword(Request $request){

		$validator = Validator::make($request->all(), [ 
		
            'old_password'           => 'required',           
            'password'           => 'required|same:confirm_password|different:old_password',
            'confirm_password'       => 'required'
		],[ 
		
        	'old_password.required'	    	=> 'Old Password is required',
        	'password.required'		  	=> 'Password is required',         
        	'confirm_password.required'	    => 'Confirm Password is required',
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
		
			if(auth()->guard('api')->user()) {
			    $user_id=auth()->guard('api')->user()->user_id;
				$user = DB::table('users')
					->select(DB::raw("*"))
					->where('user_id',$user_id)
					->first();

				if (Hash::check($request->old_password, $user->password)) {
					DB::table('users')
						->where('user_id',$user_id)
						->update(['password'=>Hash::make($request->password)]);
					
					$response = array("status" => "success",'msg'=>'Password changed successfully');
				} else {                    
					$response = array("status" => "error","msg"=>"Old password is incorrect");
				}  
			} else {
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
		} 
		return response()->json($response); 
	}

	public function getEulaAgree(Request $request){
		
			if(auth()->guard('api')->user()) {
			    $user_id=auth()->guard('api')->user()->user_id;
				$userDetail=DB::table('users')->select(DB::raw("eula_agree"))->where('user_id',$user_id)->first();
				$eulaAgree=$userDetail->eula_agree;
				if($userDetail){
					$response = array( "status" => "success", "eulaAgree" => $eulaAgree ); 
				}else{
					$response = array( "status" => "success", "data" => '' ); 
				}
				return response()->json($response);
			}else{
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
		
	}

	public function updateEulaAgree(Request $request){

			if(auth()->guard('api')->user()) {
			    $user_id=auth()->guard('api')->user()->user_id;
				DB::table('users')->where('user_id',$user_id)->update(['eula_agree'=>1]);
			
				$response = array( "status" => "success", "msg" => 'success' ); 
				return response()->json($response);
			}else{
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
	}

	public function forgotPassword(Request $request){
        $validator = Validator::make($request->all(), 
            [   
                'email'          => 'required|email'
            ],
            [
                'email.email'              => 'Email id is not valid.'
            ]);
        if(!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
        }else{
            $mail_setting=DB::table('mail_types')->where('active',1)->first();
            if((config('app.sendgrid_api_key') !="" || config('app.mail_host') !="") && isset($mail_setting)){
                $functions = new Functions();
                $now  = date("Y-m-d H:i:s");
                $otp= mt_rand(100000, 999999);

				$user_detail=DB::table('users')->where('email',$request->email)->first();

				if($user_detail){
					$user_id = DB::table('users')->where('email',$request->email)->update([
						'verification_code' => $otp,
						'verification_time' => $now
					]);

					$site_title =Functions::getSiteTitle();
                
                
					$mailBody = '
					<p>Dear <b>'.  $request->email .'</b>,</p>
					<p style="font-size:16px;color:#333333;line-height:24px;margin:0">Use the OTP to verify your email address.</p>
					<h3 style="color:#333333;font-size:24px;line-height:32px;margin:0;padding-bottom:23px;margin-top:20px;text-align:center">'
					.$otp.'</h3>
					<br/><br/>
					<p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you<br /><br/>'.$site_title.'</p>
					';
					// dd($mailBody);
					// $ref_id
					$array = array('subject'=>'OTP Email Verification - '.$site_title,'view'=>'emails.site.company_panel','body' => $mailBody);
					if(strpos($_SERVER['SERVER_NAME'], "localhost")===false && strpos($_SERVER['SERVER_NAME'], "leukewebpanel.local")===false){
						Mail::to($request->email)->send(new SendMail($array));  
					}
					$msg = "An OTP has been sent to your Email";
					// $id = $user_id;
					// $data  = array( 'user_id'=>$user_detail->user_id,'username'=>$user_detail->username, 'email' => $request->email, 'otp' => $otp );
					$msg = "An OTP has been sent to your Email";
					$response = array("status" => "success",'msg'=>$msg );      
					return response()->json($response); 

				}else{
					return response()->json(['status'=>'error','msg'=> "Email is not exist."]);
				}
			}else{
				return response()->json(['status'=>'error','msg'=> "Error! Please Contact to administrator."]);
			}
               
		}
	}

	public function updateForgotPassword(Request $request){
		$validator = Validator::make($request->all(), [ 
			'email'         => 'required|email',           
            'otp'       => 'required',          
            'password'           => 'required|same:confirm_password',
            'confirm_password'       => 'required'
		],[ 
			'email.required'	  	=> 'Email is required',
        	'otp.required'			=> 'Otp is required',
        	'password.required'		  	=> 'Password is required',         
        	'confirm_password.required'	    => 'Confirm Password is required',
		]);
		
		if (!$validator->passes()) {
			return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
		}else{
			$functions = new Functions();

			$user_detail=DB::table('users')->where('email',$request->email)->first();

			if($user_detail){
				if($user_detail->verification_code!=""){
					$now = date('Y-m-d H:i:s');
					$datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $user_detail->verification_time);
					$datetime->modify('+10 minutes');
					$expiryTime= $datetime->format('Y-m-d H:i:s');
					
					if(strtotime($now) > strtotime($expiryTime)){
						 $response = array("status" => "error",'msg'=>'Otp Expired');      
					}else{
						if(($user_detail->verification_code) != trim($request->otp)){
							 $response = array("status" => "error",'msg'=>'Otp doesn\'t match.');      
						}else{

							$password=Hash::make($request->password);
							DB::table('users')->where('email',$request->email)->update(['password'=>$password,'verification_code'=>'','verification_time'=>null]);
							$msg = "Password update successfully!";

							DB::table("users")->where("user_id",$user_detail->user_id)->update(array("active"=>'1',"email_verified"=>'1','verification_code'=>'','verification_time'=>null));
							 $response = array("status" => "success",'msg'=>$msg);      
						}
					}
				}else{
					 $response = array("status" => "error",'msg'=>'OTP expired');      
				}
				return response()->json($response);
			}else{
				return response()->json(['status'=>'error','msg'=> "Email is not exist."]);
			}
		}
	}


	public function blockedUsersList(Request $request){
		
			if(auth()->guard('api')->user()) {
			    $user_id= auth()->guard('api')->user()->user_id;
				$userDpPath = asset(Storage::url('public/profile_pic'));
				$limit = 10;
					
				$blockList =  DB::table('blocked_users as b')
				->join('users as u', 'u.user_id', 'b.user_id')
				// ->leftJoin('user_verify as uv', 'uv.user_id', 'c.user_id')
				->leftJoin('user_verify as uv', function ($join){
					$join->on('uv.user_id','=','b.user_id')
					->where('uv.verified','A');
				})
				->select('u.user_id', 'u.username', 'u.fname','u.lname', 'u.login_type', DB::raw("case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp"),'uv.verified')
				->where('b.blocked_by', $user_id)
				->where('u.active',1)
				->where('u.deleted',0)
				->orderBy('u.fname', 'asc')
				->paginate(10);

				$response = array( "status" => "success", "blockList" => $blockList ); 
				return response()->json($response);
			}else {
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
		
	}
	
	public function notificationsList(Request $request){
		
			if(auth()->guard('api')->user()) {
				$userDpPath = asset(Storage::url('public/profile_pic'));
				$limit = 10;
				$user_id= auth()->guard('api')->user()->user_id;

				$user= DB::table('notifications as n')
				->join('users as u', 'u.user_id', 'n.notify_by')
				->select(DB::raw("u.user_id as user_id,u.username as username,u.fname as first_name,u.lname as last_name,case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as photo,n.message as msg,n.type as type,n.video_id as video_id,n.read as is_read,n.added_on as sent_on"))
				->where('notify_to',$user_id)
				->orderBy('n.added_on', 'desc')
				->paginate(10);
			
				DB::table('notifications')->where('notify_to',$user_id)->update(['read'=>1]);

				$response = array( "status" => "success", "data" => $user ); 
				return response()->json($response);
			}else {
				return response()->json([
					"status" => "error", "msg" => "Unauthorized user!"
				]);
			}
	}
	
	public function getChatWith(Request $request){

		if(auth()->guard('api')->user()) {
			$user_id=auth()->guard('api')->user()->user_id;
			if(isset($request->chat_with)){
			    User::where('user_id',$user_id)->update(['chat_with'=>$request->chat_with]);
			}
			$chatWith=User::where('user_id',$user_id)->pluck('chat_with')->first();
			$response = array( "status" => true, "chatWith" => $chatWith ); 
				return response()->json($response);
		}else {
			return response()->json([
				"status" => "error", "msg" => "Unauthorized user!"
			]);
		}
	}
	public function logout()
	    {
	    	if(auth()->guard('api')->user()) {
		        $id = auth('api')->user()->user_id;
		        User::where('user_id',$id)->update(['fcm_token'=>'']);
		        auth('api')->logout();
		        return response()->json(['message' => 'Successfully logged out']);
		    }
	    }
}   