<?php

namespace App\Http\Controllers\API;
use Auth;
use App\User;
use DateTime;
use Exception;
use JWTAuth;
use App\Mail\SendMail;
use Illuminate\Http\Request;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL; 
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    private function _error_string($errArray)
    {
        $error_string = '';
        foreach ($errArray as $key) {
            $error_string.= $key."\n";
        }
        return $error_string;
    }

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login','isEmailExist','resendOtp','fetchUserInformation','index','register','checkExistsEmail','verifyOtp','socialRegister','socialLogin']]);
    }
    
        //DB::enableQueryLog(); 
        // $userDpPath = secure_asset(config('app.profile_path'));
        // $videoStoragePath  = secure_asset(config("app.video_path"));

    public function index(Request $data){
        $before_date = date('Y-m-d', strtotime('-13 years'));
        $validator = Validator::make($data->all(), 
            [   
                'fname'          => 'required',
                // 'lname'          => 'required',
                'email'          => 'required|email|unique:users',
                'username'       => 'required|unique:users',
                'password'       => 'required|min:6',
            ],
            [
                'email.unique'             => 'Your Email is already registered please login.',
                'email.email'              => 'Email id is not valid.',
                'username.unique'          => 'This username is already taken.',
                'password.confirmed'       => 'Password doesn\'t match.',
            ]);
        if(!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
        }else{
            $mail_setting=DB::table('mail_types')->where('active',1)->first();
            if((config('app.sendgrid_api_key') !="" || config('app.mail_host') !="") && isset($mail_setting)){
                $functions = new Functions();
                $now  = date("Y-m-d H:i:s");
                $otp= mt_rand(100000, 999999);
                $user_token = Hash::make($functions->_password_generate(20));
                $user_id = DB::table('users')->insertGetId([
                    'fname' => $data->fname,
                    'lname' => $data->lname,
                    'username' => strtolower(strip_tags($data->username)),
                    'verification_code' => $otp,
                    'verification_time' => $now,
                    'dob' => (isset($data->dob)) ? $data->dob :'',
                    'gender' => (isset($data->gender)) ? $data->gender :'',
                    'email' => $data->email,
                    'password' => Hash::make($data->password),
                    'app_token'         => $user_token,
                    'login_type' => 'O',
                    'active' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                
                $path = $functions->_getUserFolderName($user_id, "public/videos");
                $path2 = $functions->_getUserFolderName($user_id, "public/photos");
                $path3 = $functions->_getUserFolderName($user_id, 'public/profile_pic');
                $path4 = $functions->_getUserFolderName($user_id, 'public/sounds');
                $video_gif_path = "public/videos/".$user_id.'/gif';        
                $folderExists = Storage::exists($video_gif_path);
                if(!$folderExists){
                    Storage::makeDirectory($video_gif_path);
                }
                $video_thumb_path = "public/videos/".$user_id.'/thumb';        
                $folderExistsThumb = Storage::exists($video_thumb_path);
                if(!$folderExistsThumb){
                    Storage::makeDirectory($video_thumb_path);
                }
                if ($data->hasFile('profile_pic_file'))
                {
                    try
                    {
                        $image_file = $data->file('profile_pic_file');

                        if ($image_file->isvalid())
                        {
                            $path = 'public/profile_pic/' . $user_id;
                            $filenametostore = request()->file('profile_pic_file')
                            ->store($path);
                            Storage::setVisibility($filenametostore, 'public');
                            $fileArray = explode('/', $filenametostore);
                            $fileName = array_pop($fileArray);
                            // dd(asset(Storage::url('public/profile_pic/'.$user->user_id.'/'.$fileName)));
                            $functions->_cropImage($image_file, 300, 300, 0, 0, $path . '/small', $fileName);
                            // dd(66);
                            $file_path = asset(Storage::url('public/profile_pic/' . $user_id . "/" . $fileName));

                            $small_file_path = asset(Storage::url('public/profile_pic/' . $user_id . "/small/" . $fileName));
                            if ($file_path == "")
                            {
                                $file_path = asset(config('app.profile_path')) . 'default-user.png';
                            }
                            if ($small_file_path == "")
                            {
                                $small_file_path = asset(config('app.profile_path')) . 'default-user.png';
                            }

                            DB::table('users')->where('user_id', $user_id)->update(['user_dp' => $fileName]);
                        }
                    }
                    catch(Exception $ex)
                    {
                        dd($ex->getMessage());
                        return redirect()
                        ->back();
                    }
                }
                
                $site_title =Functions::getSiteTitle();
                
                
                $mailBody = '
                <p>Dear <b>'.  $data->email .'</b>,</p>
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
                    Mail::to($data->email)->send(new SendMail($array)); 
                } 
                $msg = "An OTP has been sent to your Email";
                // $id = $user_id;
                $data  = array( 'user_id' => $user_id, 'app_token' => $user_token,'username' =>strtolower(strip_tags($data->username)) );
                $msg = "An OTP has been sent to your Email";
                DB::table('notification_settings')->insert(['user_id' => $user_id]);
                $response = array("status" => "success",'msg'=>$msg ,'content' => $data);      
                return response()->json($response); 
            }else{
                return response()->json(['status'=>'error','msg'=> "Registration failed. Please Contact to administrator."]);
            }
        }
    }
    
    public function getAccessTokenBasedOnUserEmail($email) {
        
        $accessToken = '';
        $user = User::whereEmail($email)->first();
        if($user) {
            if ( !$userToken=JWTAuth::fromUser($user) ) {
                $accessToken = '';
            } else {
                $accessToken = $userToken;
            }
        }
        return $accessToken;
        
    }
    
    public function isEmailExist(Request $request){
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
            $chkRecord=DB::table('users')->where('email',$request->email)->exists();
            if($chkRecord){
                return response()->json(['status'=>'success','isEmailExist'=>1]);
            }else{
                return response()->json(['status'=>'success','isEmailExist'=>0]);
            }
        }
    }
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email'          => 'required|email',
            'password'       => 'required',
        ],
        [
            'email.required'   => 'Email is required',
            'email.email'      => 'Email id is not valid',
        ]);

        if(!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
        }else{
            $functions = new Functions();
            
            $credentials = request(['email', 'password']);
            
            if (! $token = auth('api')->attempt($credentials)) {
                return response()->json(['status'=>false,'msg' => 'Invalid Username or Password'], 200);
            }
            $data=auth('api')->user();
            
            // $existingRecord = DB::table('users')
            // ->select(DB::raw("user_id,password,username,fname,lname,email,user_dp"))
            // ->whereRaw(DB::raw("email = '".$request->email."'"))
            // ->first();
            // if($existingRecord) {
                // $checkIfActive = DB::table('users')
                // ->select(DB::raw("user_id,app_token,active"))
                // ->whereRaw(DB::raw("email = '".$request->email."' and email_verified= 1 "))
                // ->first();
            if($data->email_verified==1){
                if($data->active==1){
                        // if(Hash::check($request->password, $existingRecord->password)) {
                            // $user_token = Hash::make($functions->_password_generate(20));
                    $now  = date("Y-m-d H:i:s");
                    $data2 = array(
                                // 'app_token'         => $token,
                        'time_zone'         => $request->time_zone,
                        'updated_at'        => $now
                    );
                    DB::table('users')
                    ->where('user_id', $data->user_id)
                    ->update($data2);
                        //     if(stripos($data->user_dp,'https://')!==false){
            	           //     $file_path=$data->user_dp;
            	           //     $small_file_path=$data->user_dp;
            	           // }else{
            	           //     $file_path = asset(Storage::url('public/profile_pic/'.$data->user_id."/".$data->user_dp));
                        //         $small_file_path = asset(Storage::url('public/profile_pic/'.$data->user_id."/small/".$data->user_dp));
                        //         if($file_path==""){
                        //             $file_path=asset('default/default.png');
                        //         }
                        //         if($small_file_path==""){
                        //             $small_file_path=asset('default/default.png');
                        //         }
            	           // }
                    return $this->respondWithToken($token);
                    
                            // $data  = array( 'user_id' => $data->user_id, 'app_token' => $token,'username' =>$data->username,'email'=>$data->email,'fname'=>$data->fname,'lname'=>$data->lname,'user_dp'=>$file_path );
                        // }else{
                        //     $msg = "Invalid password.";
                        //     $response = array("status" => "error",'msg'=>$msg );      
                        //     return response()->json($response); 
                        // }
                }else{
                    $msg = "this account is deactivated";
                    $response = array("status" => "error",'msg'=>$msg );      
                    return response()->json($response); 
                }
                
            }else{
                $data = DB::table('users')
                ->select(DB::raw("user_id,app_token"))->where('email',$request->email)->first();
                $msg = "You have not verified your registered email for the account.";
                $response = array("status" => "email_not_verified", 'msg'=>$msg ,'content'=>$data);      
                return response()->json($response); 
            }
            // }else{
            //     $msg = "Your Account does not exist";
            //     $response = array("status" => "error",'msg'=>$msg );      
            //     return response()->json($response); 
            // }

            $msg = "User logged in successfully";
            $response = array("status" => "success",'msg'=>$msg ,'content' => $data);      
            return response()->json($response); 
        }
    }
    
    public function me()
    {
        return response()->json(auth('api')->user());
    }
    
    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }
    
    protected function respondWithToken($token)
    {
     $data=array();
     $data=auth('api')->user();
    //   dd($data);
     $file_path = '';
     $small_file_path = '';
     if($data->user_dp!='' && $data->user_dp!=null) {
         if(stripos($data->user_dp,'https://')!==false){
            $file_path=$data->user_dp;
            $small_file_path=$data->user_dp;
        }else{
            $file_path = asset(Storage::url('public/profile_pic/'.$data->user_id."/".$data->user_dp));
            $small_file_path = asset(Storage::url('public/profile_pic/'.$data->user_id."/small/".$data->user_dp));
            if($file_path==""){
                $file_path=asset('default/default.png');
            }
            if($small_file_path==""){
                $small_file_path=asset('default/default.png');
            }
        }
    }
    
    return response()->json([
        'status' => "success",
        'msg' => 'User login successfully!',
        'content' => array(
            'user_id'           => $data->user_id,
            'username'          => $data->username,
            'fname'             => $data->fname,
            'lname'             => $data->lname,
            'email'             => $data->email,
            'mobile'            => $data->mobile,
            'dob'               => $data->dob,
            'gender'            => $data->gender,
            'image'           => $file_path,                    
            'country'           => $data->country,
            'login_type'        => $data->login_type,
            'small_pic'     => $small_file_path,
            'user_dp'     => $file_path,
            'bio'        => ($data->bio == null) ? '' : $data->bio,
            'app_token' => $token,
            'expires_in' => strtotime(date("Y-m-d",strtotime('+'.auth('api')->factory()->getTTL().' minutes')))
        ),
    ]);
}

public function resendOtp(Request $request){

    $validator = Validator::make($request->all(), [
        'user_id'          => 'required'
    ],[
        'user_id.required' => 'User is required'
    ]);
    
    if (!$validator->passes()) {
        return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
    }else{
        $userRecord = DB::table('users')
        ->select("user_id","mobile","email")
        ->where('user_id',$request->user_id)
        ->first();

        if($userRecord) {
            $otp= mt_rand(100000, 999999);
            $now  = date("Y-m-d H:i:s");
            DB::table('users')
            ->where('user_id', $userRecord->user_id)
            ->update(['verification_code' => $otp, 'verification_time' => $now]);
            $site_title =Functions::getSiteTitle();
            
            
            $mailBody = '
            <p>Dear <b>'.  $userRecord->email .'</b>,</p>
            <p style="font-size:16px;color:#333333;line-height:24px;margin:0">Use the OTP to verify your email address.</p>
            <h3 style="color:#333333;font-size:24px;line-height:32px;margin:0;padding-bottom:23px;margin-top:20px;text-align:center">'
            .$otp.'</h3>
            <br/><br/>
            <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you<br /><br/>'.$site_title.'</p>
            ';
            // dd($mailBody);
            // $ref_id
            $array = array('subject'=>'OTP Email Verification - '.$site_title,'view'=>'emails.site.company_panel','body' => $mailBody);
            Mail::to($userRecord->email)->send(new SendMail($array));  
                    /*if($userRecord->mobile!='') {
                        Functions::sendSMS("91".$userRecord->mobile, $otp.' is your OTP to verify your account with LeukeVideos. Valid for 10 minutes. Do not share with anyone');    
                    }*/
                    
                    $response = array( "status" => "success", "msg" => "An OTP has been sent to your Mobile or Email", 'user_id' => $userRecord->user_id ); 
                } else {
                    $response = array( "status" => "failed", "msg" => "Invalid user!" );
                }

                
                return response()->json($response);   
            }
        }

        public function loginProfileInformation(Request $request){
            
            if (auth()->guard('api')->user()) {
             $user_id=auth()->guard('api')->user()->user_id;
             $userRecord = DB::table('users')
             ->select(DB::Raw("*,ifnull(dob,'".date('Y-m-d', strtotime('-13 years'))."') as dob,ifnull(bio,'') as bio"))
             ->where('user_id',$user_id)
             ->first();
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
        
        
        if($userRecord) {
           $custom = collect(['large_pic'=>$file_path,'small_pic' => $small_file_path]);
           $userRecord = $custom->merge($userRecord);
           $response = array( "status" => "success", "content" => $userRecord , 'large_pic' => $file_path ,'small_pic' => $small_file_path ); 
       } else {
           $response = array( "status" => "failed", "msg" => "Invalid user!" );
       }
       
       return response()->json($response);   
   }
}
public function fetchUserInformation(Request $request){
  $validator = Validator::make($request->all(), [
    'user_id'          => 'required'
],[
    'user_id.required' => 'User is required'
]);
  
  if (!$validator->passes()) {
    return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
}else{
   $userRecord = DB::table('users')
   ->select(DB::Raw("*,ifnull(dob,'".date('Y-m-d', strtotime('-13 years'))."') as dob,ifnull(bio,'') as bio"))
   ->where('user_id',$request->user_id)
   ->first();
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


if($userRecord) {
   $custom = collect(['large_pic'=>$file_path,'small_pic' => $small_file_path]);
   $userRecord = $custom->merge($userRecord);
   $response = array( "status" => "success", "content" => $userRecord , 'large_pic' => $file_path ,'small_pic' => $small_file_path ); 
} else {
   $response = array( "status" => "failed", "msg" => "Invalid user!" );
}

return response()->json($response);   
}
}

public function updateUserInformation(Request $request){
  $validator = Validator::make($request->all(), [
    
    'username'          => 'required',
    'name'          => 'required',
    'email'          => 'required',
            // 'mobile'          => 'required',
    'gender'          => 'required',
    'dob'          => 'required',
],[
    'username.required' => 'Username is required',
            //'username.unique' => 'Username is already taken, try another one.',
    'name.required' => 'Name is required',
    'email.required' => 'Email is required',
    'mobile.required' => 'Mobile is required',
    'gender.required' => 'Gender is required',
    'dob.required' => 'DOB is required',
]);
  
  if (!$validator->passes()) {
    return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
}else{
    
    if(auth()->guard('api')->user()){
        $user_id= auth()->guard('api')->user()->user_id;
        $userRecord = DB::table('users')
        ->select("*")
        ->where('user_id',$user_id)
        ->first();
        
        if($userRecord) {
          $nameArr = explode(" ",$request->name);
          $fname = $nameArr[0];
          if( isset($nameArr[1]) ) {
              $lname = $nameArr[1];    
          } else {
              $lname = '';
          }
          
          DB::table('users')
          ->where('user_id', $userRecord->user_id)
          ->update(['username' => strtolower($request->username),'fname' => $fname,'lname' => $lname,'email' => $request->email,'mobile' => $request->mobile !=null ? $request->mobile : '','bio' => strip_tags($request->bio),'gender' => $request->gender,'dob' => date('Y-m-d',strtotime($request->dob))]);
          
          $user = DB::table('users')
          ->select("*")
          ->where('user_id',$userRecord->user_id)
          ->first();
          
          $file_path = '';
          $small_file_path = '';
          
          
          if($user->user_dp!='' && $user->user_dp!=null) {
             if(stripos($user->user_dp,'https://')!==false){
                $file_path=$user->user_dp;
                $small_file_path=$user->user_dp;
            }else{
                $file_path = asset(Storage::url('public/profile_pic/'.$user->user_id."/".$user->user_dp));
                $small_file_path = asset(Storage::url('public/profile_pic/'.$user->user_id."/small/".$user->user_dp));
                if($file_path==""){
                    $file_path=asset('default/default.png');
                }
                if($small_file_path==""){
                    $small_file_path=asset('default/default.png');
                }
            }
        }
        
        $user_content = array(
                        // 'user_id'           => $user->user_id,
                        // 'username'          => strtolower(strip_tags($user->username)),
                        // 'fname'             => strip_tags($user->fname),
                        // 'lname'             => strip_tags($user->lname),
                        // 'email'             => $user->email,
                        // 'mobile'            => ($user->mobile !=null) ? $user->mobile : '',
                        // 'dob'               => $user->dob,
                        // 'active'            => $user->active,
                        // 'gender'            => $user->gender,
                        // 'user_dp'           => $user->user_dp,                    
                        // // 'app_token'         => $user->app_token,
                        // 'country'           => strip_tags($user->country),
                        // 'languages'         => $user->languages,
                        // 'player_id'         => '',
                        // 'timezone'          => $user->time_zone,
                        // 'login_type'        => $user->login_type,
                        // 'ios_uuid'         => $user->ios_uuid,
                        // 'last_active'       => Functions::fSafeChar($user->last_active),
            
            'user_id'           => $user->user_id,
            'username'          => strtolower(strip_tags($user->username)),
            'fname'             => strip_tags($user->fname),
            'lname'             =>  strip_tags($user->lname),
            'email'             => $user->email,
            'mobile'            => ($user->mobile !=null) ? $user->mobile : '',
            'dob'               => $user->dob,
            'gender'            => $user->gender,
            'image'           => $file_path,                    
            'country'           => $user->country,
                    // 'state'           => $user->state,
                    // 'city'           => $user->city,
            'login_type'        => $user->login_type,
            'small_pic'     => $small_file_path,
            'user_dp'     => $file_path,
            'bio'        => ($user->bio == null) ? '' : $user->bio,
            'app_token'  => auth('api')->refresh()
                    // 'address'        => ($user->address == null) ? '' : $user->address,
                    // 'app_token' => $token,
                    // 'expires_in' => strtotime(date("Y-m-d",strtotime('+'.auth('api')->factory()->getTTL().' minutes')))
            
        );
        $response = array( "status" => "success", "msg" => "User information updated successfully.", "content" => $user_content ); 
    } else {
       $response = array( "status" => "failed", "msg" => "Invalid user!" );
   }
   
   return response()->json($response);   
}else{
    return response()->json([
        "status" => "error", "msg" => "Unauthorized user!"
    ]);
}
}
}

public function socialLogin(Request $request){
    $email = $request->email;
    $functions = new Functions();
    $isRecord = false;
    if($request->login_type == "A") {
        if(isset($request->ios_uuid)) {
                //$user = DB::table("users")->where("ios_uuid",$request->ios_uuid)->first();
            $user = User::where("ios_uuid",$request->ios_uuid)->first();
            if($user) {
                DB::table("users")->where('user_id',$user->user_id)->update(['login_type'=>$request->login_type]);
                $isRecord = true;
            }elseif(isset($request->email)){
                $user = DB::table("users")->where("email",$request->email)->first();
                if($user) {
                    DB::table("users")->where('user_id',$user->user_id)->update(['ios_uuid'=>$request->ios_uuid,'login_type'=>$request->login_type]);
                    $isRecord = true;
                }else{
                    $isRecord=false;
                }
            } else {
                $isRecord = false;
            }
        }
    }else{
        $user = User::whereRaw(DB::raw("(email='".$email."') and email<>''"))->first();
            // $user = DB::table("users")->whereRaw(DB::raw("(email='".$email."') and email<>''"))->first();
        if($user){
            $isRecord = true;  
        } else {
            $isRecord = false; 
        }
    }
    if($isRecord){
        if($user->active==1){
            $ios_uuid = "";
            if($request->login_type == "A") {
                $ios_uuid = $request->ios_uuid;    
            }
            $uniques_user_id_res = DB::table("unique_users_ids")->select("unique_id","user_id","unique_token")->where('unique_token',$request->unique_token)->first();
            if($uniques_user_id_res){
                DB::table('unique_users_ids')
                ->where('unique_token',$request->unique_token)
                ->update(['user_id'=>$user->user_id]);
                DB::table('video_views')
                ->where('unique_id',$uniques_user_id_res->unique_id)
                ->where('user_id',0)
                ->update(['user_id'=>$user->user_id]);
            }else{
                DB::table('unique_users_ids')->insert(['unique_token'=>$request->unique_token,'user_id'=>$user->user_id]);   
            }
            $accessToken = $this->getAccessTokenBasedOnUserEmail($user->email);
            
            if ($accessToken=="") {
                return response()->json(['status'=>false,'msg'=> 'Some error occur in our server. please try again.']);
            }
                        // $data=auth('api')->user();
                        // print_r($data);
                        // exit;

            $now  = date("Y-m-d H:i:s");
            $data2 = array(
                'time_zone'         => $request->time_zone,
                'updated_at'        => $now
            );
            DB::table('users')
            ->where('user_id', $user->user_id)
            ->update($data2);

            
            
            if(stripos($user->user_dp,'https://')!==false){
               $file_path=$user->user_dp;
               $small_file_path=$user->user_dp;
           }else{
               $file_path = asset(Storage::url('public/profile_pic/'.$user->user_id."/".$user->user_dp));
               $small_file_path = asset(Storage::url('public/profile_pic/'.$user->user_id."/small/".$user->user_dp));
               if($file_path==""){
                $file_path=asset('default/default.png');
            }
            if($small_file_path==""){
                $small_file_path=asset('default/default.png');
            }
        }
        $user_content = array(
            'user_id'           => $user->user_id,
            'username'          => strtolower(strip_tags($user->username)),
            'fname'             => strip_tags($user->fname),
            'lname'             => strip_tags($user->lname),
            'email'             => $user->email,
            'mobile'            => ($user->mobile !=null) ? $user->mobile : '',
            'dob'               => $user->dob,
            'active'            => $user->active,
            'gender'            => $user->gender,
            'user_dp'           => $file_path,                    
            'app_token'         => $accessToken,
            'country'           => $user->country,
            'languages'         => $user->languages,
            'player_id'         => Functions::fSafeChar($request->player_id),
            'timezone'          => $user->time_zone,
            'login_type'        => $request->login_type,
            'ios_uuid'          => $ios_uuid,
            'last_active'       => Functions::fSafeChar($user->last_active),
        );
        
                    // $is_following_videos=0;
                    // $followingVideos = DB::table("follow")
                    // ->select(DB::raw("follow_id"))
                    // ->where("follow_by",$user->user_id)
                    // ->first(); 
                    // if($followingVideos) {
                    //     $is_following_videos = 1;
                    // }
                    // $user_content['is_following_videos']=$is_following_videos;
        
        $response = array("status" => "success",'msg'=>'Social login successfully' ,'content' => $user_content); 
    }else{
        $response = array("status" => "error",'msg'=>'Account inactive'); 
    }
}
else{
    $max_id = 1001;
    $username = "user" . $max_id;
    while(DB::table("users")->select("user_id")->where("username",$username)->first())
    {
        $max_id++;
        $username = "user" . $max_id;
    }
    $user_token = Hash::make($functions->_password_generate(20));
    $now  = date("Y-m-d H:i:s");
    $ios_uuid = "";
    if($request->login_type == "A") {
        $ios_uuid = $request->ios_uuid;    
    }
    $gender = "";
    if($request->gender != null && $request->gender != "") {
        if(strtolower($request->gender) == "male" || strtolower($request->gender) == "m") {
            $gender = "m";
        } else if(strtolower($request->gender) == "female" || strtolower($request->gender) == "f") {
            $gender = "f";
        } else {
            $gender = "ot";
        }
    }
    $data = array(
        'username'          => $username,
        'fname'             => strip_tags($request->fname),
        'lname'             => strip_tags($request->lname),
        
        'active'            => '1',
        'gender'            => $gender,
        'app_token'         => $user_token,
        'country'           => Functions::fSafeChar($request->country),
        'languages'         => Functions::fSafeChar($request->languages),
        'time_zone'         => Functions::fSafeChar($request->timezone),
        'user_dp'           => Functions::fSafeChar($request->user_dp),
        'login_type'        => $request->login_type,
                // 'login_type'        => 'FB',
        'created_at'        => $now,
        'updated_at'        => $now,
        'ios_uuid'         => $ios_uuid,
        'email_verified'   => 1
    );

    if(isset($request->dob) && $request->dob!=''){
        $data['dob']     =  date("Y-m-d", strtotime($request->dob));
    }
    if(isset($request->email)){
        $data['email'] = $request->email;
    }

    if(isset($request->mobile)){
        $data['mobile'] = $request->mobile;
    }

            // if($request->login_type == "A") {
    $apple_user_id = DB::table("users")->insertGetId($data);
    $data['user_id'] = $apple_user_id;
    $accessToken = $this->getAccessTokenBasedOnUserEmail($request->email);
    if($accessToken == "") {
        return response()->json(['status'=>false,'msg'=> 'Some error occur in our server. please try again.']);
    }
    $user_content = array(
        'user_id'           => $apple_user_id,
        'username'          => $username,
        'fname'             => strip_tags($request->fname),
        'lname'             => strip_tags($request->lname),
        
        'active'            => '1',
        'gender'            => $gender,
        'app_token'         => $accessToken,
        'country'           => Functions::fSafeChar($request->country),
        'languages'         => Functions::fSafeChar($request->languages),
        'time_zone'         => Functions::fSafeChar($request->timezone),
        'user_dp'           => Functions::fSafeChar($request->user_dp),
        'login_type'        => $request->login_type,
        'created_at'        => $now,
        'updated_at'        => $now,
        'ios_uuid'         => $ios_uuid,
        'email_verified'   => 1
    );
    DB::table('notification_settings')->insert(['user_id' => $apple_user_id]);
    $response = array("status" => "success",'msg'=>'Social login successfully' ,'isRecord' => false,'content' => $user_content); 
}

return response()->json($response); 

}

public function verifyOtp(Request $request){
    $otp = $request->otp;
    if(strlen($otp)<=6){
        $user_id= $request->user_id;
        $app_token = $request->app_token;
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $chk = DB::table("users")->select(DB::raw("user_id,case when user_dp !='' THEN case when INSTR(user_dp,'https://') > 0 THEN user_dp ELSE concat('".$userDpPath."/',user_id,'/small/',user_dp)  END ELSE '' END as user_dp,app_token,fname,lname,mobile,email,gender,ifnull(dob,'NA') as dob,username,verification_time,verification_code"))->where("user_id",$user_id)->whereNotNull("verification_time")->first();
        
        if($chk){
            $now = date('Y-m-d H:i:s');
            $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $chk->verification_time);
            $datetime->modify('+10 minutes');
            $expiryTime= $datetime->format('Y-m-d H:i:s');
            $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $chk->verification_time);
            $datetime->modify('+10 minutes');
            $expiryTime= $datetime->format('Y-m-d H:i:s');
            if(strtotime($now) > strtotime($expiryTime)){
                $response = array("status" => "error",'msg'=>'Otp Expired');      
            }else{
                if(($chk->verification_code) != trim($otp)){
                    $response = array("status" => "error",'msg'=>'Otp doesn\'t match.');      
                }else{
                            // dd($chk);
                    $accessToken = $this->getAccessTokenBasedOnUserEmail($chk->email);
                            // dd($chk->email);
                    if($accessToken == "") {
                        return response()->json(['status'=>false,'msg'=> 'Some error occur in our server. please try again.']);
                    }
                    unset($chk->verification_time);
                    unset($chk->verification_code);
                    DB::table("users")->where("user_id",$user_id)->update(array("active"=>'1',"email_verified"=>'1','verification_code'=>'','verification_time'=>null));
                            // $response = array("status" => "success",'msg'=>'Profile activated successfully. Proceed to Login', 'content' => $user_content);   
                    $user = User::find($chk->user_id);

                    auth()->guard("api")->login($user);
                    return $this->respondWithToken($accessToken);
                }
            }
        }else{
            $response = array("status" => "error",'msg'=>'OTP expired');      
        }
    }else{
        $response = array("status" => "error",'msg'=>'Otp should be of 6 digits');      
    }
    
    return response()->json($response);
    
}

public function socialRegister(Request $data){
    $rules = [
        'email' => 'required|email|unique:users',
        'fname' => 'required',
            // 'gender' => 'required',
        'lname' => 'required',
        'username' => 'required|unique:users',
        'password' => 'required|min:6',            
    ];
    
        // if($data->login_type!='A'){
        //     $rules['email'] = 'required|email|unique:users';
        // }
    
    $messages = [ 
        'username.unique' => 'This username is already taken.',
        'password.confirmed' => 'Password doesn\'t match.',
        'email.unique' => 'Your Email is already registered please login.',
        'email.email' => 'Email id is not valid.'
    ];
    
        // if($data->login_type!='A'){
        //     $messages['email.unique'] = 'Your Email is already registered please login.';
        //     $messages['email.email'] = 'Email id is not valid.';
        // }
    
    $validator = Validator::make(
        $data->all() , 
        $rules,
        $messages
    );
    if (!$validator->passes())
    {
        return response()
        ->json(['status' => 'error', 'msg' => $this->_error_string($validator->errors()
            ->all()) ]);
    }
    else
    {
        
        if ($data->password == $data->confirm_password)
        {
            $functions = new Functions();
            $now = date("Y-m-d H:i:s");
            $otp = mt_rand(100000, 999999);
            $user_token = Hash::make($functions->_password_generate(20));
            
            if (isset($data->profile_pic))
            {
                $profile_pic = $data->profile_pic;
            }
            else
            {
                $profile_pic = "";
            }
                    // $ios_uuid = "";
                    // if($data->login_type == "A") {
                    //     $ios_uuid = $data->ios_uuid;    
                    // }
            $res = array(
                'fname' => strip_tags($data->fname),
                'lname' => strip_tags($data->lname),
                'username' => strtolower(strip_tags($data->username)),
                'verification_code' => '',
                'verification_time' => null,
                'dob' => date('Y-m-d', strtotime($data->dob)),
                'email' => $data->email,
                'password' => Hash::make($data->password) ,
                        // 'gender' => $data->gender,
                'app_token' => $user_token,
                'login_type' => isset($data->login_type) ? $data->login_type : 'O',
                'user_dp' => $profile_pic,
                'active' => 1,
                        // 'ios_uuid' => $ios_uuid,
                'email_verified' => 1
            );
            if($data->login_type == "A") {
                $res['updated_at'] = $now;
                $dataRes=DB::table("users")->where('email',$data->email)->first();
                DB::table("users")->where('user_id',$dataRes->user_id)->update($res);
                $user_id=$dataRes->user_id;
                unset($res['updated_at']);
            }else{
                $res['created_at'] = $now;
                $res['updated_at'] = $now;
                $user_id = DB::table('users')->insertGetId($res);
                unset($res['created_at']);
                unset($res['updated_at']);
            }
            
            unset($res['password']);
            $res['user_id']=$user_id;
            $path = $functions->_getUserFolderName($user_id, "public/videos");
            $path2 = $functions->_getUserFolderName($user_id, "public/photos");
            $path3 = $functions->_getUserFolderName($user_id, 'public/profile_pic');
            $path4 = $functions->_getUserFolderName($user_id, 'public/sounds');
            $video_gif_path = "public/videos/" . $user_id . '/gif';
            $folderExists = Storage::disk('local')->exists($video_gif_path);
            if (!$folderExists)
            {
                Storage::disk('local')->makeDirectory($video_gif_path);
            }
            $video_thumb_path = "public/videos/".$user_id.'/thumb';        
            $folderExistsThumb = Storage::disk('local')->exists($video_thumb_path);
            if(!$folderExistsThumb){
                Storage::disk('local')->makeDirectory($video_thumb_path);
            }
            
            if ($data->hasFile('profile_pic_file'))
            {
                try
                {
                    $image_file = $data->file('profile_pic_file');
                    
                    if ($image_file->isvalid())
                    {
                        
                        $path = 'public/profile_pic/' . $user_id;
                        
                        $filenametostore = request()->file('profile_pic_file')
                        ->store($path);
                        Storage::setVisibility($filenametostore, 'public');
                        $fileArray = explode('/', $filenametostore);
                        $fileName = array_pop($fileArray);
                                // dd(asset(Storage::url('public/profile_pic/'.$user->user_id.'/'.$fileName)));
                        $functions->_cropImage($image_file, 300, 300, 0, 0, $path . '/small', $fileName);
                                // dd(66);
                        $file_path = asset(Storage::url('public/profile_pic/' . $user_id . "/" . $fileName));
                        
                        $small_file_path = asset(Storage::url('public/profile_pic/' . $user_id . "/small/" . $fileName));
                        if ($file_path == "")
                        {
                            $file_path = asset(config('app.profile_path')) . 'default-user.png';
                        }
                        if ($small_file_path == "")
                        {
                            $small_file_path = asset(config('app.profile_path')) . 'default-user.png';
                        }
                        
                        
                        $res['user_dp'] = $file_path;
                        
                        DB::table('users')->where('user_id', $user_id)->update(['user_dp' => $fileName]);
                    }
                }
                catch(Exception $ex)
                {
                    dd($ex->getMessage());
                    return redirect()
                    ->back();
                }
            }
            
            $msg = "User Register Successfully";
            $response = array(
                "status" => "success",
                'msg' => $msg,
                'content' => $res
            );
            return response()->json($response);
        }
        else
        {
            return response()->json(['status' => 'error', 'msg' => "Password and confirm password must be same."]);
        }
    }
}


}
