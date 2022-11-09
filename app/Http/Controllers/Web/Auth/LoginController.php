<?php

namespace App\Http\Controllers\Web\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Storage;
use Auth;
use Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Exception;
use Laravel\Socialite\Facades\Socialite;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
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
        $this->middleware('guest:web')->except('logout');
    }

    private function _error_string($errArray)
    {
        $error_string = '';
        foreach ($errArray as $key) {
            $error_string .= $key . "\n";
        }
        return $error_string;
    }

    public function showLoginForm()
    {
        $mailRes = DB::table('mail_types')->where('active', 1)->count();
        $mailSet = 0;
        if ($mailRes > 0) {
            $mailSet = 1;
        }
        return view('web/auth/login', ['mailSet' => $mailSet]);
    }

    public function login(Request $request)
    {
        // dd($request->all());
        // Validate the form data
        $rules = [
            'email'   => 'required|email|exists:users,email',
            'password' => 'required|min:6'
        ];

        $messages = [
            'email.required'            => 'You cant leave User name field empty',
            'email.email'            => 'You must enter an email address',
            'email.exists'            => 'The account with this email doesnot exists',
            'password.required'         => 'You cant leave Password field empty',
            'password.min'              => 'Password has to be 6 chars long'
        ];

        $this->validate($request, $rules, $messages);

        $remember = !empty($request->remember) ? 1 : 0;
        // Attempt to log the user in
        $user_detail = DB::table('users')->select('email_verified', 'active')->where('email', $request->email)->first();

        if (isset($user_detail)) {
            if ($user_detail->email_verified == 0) {
                return redirect()->back()->withInput($request->only('email', 'remember'))->with(['error' => 'Email Not Verified.', 'verified' => 'email not verified']);
            } else {
                if ($user_detail->active == 0) {
                    return redirect()->back()->withInput($request->only('email', 'remember'))->with('error', 'Account is not active.');
                }
                if (Auth::guard('web')->attempt(['email' => $request->email, 'password' => $request->password], $remember)) {
                    //   Artisan::call('cache:clear');
                    // Artisan::call('config:clear');
                    // Artisan::call('view:clear');
                    // Artisan::call('route:clear');
                    // Artisan::call('storage:link');
                    // return redirect()->intended(route('web.home')); 
                    $user = Auth::guard('web')->user();
                    $cookieExists = Cookie::get('videoViewed');
                    // dd($cookieExists);
                    if ($cookieExists != null) {
                        $unique_res = DB::table('unique_users_ids')
                            ->select('unique_id')
                            ->where('unique_token', $cookieExists)
                            ->where('user_id', 0)
                            ->first();
                        // dd($unique_res);
                        if ($unique_res) {
                            DB::table('unique_users_ids')->where('unique_id', $unique_res->unique_id)->update(['user_id' => $user->user_id]);
                        } else {
                            $uniqueRec = DB::table('unique_users_ids')
                                ->select('unique_id', 'unique_token')
                                // ->where('unique_token',$request->unique_token)
                                ->where('user_id', $user->user_id)
                                ->first();
                            if ($uniqueRec) {
                                $token = $uniqueRec->unique_token;
                                $cookieTime = time() + 60 * 60 * 24 * 30;
                            } else {
                                $token = str_random(32);
                                $cookieTime = time() + 60 * 60 * 24 * 30;
                                DB::table('unique_users_ids')->insert(['unique_token' => $token, 'user_id' => $user->user_id]);
                            }
                            // 30 days


                            Cookie::queue('videoViewed', $token, $cookieTime);

                            // DB::table('unique_users_ids')->where('unique_token',$request->unique_token)->update(['user_id' =>$user->user_id]);
                        }
                    }
                    return redirect()->route('web.userProfile', ['id' => $user->user_id]);
                }
            }
        }


        // if unsuccessful, then redirect back to the login with the form data
        return redirect()->back()->withInput($request->only('email', 'remember'))->with('error', 'Invalid login credentials');
    }

    public function loginEmailVerify($email)
    {

        $mail_setting = DB::table('mail_types')->where('active', 1)->first();
        if ((config('app.sendgrid_api_key') != "" || config('app.mail_host') != "") && isset($mail_setting)) {
            $user = DB::table('users')->select('user_id')->where('email', $email)->first();
            if ($user) {

                $url = URL::temporarySignedRoute(
                    'web.email-verify',
                    now()->addMinutes(30),
                    ['user' => $user->user_id]
                );
                $site_title = Functions::getSiteTitle();
                $mailBody = '
            <p>Dear <b>' .  $email . '</b>,</p>
            <p style="font-size:16px;color:#333333;line-height:24px;margin:0">Click the below button for Email Verification.</p>
            <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px;margin-top:20px;text-align:center">
                <a style="padding:10px;background:#007bff;color:#fff;border-radius:5px;cursor:pointer;text-decoration:none;" href="' . $url . '"> Verify Email </a>
            </p>
            <br/><br/>
            <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you<br />' . $site_title . '<br/></p>
            ';
                // dd($mailBody);
                // $ref_id
                $array = array('subject' => 'Welcome to ' . $site_title, 'view' => 'emails.site.company_panel', 'body' => $mailBody);

                if (strpos($_SERVER['SERVER_NAME'], "localhost") === false && strpos($_SERVER['SERVER_NAME'], "leukewebpanel.local") === false) {
                    Mail::to($email)->send(new SendMail($array));
                }
                return redirect(route('web.login'))->with('success', 'Mail has been sent to your email address. Please check email to varify your email.');
            } else {
                return redirect(route('web.login'))->with('error', 'Email not registered.');
            }
        } else {
            return redirect(route('web.login'))->with('error', 'Registration failed. Please Contact to administrator.');
        }
    }

    public function redirectToGoogle()
    {
        // $user_data =array(
        //     'fname' => 'sarita',
        //     'lname' => 'sahota',
        //     'username' => 'saritasahota',
        //     'email' => 'sarita@gmail.com',
        //     // 'user_dp' => $user->getAvatar(),
        //     'user_dp' => '',
        //     'login_type' => 'G',
        //     'active' => 1,
        //     'email_verified' =>1,
        //     'created_at' => date('Y-m-d'),
        //     'updated_at' => date('Y-m-d'),
        // );
        // return view("web.auth.updateLoginDetail",compact('user_data'));
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $user = Socialite::driver('google')->user();

            // $finduser = User::where('google_id', $user->id)->first();
            $findUser = User::where('email', $user->getEmail())->first();

            if ($findUser) {

                Auth::guard('web')->loginUsingId($findUser->user_id);
                return redirect()->route('web.home')->with('success', 'You are logged in successfully.');
            } else {

                // $newUser = User::create([
                //     'fname' => $user->getName(),
                //     'username' => $user->getNickname(),
                //     'email' => $user->getEmail(),
                //     'user_dp' => $user->getAvatar(),
                //     'login_type' => 'G',
                // ]);

                $user_data = array(
                    'fname' => $user->user['given_name'],
                    'lname' => $user->user['family_name'],
                    'username' => strtolower(preg_replace("/\s+/", "", $user->user['name'])),
                    'email' => $user->user['email'],
                    // 'user_dp' => $user->getAvatar(),
                    'user_dp' => $user->user['picture'],
                    'login_type' => 'G',
                    'active' => 1,
                    'email_verified' => 1,
                    'created_at' => date('Y-m-d'),
                    'updated_at' => date('Y-m-d'),
                );
                // return view("web.updateLoginDetail",compact('newUser'));
                return view("web.auth.updateLoginDetail", compact('user_data'));
                // $id=DB::table('users')->insertGetId($newUser);
                // $functions = new Functions();
                // $path = $functions->_getUserFolderName($id, "public/videos");
                // $path2 = $functions->_getUserFolderName($id, "public/photos");
                // $path3 = $functions->_getUserFolderName($id, 'public/profile_pic');
                // $path4 = $functions->_getUserFolderName($id, 'public/sounds');
                // $video_gif_path = "public/videos/".$id.'/gif';        

                // $folderExists = Storage::exists($video_gif_path);
                // if(!$folderExists){
                //     Storage::makeDirectory($video_gif_path);
                // }

                // Auth::guard('web')->loginUsingId($id);
                // return redirect()->route('web.home')->with('success','You are registered successfully.');
            }
        } catch (Exception $e) {
            return redirect()->route('web.login');
        }
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->redirect();
    }

    public function handleFacebookCallback()
    {
        try {

            $user = Socialite::driver('facebook')->user();

            $findUser = User::where('email', $user->getEmail())->first();

            if ($findUser) {

                Auth::guard('web')->loginUsingId($findUser->user_id);
                return redirect()->route('web.home')->with('success', 'You are Logged In successfully.');
            } else {

                $u_name = explode(' ', $user->getName());
                $f_name = '';
                $l_name = '';
                if (isset($u_name[0])) {
                    $f_name = $u_name[0];
                }
                if (isset($u_name[1])) {
                    $l_name = $u_name[1];
                }
                $user_data = array(
                    'fname' => $f_name,
                    'lname' => $l_name,
                    'username' => strtolower(preg_replace("/\s+/", "", $user->getName())),
                    'email' => $user->getEmail(),
                    // 'user_dp' => $user->getAvatar(),
                    'user_dp' => $user->getAvatar(),
                    'login_type' => 'F',
                    'active' => 1,
                    'email_verified' => 1,
                    'created_at' => date('Y-m-d'),
                    'updated_at' => date('Y-m-d'),
                );
                return view("web.auth.updateLoginDetail", compact('user_data'));
                // dd($newUser);
                // $id=DB::table('users')->insertGetId($newUser);

                // $functions = new Functions();
                // $path = $functions->_getUserFolderName($id, "public/videos");
                // $path2 = $functions->_getUserFolderName($id, "public/photos");
                // $path3 = $functions->_getUserFolderName($id, 'public/profile_pic');
                // $path4 = $functions->_getUserFolderName($id, 'public/sounds');
                // $video_gif_path = "public/videos/".$id.'/gif';        

                // $folderExists = Storage::exists($video_gif_path);
                // if(!$folderExists){
                //     Storage::makeDirectory($video_gif_path);
                // }

                // Auth::guard('web')->loginUsingId($id);
                // return redirect()->route('web.home')->with('success','You are registered successfully.');
            }
        } catch (Exception $e) {
            return redirect()->route('web.login');
        }
    }

    public function socialRegister(Request $request)
    {
        $before_date = date('Y-m-d', strtotime('-13 years'));
        $validator = Validator::make(
            $request->all(),
            [
                'username'  => 'required|unique:users,username',
                'fname'     => 'required|regex:/^[\pL\s\-]+$/u',
                'lname'     => 'required|regex:/^[\pL\s\-]+$/u',
                'email'          => 'required|email|unique:users',
                // 'username'       => 'required|unique:users',
                'password'           => 'required|same:confirm_password',
                'confirm_password'       => 'required',
                'dob' => 'required|before_or_equal:' . $before_date,
                // 'dob'            => 'required'
            ],
            [
                'email.unique'             => 'Your Email is already registered please login.',
                'email.email'              => 'Email id is not valid.',
                'username.unique'          => 'This username is already taken.',
                'password.required'              => 'Password is required',
                'confirm_password.required'        => 'Confirm Password is required',
                'dob.required'             => 'Date of birth is required.',
            ]
        );
        if (!$validator->passes()) {
            // dd($request->all());
            $user_data = array(
                'fname' => $request->fname,
                'lname' => $request->lname,
                'username' => $request->username,
                'email' => $request->email,
                // 'user_dp' => $user->getAvatar(),
                'user_dp' => $request->old_profile_pic,
                'login_type' => $request->login_type,
                'active' => 1,
                'email_verified' => 1,
                'created_at' => date('Y-m-d'),
                'updated_at' => date('Y-m-d'),
            );
            $request->session()->flash('error', $this->_error_string($validator->errors()->all()));
            // return view("web.updateLoginDetail",compact('newUser'));
            return view("web.auth.updateLoginDetail", compact('user_data'))->withInput($request->all())->with('error', $this->_error_string($validator->errors()->all()));
            // return redirect()->back()->withInput($request->all())->with('error',$this->_error_string($validator->errors()->all())); 
            // return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all())]);
        } else {
            $functions = new Functions();
            $f_name = '';
            $l_name = '';
            $user_dp = '';
            if (isset($request->fname)) {
                $f_name = $request->fname;
            }
            if (isset($request->lname)) {
                $l_name = $request->lname;
            }
            if (isset($request->old_profile_pic)) {
                if (!is_array($request->old_profile_pic)) {
                    $user_dp = $request->old_profile_pic;
                }
            }
            $user_token = Hash::make($functions->_password_generate(20));
            $newUser = array(
                'fname' => strip_tags($f_name),
                'lname' => strip_tags($l_name),
                'username' => strtolower(strip_tags($request->username)),
                'email' => $request->email,
                'user_dp' => $user_dp,
                'password' => Hash::make($request->password),
                'app_token'    => $user_token,
                'dob'    =>   $request->dob,
                'login_type' => isset($request->type) ? $request->type : 'O',
                'active' => 1,
                'email_verified' => 1,
                'created_at' => date('Y-m-d'),
                'updated_at' => date('Y-m-d'),
            );
            // dd($newUser);
            $id = DB::table('users')->insertGetId($newUser);

            $functions = new Functions();
            $path = $functions->_getUserFolderName($id, "public/videos");
            $path2 = $functions->_getUserFolderName($id, "public/photos");
            $path3 = $functions->_getUserFolderName($id, 'public/profile_pic');
            $path4 = $functions->_getUserFolderName($id, 'public/sounds');
            $video_gif_path = "public/videos/" . $id . '/gif';

            $folderExists = Storage::disk('local')->exists($video_gif_path);
            if (!$folderExists) {
                Storage::makeDirectory($video_gif_path);
            }

            $video_thumb_path = "public/videos/" . $id . '/thumb';

            $folderExistsThumb = Storage::disk('local')->exists($video_thumb_path);
            if (!$folderExistsThumb) {
                Storage::disk('local')->makeDirectory($video_thumb_path);
            }

            if ($request->hasFile('profile_pic')) {
                try {
                    $image_file = $request->file('profile_pic');

                    if ($image_file->isvalid()) {
                        $functions = new Functions();

                        $path = 'public/profile_pic/' . $id;

                        $filenametostore = request()->file('profile_pic')->store($path);
                        Storage::setVisibility($filenametostore, 'public');
                        $fileArray = explode('/', $filenametostore);
                        $fileName = array_pop($fileArray);
                        // dd(asset(Storage::url('public/profile_pic/'.$user->user_id.'/'.$fileName)));
                        $functions->_cropImage($image_file, 300, 300, 0, 0, $path . '/small', $fileName);
                        // dd(66);
                        $file_path = asset(Storage::url('public/profile_pic/' . $id . "/" . $fileName));

                        $small_file_path = asset(Storage::url('public/profile_pic/' . $id . "/small/" . $fileName));
                        if ($file_path == "") {
                            $file_path = asset(config('app.profile_path')) . 'default-user.png';
                        }
                        if ($small_file_path == "") {
                            $small_file_path = asset(config('app.profile_path')) . 'default-user.png';
                        }

                        $data['user_id']  = $id;
                        $data['image']     = $fileName;


                        DB::table('users')
                            ->where('user_id', $id)
                            ->update(['user_dp' => $fileName]);
                    }
                } catch (Exception $ex) {
                    dd($ex->getMessage());
                    return redirect()->back();
                }
            }

            Auth::guard('web')->loginUsingId($id);
            return redirect()->route('web.home')->with('success', 'You are registered successfully.');
        }
    }
}
