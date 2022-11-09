<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use App\Helpers\Common\Functions;
use Exception;
use App\Mail\SendMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    private function _error_string($errArray)
    {
        $error_string = '';
        foreach ($errArray as $key) {
            $error_string.= $key."\n";
        }
        return $error_string;
    }
    
    public function index()
    {   
        $recaptchaRecord=DB::table('google_captcha_key')->first();
        if($recaptchaRecord){
            $recaptcha=true;
        }else{
            $recaptcha=false;
        }
        return view('web.auth.register',compact('recaptcha',$recaptcha));
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    { 
        $before_date = date('Y-m-d', strtotime('-13 years'));
        return Validator::make($data, [
            'username' => ['required', 'string', 'max:255'],
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'mobile' => ['numeric', 'nullable'],
            'gender' => ['required', 'string', 'max:255'],
            'dob' => ['required', 'date', 'before_or_equal:' . $before_date],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'image' => ['mimes:jgp,png,mpeg'], 
        ]);
    }

//     public function register(Request $request) {
//         // Validate the form data
//         $rules = [
//             'username' => ['required', 'string', 'max:100'],
//             'fname' => ['required', 'string', 'max:100'],
//             'lname' => ['string', 'max:100'],
//             'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
//             'mobile' => ['numeric'],
//             'gender' => ['required'],
//             'dob' => ['required', 'date'],
//             'password' => ['required', 'string', 'min:8', 'confirmed'],
//         ];

//         $messages = [
//             'username.required'            => 'The username is required.',
//             'username.max'            => 'The username must be less than 100 characters.',
//             'fname.required'            => 'The first name is required.',
//             'gender.required'            => 'The gender is required.',
//             'dob.required'            => 'The dob is required.',
//             'dob.date'            => 'The dob must be of type date.',
//             'fname.max'            => 'The first name must be less than 100 characters.',
//             'lname.max'            => 'The last must be less than 100 characters.',
//             'mobile.numeric'            => 'The mobile must be numeric.',
//             'email.email'            => 'You must enter an email address',
//             'email.exists'            => 'The account with this email already exists',
//             'password.required'         => 'You cant leave Password field empty',
//             'password.min'              => 'Password has to be 6 chars long',
//             'password.confirmed'              => 'The old and the new password does not match.'
//         ];

//         try {
//             $this->validate($request, $rules, $messages);
// dd('abc');
//             User::create([
//                     'fname' => $request->fname,
//                     'lname' => $request->lname,
//                     'username' => $request->name,
//                     'gender' => $request->gender,
//                     'mobile' => $request->mobile,
//                     'dob' => $request->dob,
//                     'email' => $request->email,
//                     'password' => Hash::make($request->password),
//                 ]);

//         } catch(Exception $ex) {
//             dd($ex->getMessage());
//         }
        

//         return redirect()->route('web.home');
//     }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\User
     */
    protected function register(Request $data)
    {
        // dd($data);
        $recaptchaRecord=DB::table('google_captcha_key')->first();
        if($recaptchaRecord){
            if(isset($_POST['g-recaptcha-response'])){
                $captcha=$_POST['g-recaptcha-response'];
            }
            if(!$captcha){
                // echo '<h2>Please check the the captcha form.</h2>';
                return redirect(route('web.register'))->withInput($data->all())->with('error','Please check the the captcha form'); 
                exit;
            }
            $secretKey = config('app.google_captcha_secret_key');
            $ip = $_SERVER['REMOTE_ADDR'];
            // post request to server
            $url = 'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($secretKey) .  '&response=' . urlencode($captcha);
            $response = file_get_contents($url);
            $responseKeys = json_decode($response,true);
            // should return JSON with success as true
        }else{
            $responseKeys["success"]='success';
        }
          if($responseKeys["success"]) {
                
                // $mail_setting=DB::table('mail_types')->where('active',1)->first();
                // if((config('app.sendgrid_api_key') !="" || config('app.mail_host') !="") && isset($mail_setting)){

                    $validator = Validator::make($data->all(), [ 
                        'email'          => 'required|email|unique:users',
                        'username'          => 'required|unique:users,username',
                        'fname'     =>'required|regex:/^[\pL\s\-]+$/u',
                        'lname'     =>'required|regex:/^[\pL\s\-]+$/u'
                        // 'profile_pic' => 'required'
                    ],
                    [
                        'fname.required' => 'First name required',
                        'lname.required' => 'Last name required',
                        'email.email'		  	   => 'Email id is not valid',
                        'username.required'		  	   => 'Username is required'
                    ]);
            
                    if(!$validator->passes()) {
                        return redirect(route('web.register'))->withInput($data->all())->with('error',$this->_error_string($validator->errors()->all())); 
                    }else{

                        $functions = new Functions();
                        
                        // $user_id = DB::table("users")->select("user_id")->orderBy("user_id","desc")->first();
                        // if($user_id){
                        //     $max_id = $user_id->user_id;
                        // }else{
                        //     $max_id = 1001;
                        // }
                        // $username = "user" . $max_id;
                        // while(DB::table("users")->select("user_id")->where("username",$username)->first())
                        // {
                        //     $max_id++;
                        //     $username = "user" . $max_id;
                        // }

                $user = User::create([
                    'fname' => strip_tags($data->fname),
                    'lname' => strip_tags($data->lname),
                    'username' => strtolower(strip_tags($data->username)),
                    // 'gender' => $data->gender,
                    // 'mobile' => !empty($data->mobile) ? $data->mobile : '',
                    // 'dob' => date('Y-m-d', strtotime($data->dob)),
                    'email' => $data->email,
                    'password' => Hash::make($data->password),
                    'login_type' => 'O',
                    'active' => 1,
                    ]);
            
            //   $user =array(
            //                 'fname' => $data['fname'],
            //                 'lname' => $data['lname'],
            //                 'username' => $data['username'],
            //                 'gender' => $data['gender'],
            //                 'mobile' => !empty($data['mobile']) ? $data['mobile'] : '',
            //                 'dob' => date('Y-m-d', strtotime($data['dob'])),
            //                 'email' => $data['email'],
            //                 'password' => Hash::make($data['password']),
            //                 'login_type' => 'O',
            //                 'active' => 1,
            //                 'email_verified' => 0,
            //                 'created_at' => date('Y-m-d'),
            //                 'updated_at' => date('Y-m-d')
            //             );
                    
            //             $id=DB::table('users')->insertGetId($user);
                // $image_file = $data->profile_pic;

                // try {

                //     if($image_file->isvalid()) {
                //         $functions = new Functions();
                    
                // 	$path = 'public/profile_pic/'.$user->user_id;
                    
                // 	$filenametostore = request()->file('profile_pic')->store($path);  
                // 	Storage::setVisibility($filenametostore, 'public');
                // 	$fileArray = explode('/',$filenametostore);  
                // 	$fileName = array_pop($fileArray); 
                // 	$functions->_cropImage(asset(Storage::url('public/profile_pic/'.$user->user_id.'/'.$fileName)),500,500,0,0,$path.'/small',$fileName);
                // 	$file_path = asset(config('app.profile_path').$user->user_id."/".$fileName);
                // 	$small_file_path = asset(config('app.profile_path').$user->user_id."/small/".$fileName);
                // 	if($file_path==""){
                // 		$file_path=asset(config('app.profile_path')).'default-user.png';
                // 	}
                // 	if($small_file_path==""){
                // 		$small_file_path=asset(config('app.profile_path')).'default-user.png';
                // 	}
                    
                // 	$data2 =array(
                // 		'user_id'       => $user->user_id,
                // 		'image'         => $fileName				
                // 	); 
                    
                // 	DB::table('users')
                // 	->where('user_id', $user->user_id)
                // 	->update(['user_dp'=>$fileName]);
                //     }
                // } Catch(Exception $ex) {
                //     // dd($ex->getMessage());
                //     return redirect()->back();
                // }

                if (Auth::guard('web')->check()) {
                    Auth::guard('web')->logout();
                }

                $path = $functions->_getUserFolderName($user->user_id, "public/videos");
                $path2 = $functions->_getUserFolderName($user->user_id, "public/photos");
                $path3 = $functions->_getUserFolderName($user->user_id, 'public/profile_pic');
                $path4 = $functions->_getUserFolderName($user->user_id, 'public/sounds');
                $video_gif_path = "public/videos/".$user->user_id.'/gif';        
                $folderExists = Storage::exists($video_gif_path);
                if(!$folderExists){
                    Storage::makeDirectory($video_gif_path);
                }
                $video_thumb_path = "public/videos/".$user->user_id.'/thumb';        
                    
                $folderExistsThumb = Storage::exists($video_thumb_path);
                if(!$folderExistsThumb){
                    Storage::makeDirectory($video_thumb_path);
                }
                $url= URL::temporarySignedRoute(
                    'web.email-verify', now()->addMinutes(30), ['user' => $user->user_id]
                );
                    $site_title=Functions::getSiteTitle();
                    $mailBody = '
                    <p>Dear <b>'.  $data->email .'</b>,</p>
                    <p style="font-size:16px;color:#333333;line-height:24px;margin:0">Click the below button for Email Verification.</p>
                    <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px;margin-top:20px;text-align:center">
                        <a style="padding:10px;background:#007bff;color:#fff;border-radius:5px;cursor:pointer;text-decoration:none;" href="'.$url.'"> Verify Email </a>
                    </p>
                    <br/><br/>
                    <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you<br />'.$site_title.'<br/></p>
                    ';
                    // dd($mailBody);
                    // $ref_id
                    $array = array('subject'=>'Welcome to '.$site_title,'view'=>'emails.site.company_panel','body' => $mailBody);

                    if(strpos($_SERVER['SERVER_NAME'], "localhost")===false && strpos($_SERVER['SERVER_NAME'], "leukewebpanel.local")===false){
                       Mail::to($data->email)->send(new SendMail($array)); 
                    } 
                    return redirect(route('web.register'))->with('success','Mail has been sent to your email address. Please check email to varify your email.'); 
                    }
                // }else{
                //     return redirect(route('web.register'))->with('error','Registration failed. Please Contact to administrator.'); 
                // }
            } else {
                return redirect(route('web.register'))->with('error','Invalid Captcha.'); 
        }
    
        // Auth::guard('web')->loginUsingId($user->user_id);
        
        // return $user;
    }

    public function emailVerify($id){
        DB::table('users')->where('user_id',$id)->update(['email_verified'=> 1]);
        return redirect()->route('web.login')->with('success','Email Verified Successfully.');
    }
}
