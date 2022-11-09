<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Mail\SendMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use App\Helpers\Common\Functions;

class ForgotPasswordController extends Controller
{
   
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showLinkRequestForm()
    {
        return view('web.auth.passwords.email');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $rules = [
            'email'   => 'required|exists:users,email'
        ];

        $messages = [
            'email.required'  =>  'You cant leave User name field empty',
            'email.exists'  =>  'The user with this email does not exists'
        ];

        $this->validate($request,$rules,$messages);
        $user = DB::table('users')->where('email',$request->email)->first();
        
        //dd($user->user_id);
        $url= URL::temporarySignedRoute(
            'web.set-password', now()->addMinutes(30), ['user' => $user->user_id]
        );
        $site_title=Functions::getSiteTitle();
        $mailBody = '
        <p>Dear <b>'. $user->username .'</b>,</p>
        <p style="font-size:16px;color:#333333;line-height:24px;margin:0">Click the below button to reset your password.</p>
        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px;margin-top:20px;text-align:center">
            <a style="padding:10px;background:#007bff;color:#fff;border-radius:5px;cursor:pointer;text-decoration:none;" href="'.$url.'"> Password Reset</a>
        </p>
        <br/><br/>
        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you<br />'.$site_title.'<br/></p>
        ';
        // dd($mailBody);
        // $ref_id
        $array = array('subject'=>'Welcome to '.$site_title,'view'=>'emails.site.company_panel','body' => $mailBody);

        // if(strpos($_SERVER['SERVER_NAME'], "localhost")===false){
            Mail::to($user->email)->send(new SendMail($array));  

        Session :: flash('success', 'Mail has been sent to your email address. Please check email to reset your password.');
        return redirect()->route('web.login');
    }

    public function setPassword($id){
        
        return view("web.auth.passwords.set-password",compact('id'));

    }

    public function passwordChanged(Request $request) {

        $rules = [
            'new_password'      => 'required|min:6',
            'confirm_password'  => 'required|min:6|same:new_password'
        ];

        $messages = [
           
            'new_password.required'         => 'You cant leave new password field empty',
            'new_password.min'              => 'New password must be 6 characters long',
            'confirm_password.required'     => 'You cant leave confirm password field empty',
            'confirm_password.min'          => 'Confirm password must be 6 characters long',
            'confirm_password.same'         => 'Confirm password must be same as the new password'
        ];


        $this->validate($request, $rules,$messages);
        DB::table('users')->where('user_id',$request->id)->update(['password'=> Hash::make($request->new_password)]);
        return redirect()->route('web.login')->with('success','Password Reset Successfully.');
        
    }

}
