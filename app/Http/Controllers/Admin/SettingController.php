<?php

namespace App\Http\Controllers\Admin;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RachidLaasri\LaravelInstaller\Helpers\MigrationsHelper;
use Config;
use \Sightengine\SightengineClient;
use App\Mail\SendMail;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    use MigrationsHelper;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id=1,Request $request)
    {
        $action = 'edit';
        if(!isset($request->type) && $request->type==""){
            $type = 'G';
        }else{
            $type =$request->type;
        }
        $settings = DB::table('settings')->select(DB::raw("*"))->where('setting_id','=',1)->first();
        
        return view('admin.settings-create',compact('settings','action','id','type'));
    }
    public function adSettings($id=1,Request $request)
    {
        $action = 'edit';
        if(!isset($request->type) && $request->type==""){
            $type = 'G';
        }else{
            $type =$request->type;
        }
        $adSettings = DB::table('ad_settings')->select(DB::raw("*"))->first();
        
        return view('admin.ad-settings-create',compact('action','id','adSettings','type'));
    }

    private function _form_validation($request){
        // dd($request->all());
        $rules = [
            'site_name'     => 'required',
            // 'site_address'     => 'required',
            'site_email'     => 'required',
            // 'site_phone' => 'required',
            // 'site_logo'     => 'required',
        ];
        $messages = [
            'site_name.required' => 'Site name field is required',
            // 'site_address.required'    => 'You can\'t leave Registration Fee field empty',
            // 'site_email.required'    => 'You can\'t leave Invoice Comapny Name field empty',
            // 'site_phone.required'    => 'You can\'t leave Address field empty',
            // 'site_logo.required'    => 'Please select a logo',
        ];
        $this->validate($request,$rules,$messages);
        // $fileName="";
        if($request->hasFile('site_logo')) {
            $path = 'public/uploads/logos';
            $filenametostore = $request->file('site_logo')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $fileName = array_pop($fileArray);
            // $file_path = asset(config('app.profile_path').$request->user_id."/".$fileName);
        }else{
            if($request->old_site_logo!=''){
                // dd($request->old_img);
                $fileName= $request->old_site_logo;
            }else{
               $fileName= '';
            }
        }
       // $watermark="";
       if($request->hasFile('watermark')) {
            $path = 'public/uploads/logos';
            $functions = new Functions();
            $filenametostore = $request->file('watermark')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $watermark = array_pop($fileArray);
            // secure_asset(Storage::url('public/profile_pic/'.$request->user_id.'/'.$fileName)
            $functions->createThumbWidth(asset(Storage::url($filenametostore)),200,$path,"small_".$watermark);

            
            
            // $file_path = asset(config('app.profile_path').$request->user_id."/".$fileName);
        }else{
            if($request->old_watermark!=''){
                $watermark=$request->old_watermark;
            }else{
            $watermark ='';
            }
        }
        $postData = array(
            'site_name' => $request->site_name,
            'site_address' => $request->site_address,
            'site_email'     => $request->site_email,
            'site_phone'     => $request->site_phone,
            'site_logo'     => $fileName,
            'watermark'     => $watermark,
            'updated_at'     => date('Y-m-d H:i:s'),
        );
        return $postData;
    }

     /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    /*public function edit($id)
    {
        $action = 'edit';
        $candidate = DB::table('users')->select(DB::raw("*"))->where('user_id','=',$id)->first();
         //dd($candidate);
        return view('admin.candidates-create',compact('candidate','id','action'));
    }*/

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    
    public function update(Request $request, $id=1)
    {
        $action='edit';
      
        // dd($request->all());
        $data = $this->_form_validation($request);
        DB::table('settings')->updateOrInsert(
        ['setting_id' => $id],
        $data);
        // ->where('setting_id',$id)->update();
        $msg="General Settings Update Successfully";
        return redirect( config("app.admin_url").'/settings/'.$request->type)->with('success',$msg);
    }

    
    public function checkForUpdates(Request $request)
    {
        if (!env('APP_DEMO', false)) {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('cache:forget', ['key' => 'spatie.permission.cache']);
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            $executedMigrations = $this->getExecutedMigrations();
            $newMigrations = $this->getMigrations(config('installer.currentVersion', 'v100'));
            $seedCount = $this->getSeedsCount();
            // dd($seedCount);
            $containsUpdate = !empty($newMigrations) && count(array_intersect($newMigrations, $executedMigrations->toArray())) == count($newMigrations) && $seedCount==0;
            if (!$containsUpdate) {
                return redirect(url('update/' . config('installer.currentVersion', 'v100')));
            }
        }

        $request->session()->put('success', "System is already up to date.");

        return redirect()->back();
    }
    
    public function adSettingUpdate(Request $request)
    {
        $adData = array();
        $adData['android_app_id'] = is_null($request->android_app_id) ? '' : $request->android_app_id;
        $adData['android_banner_app_id'] = is_null($request->android_banner_app_id) ? '' : $request->android_banner_app_id;
        $adData['android_interstitial_app_id'] = is_null($request->android_interstitial_app_id) ? '' : $request->android_interstitial_app_id;
        $adData['android_video_app_id'] = is_null($request->android_video_app_id) ? '' : $request->android_video_app_id;
        
        $adData['ios_app_id'] = is_null($request->ios_app_id) ? '' : $request->ios_app_id;
        $adData['ios_banner_app_id'] = is_null($request->ios_banner_app_id) ? '' : $request->ios_banner_app_id;
        $adData['ios_interstitial_app_id'] = is_null($request->ios_interstitial_app_id) ? '' : $request->ios_interstitial_app_id;
        $adData['ios_video_app_id'] = is_null($request->ios_video_app_id) ? '' : $request->ios_video_app_id;
        $adData['disable_inter'] = !empty($request->disable_inter) ? 1 : 0;
        $adData['disable_banner'] = !empty($request->disable_banner) ? 1 : 0;
        $adData['disable_rewarded'] = !empty($request->disable_rewarded) ? 1 : 0;
        
        if(!is_null($request->interstitial_show_on)) {
            if( count($request->interstitial_show_on) >0 ) {
                $adData['interstitial_show_on'] = implode(",",$request->interstitial_show_on);
            } else {
                $adData['interstitial_show_on'] = '';
            }
        } else {
            $adData['interstitial_show_on'] = '';
        }
        
        if(!is_null($request->banner_show_on)) {
            if( count($request->banner_show_on) >0 ) {
                $adData['banner_show_on'] = implode(",",$request->banner_show_on);
            } else {
                $adData['banner_show_on'] = '';
            }
        } else {
            $adData['banner_show_on'] = '';
        }
        
        if(!is_null($request->video_show_on)) {
            if( count($request->video_show_on) >0 ) {
                $adData['video_show_on'] = implode(",",$request->video_show_on);
            } else {
                $adData['video_show_on'] = '';
            }
        } else {
            $adData['video_show_on'] = '';
        }

        DB::table('ad_settings')->updateOrInsert(
        ['ad_setting_id' => 1],
        $adData);
        $msg="Ad Update Successfully";
        return redirect( config("app.admin_url").'/ads-settings/'.$request->type)->with('success','Ads Update Successfully');
    }


    public function  nsfwSettings($id=1,Request $request)
    {
        $action = 'edit';
        if(!isset($request->type) && $request->type==""){
            $type = 'V';
        }else{
            $type =$request->type;
        }
        $nsfwSettings = DB::table('nsfw_settings')->select(DB::raw("*"))->first();
        
        return view('admin.nsfw-settings-create',compact('action','id','nsfwSettings','type'));
    }
    public function  nsfwSettingUpdate(Request $request)
    {
        $nsfwData = array();
        $nsfwData['api_key'] = is_null($request->api_key) ? '' : $request->api_key;
        $nsfwData['api_secret'] = is_null($request->api_secret) ? '' : $request->api_secret;
        $nsfwData['status'] = is_null($request->status) ? 0 : $request->status;
        $nsfwData['wad'] = is_null($request->wad) ? 0 : $request->wad;
        $nsfwData['nudity'] = is_null($request->nudity) ? 0 : $request->nudity;
        $nsfwData['offensive'] = is_null($request->offensive) ? 0 : $request->offensive;
        
        DB::table('nsfw_settings')->updateOrInsert(
        ['ns_id' => 1],
        $nsfwData);
        $msg = "Video Moderation Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/nsfw-settings/'.$request->type)->with('success',$msg);
    }

   
    public function  mailSettings($id=1,Request $request)
    {
        if(!isset($request->type) && $request->type==""){
            $type = 'SG';
        }else{
            $type =$request->type;
        }
        $action = 'edit';
        
        $mailTypes = DB::table('mail_types')->select(DB::raw("*"))->get();
        $activeMailType=DB::table('mail_types')->select('mail_type_id','name','mail_type')->where('active',1)->first();
        if($activeMailType){
        
            $type=$activeMailType->mail_type;
        }
            $SGmailSettings = DB::table('mail_settings as ms')
                            ->select(DB::raw("ms.*,m.*"))
                            ->leftJoin('mail_types as m','m.mail_type_id','ms.mail_type_id')
                            ->where('m.mail_type','SG')->first();
            $SMmailSettings = DB::table('mail_settings as ms')
                            ->select(DB::raw("ms.*,m.*"))
                            ->leftJoin('mail_types as m','m.mail_type_id','ms.mail_type_id')
                            ->where('m.mail_type','SM')->first();
        //dd($storageSettings);

        // $mailSettings = DB::table('mail_settings')->select(DB::raw("*"))->first();
        // }else{
        //     $mailSettings = (object) array('m_id' => 0, 'api_key'=> '',  'from_email' => '', 'mail_host'=> '',   'mail_port'=> '',   'mail_username'=> '',   'mail_password'=> '',   'mail_encryption'=> '', 'mail_type'=> 'SG',   'status'=> '0');
             
        // }
        return view('admin.mail-settings-create',compact('action','id','SMmailSettings','SGmailSettings','type','mailTypes'));
    }

    public function  mailSettingUpdate(Request $request)
    {   
        DB::table('mail_types')->update(['active'=>0]);
       
        $selectedMailType=DB::table('mail_types')->select('mail_type_id','name','mail_type')->where('mail_type',$request->mail_type)->first();
        $mailData = array();

        if($selectedMailType){
 
            $mailData['mail_type_id'] =$selectedMailType->mail_type_id;
            $mailData['api_key'] = ($request->mail_type == 'SG' && isset($request->api_key)) ?  $request->api_key : '';
            // $mailData['api_secret'] = is_null($request->api_secret) ? '' : $request->api_secret;
            $mailData['from_email'] = is_null($request->from_email) ? '' : $request->from_email;
            // $mailData['mail_type'] = is_null($request->mail_type) ? '' : $request->mail_type;
            $mailData['mail_host'] = is_null($request->mail_host) ? '' : $request->mail_host;
            $mailData['mail_port'] = is_null($request->mail_port) ? '' : $request->mail_port;
            $mailData['mail_username'] = is_null($request->mail_username) ? '' : $request->mail_username;
            $mailData['mail_password'] = is_null($request->mail_password) ? '' : $request->mail_password;
            $mailData['mail_encryption'] = is_null($request->mail_encryption) ? '' : $request->mail_encryption;
            // $mailData['status'] = is_null($request->status) ? 0 : 1;
            DB::table('mail_settings')->updateOrInsert(
            ['mail_type_id' => $selectedMailType->mail_type_id],
            $mailData);

        }
       
        if($request->mail_type=='SG'){
            $env_values = array(
                "SENDGRID_API_KEY"=>preg_replace('/\s+/', '',$request->api_key), 
                "MAIL_DRIVER"=>"sendgrid",
                "MAIL_FROM_ADDRESS"=>preg_replace('/\s+/', '',$request->from_email) );
        }else{
            $env_values = array("MAIL_HOST"=>preg_replace('/\s+/', '',$request->mail_host), 
                                "MAIL_DRIVER"=>"smtp","MAIL_PORT"=> $request->mail_port ,
                                "MAIL_USERNAME"=>preg_replace('/\s+/', '',$request->mail_username),
                                "MAIL_PASSWORD"=>preg_replace('/\s+/', '',$request->mail_password),
                                "MAIL_ENCRYPTION"=>preg_replace('/\s+/', '',$request->mail_encryption),
                                "MAIL_DRIVER"=>"smtp",
                                "MAIL_FROM_ADDRESS"=>preg_replace('/\s+/', '',$request->from_email)
                            );
        }
        // dd($request->mail_type);
        Functions::setEnvironmentValue($env_values);
        
        $site_title=Functions::getSiteTitle();
        $mailBody = '
        <p>Dear <b> Admin </b>,</p>
        <p style="font-size:16px;color:#333333;line-height:24px;margin:0">Test mail.</p>

        <br/><br/>
        <p style="color:#333333;font-size:16px;line-height:24px;margin:0;padding-bottom:23px">Thank you<br />'.$site_title.'<br/></p>
        ';
        // dd($mailBody);
        // $ref_id
        $array = array('subject'=>'Welcome to '.$site_title,'view'=>'emails.site.company_panel','body' => $mailBody);

        if(strpos($_SERVER['SERVER_NAME'], "localhost")===false && strpos($_SERVER['SERVER_NAME'], "leukewebpanel.local")===false){
            try{ 
                \Illuminate\Support\Facades\Mail::to($request->from_email)->send(new \App\Mail\SendMail($array)); 
                DB::table('mail_types')->where('mail_type',$request->mail_type)->update(['active'=> 1]);
            }catch(\Exception $e){
                $msg = "Invalid mail credentials";
            
                return redirect( config("app.admin_url").'/mail-settings/'.$request->mail_type)->with('error',$msg);
            }
        }else{
            DB::table('mail_types')->where('mail_type',$request->mail_type)->update(['active'=> 1]);
        }
        $msg = "Email Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/mail-settings/'.$request->mail_type)->with('success',$msg);
    }

    public function  homeSettings($id=1)
    {
        $action = 'edit';

        $homeSettings = DB::table('home_settings')->select(DB::raw("*"))->first();
        if(!$homeSettings){
            $homeSettings = (object) array('heading' => 0, 'sub_heading'=> '',  'img1' => '', 'img2'=> '','logo' => '','white_logo' => '' ,'site_title' => '','comments_per_page'=>'','video_per_page'=>'');
             
        }
        return view('admin.home-settings-create',compact('action','id','homeSettings'));
    }

    public function  homeSettingUpdate(Request $request)
    {
        $rules = [
            'site_title'     => 'required',
            'heading'     => 'required',
            'sub_heading'     => 'required'
        ];
        $messages = [
            'site_title.required' => 'Site Title field is required',
            'heading.required' => 'Heading field is required',
            'sub_heading.required' => 'Sub Heading field is required',
        ];
        $this->validate($request,$rules,$messages);
        $data = array();

        // $img1='';
        // $img2='';
        // $logo='';
        // $white_logo='';
        // $home_top_background_img='';
        $data['site_title'] = is_null($request->site_title) ? '' : $request->site_title;
        $data['heading'] = is_null($request->heading) ? '' : $request->heading;
        $data['sub_heading'] = is_null($request->sub_heading) ? '' : $request->sub_heading;

        $data['img1_link'] = is_null($request->img1_link) ? '' : $request->img1_link;
        $data['img2_link'] = is_null($request->img2_link) ? '' : $request->img2_link;
        
        $data['comments_per_page'] = is_null($request->comments_per_page) ? '8' : preg_replace('/\s+/', '',$request->comments_per_page);
        $data['videos_per_page'] = is_null($request->videos_per_page) ? '12' : preg_replace('/\s+/', '',$request->videos_per_page);
        if($request->hasFile('img1')) {
            $path = 'public/uploads';
            $filenametostore = $request->file('img1')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $img1 = array_pop($fileArray);
            $data['img1']=$img1;
            // $file_path = asset(config('app.profile_path').$request->user_id."/".$fileName);
        }
        // else{
        //     if(isset($request->id) && $request->id>0 && $request->old_img1!=''){
        //         $img1=$request->old_img1;
        //     }else{
        //         return redirect( config("app.admin_url").'/home-settings')->with('error','Google Button Image Is required');
        //     }
        // }
        if($request->hasFile('img2')) {
            $path = 'public/uploads';
            $filenametostore = $request->file('img2')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $img2 = array_pop($fileArray);
            $data['img2']=$img2;
           
        }
   
        if($request->hasFile('logo')) {
            $path = 'public/uploads/logos';
            $filenametostore = $request->file('logo')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $logo = array_pop($fileArray);
            $data['logo']=$logo;
            
        }
  
        
        if($request->hasFile('white_logo')) {
            $path = 'public/uploads/logos';
            $filenametostore = $request->file('white_logo')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $white_logo = array_pop($fileArray);
            $data['white_logo']=$white_logo;
           
        }
    

        if($request->hasFile('home_top_background_img')) {
            $path = 'public/uploads';
            $filenametostore = $request->file('home_top_background_img')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $home_top_background_img = array_pop($fileArray);
            $data['home_top_background_img']=$home_top_background_img;
           
        }
        
        $data['main_color'] = isset($request->gradx_code) ? $request->gradx_code : 'background: linear-gradient(50deg,rgb(115,80,199) 0%,rgb(236,74,99) 100%);';
        $home_setting=DB::table('home_settings')->count();
        if($home_setting==0){
            DB::table('home_settings')->insert($data);
        }else{
            DB::table('home_settings')->update($data);
        }
        $env_values = array(
            "VIEWS_PER_PAGE"=>$data['comments_per_page'],
            "VIDEOS_PER_PAGE"=>$data['videos_per_page']
         );
     
        Functions::setEnvironmentValue($env_values);
        $msg = "Home Screen Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/home-settings')->with('success',$msg);
    }

    public function apiCheck(){
        $client = new SightengineClient('1122776968', '2uSorbXFkDKGCPFGySxJ');
        // $output = $client->check(['nudity',  'wad', 'offensive'])->set_url('https://sightengine.com/assets/img/doc/nudity/nudity2.jpg');
        $output = $client->check(['nudity',  'wad', 'offensive'])->set_url('https://appscontent.unifysofttech.in/leuke/7.jpg');
        echo json_encode($output);
        echo "<br>";
        print_r($output);

    }

    public function  socialSettings($id=1,Request $request)
    {
        $action = 'edit';
        if(isset($request->type) && $request->type!=""){
            $type=$request->type;
        }else{
            $type="G";
        }
        
        $socialSettings = DB::table('social_settings')->select(DB::raw("*"))->first();
        if(!$socialSettings){
            $socialSettings = (object) array('google_client_id' => '', 'google_secret'=> '',  'facebook_client_id' => '', 'facebook_secret'=> '','google_active' => 0,'fb_active' => 0);
             
        }
        return view('admin.social-settings-create',compact('action','id','socialSettings','type'));
    }

    public function  socialSettingUpdate(Request $request)
    {
        $socialData = array();
        $socialData['google_client_id'] = is_null($request->google_client_id) ? '' : preg_replace('/\s+/', '',$request->google_client_id);
        $socialData['google_secret'] = is_null($request->google_secret) ? '' : preg_replace('/\s+/', '',$request->google_secret);
        $socialData['facebook_client_id'] = is_null($request->facebook_client_id) ? '' : preg_replace('/\s+/', '',$request->facebook_client_id);
        $socialData['facebook_secret'] = is_null($request->facebook_secret) ? '' : preg_replace('/\s+/', '',$request->facebook_secret);
        $socialData['google_active'] = is_null($request->google_active) ? 0 : preg_replace('/\s+/', '',$request->google_active);
        $socialData['fb_active'] = is_null($request->fb_active) ? 0 : preg_replace('/\s+/', '',$request->fb_active);
    //    dd($socialData);
        DB::table('social_settings')->updateOrInsert(
        ['social_setting_id' => 1],
        $socialData);
      
        $env_values = array(
            "GOOGLE_CLIENT_ID"=>$socialData['google_client_id'], 
            "GOOGLE_CLIENT_SECRET"=>$socialData['google_secret'],
            "GOOGLE_URL" => asset('auth/google/callback'),
            "FACEBOOK_CLIENT_ID"=> $socialData['facebook_client_id'],
            "FACEBOOK_CLIENT_SECRET"=>$socialData['facebook_secret'],
            "FACEBOOK_CALLBACK_URL" => asset('auth/facebook/callback')
         );
        
        Functions::setEnvironmentValue($env_values);
        
        $msg = "Social Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/social-settings/'.$request->type)->with('success',$msg);
    }
    public function  pusherSettings($id=1,Request $request)
    {
        $action = 'edit';
        if(isset($request->type) && $request->type!=""){
            $type=$request->type;
        }else{
            $type="P";
        }
        $pusherSettings = DB::table('pusher_settings')->select(DB::raw("*"))->first();
        if(!$pusherSettings){
            $pusherSettings = (object) array('pusher_app_id' => '', 'pusher_app_key'=> '',  'pusher_app_secret' => '', 'pusher_app_cluster'=> '');
             
        }
        return view('admin.pusher-settings-create',compact('action','id','pusherSettings','type'));
    }

    public function  pusherSettingUpdate(Request $request)
    {
        $pusherData = array();
        $pusherData['pusher_app_id'] = is_null($request->pusher_app_id) ? '' : preg_replace('/\s+/', '',$request->pusher_app_id);
        $pusherData['pusher_app_key'] = is_null($request->pusher_app_key) ? '' : preg_replace('/\s+/', '',$request->pusher_app_key);
        $pusherData['pusher_app_secret'] = is_null($request->pusher_app_secret) ? '' : preg_replace('/\s+/', '',$request->pusher_app_secret);
        $pusherData['pusher_app_cluster'] = is_null($request->pusher_app_cluster) ? '' : preg_replace('/\s+/', '',$request->pusher_app_cluster);
       
        DB::table('pusher_settings')->updateOrInsert(
        ['pusher_setting_id' => 1],
        $pusherData);
      
        $env_values = array(
            "PUSHER_APP_ID"=>$pusherData['pusher_app_id'], 
            "PUSHER_APP_KEY"=>$pusherData['pusher_app_key'],
            "PUSHER_APP_SECRET"=> $pusherData['pusher_app_secret'],
            "PUSHER_APP_CLUSTER"=>$pusherData['pusher_app_cluster'],
            "BROADCAST_DRIVER"=>"pusher"
         );
        
        Functions::setEnvironmentValue($env_values);
        
        $msg = "Pusher Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/pusher-settings/'.$request->type)->with('success',$msg);
    }

    public function  googleCaptchaSettings($id=1,Request $request)
    {
        $action = 'edit';
        if(isset($request->type) && $request->type!=""){
            $type=$request->type;
        }else{
            $type="C";
        }
        $googleCaptchaSettings = DB::table('google_captcha_key')->select(DB::raw("*"))->first();
        if(!$googleCaptchaSettings){
            $googleCaptchaSettings = (object) array('site_key' => '', 'secret_key'=> '');
             
        }
        return view('admin.google-captcha-create',compact('action','id','googleCaptchaSettings','type'));
    }

    public function  googleCaptchaUpdate(Request $request)
    {
        $captchaData = array();
        $captchaData['site_key'] = is_null($request->site_key) ? '' : preg_replace('/\s+/', '',$request->site_key);
        $captchaData['secret_key'] = is_null($request->secret_key) ? '' : preg_replace('/\s+/', '',$request->secret_key);
        
        DB::table('google_captcha_key')->updateOrInsert(
        ['id' => 1],
        $captchaData);
      
        $env_values = array(
            "GOOGLE_CAPTCHA_SITE_KEY"=>$captchaData['site_key'],
            "GOOGLE_CAPTCHA_SECRET_KEY"=> $captchaData['secret_key']
         );
        
        Functions::setEnvironmentValue($env_values);
        
        $msg = "Google Captcha Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/google-captcha/'.$request->type)->with('success',$msg);
    }

    public function  socialMediaLinks($id=1)
    {
        $action = 'edit';
        $socialLinks = DB::table('social_media_links')->select(DB::raw("*"))->first();
        if(!$socialLinks){
            $socialLinks = (object) array('fb_link' => '', 'twitter_link'=> '',  'google_link' => '', 'youtube_link'=> '');
             
        }
        return view('admin.social-media-links',compact('action','id','socialLinks'));
    }

    public function  socialMediaLinksUpdate(Request $request)
    {
        $socialData = array();
        $socialData['fb_link'] = is_null($request->fb_link) ? '' : $request->fb_link;
        $socialData['twitter_link'] = is_null($request->twitter_link) ? '' : $request->twitter_link;
        $socialData['google_link'] = is_null($request->google_link) ? '' : $request->google_link;
        $socialData['youtube_link'] = is_null($request->youtube_link) ? '' : $request->youtube_link;
       
        DB::table('social_media_links')->updateOrInsert(
        ['social_media_link_id' => 1],
        $socialData);
      
          
        $msg = "Social Media Links Updated Successfully";
        
        return redirect( config("app.admin_url").'/social-media-links')->with('success',$msg);
    }

    public function appVersion(){
        return view('admin.app-version-warning');
    }

    public function adminAppVersion(){
        return view('admin.admin-app-version-warning');
    }

    public function  appSettings($id=1,Request $request)
    {
        $action = 'edit';
        if(isset($request->type) && $request->type!=""){
            $type=$request->type;
        }else{
            $type="A";
        }
        $id=1;
        $appSettings = DB::table('app_settings')->select(DB::raw("*"))->first();
        if(!$appSettings){
            $appSettings = (object) array(
                'api_key' => '', 
                'api_user'=> ''
            
            );     
            
        }

        $appLoginSettings = DB::table('app_login_page')->select(DB::raw("*"))->first();
        if(!$appLoginSettings){
            $appLoginSettings = (object) array('logo' => '','background_img'=>'', 'title'=> '',' description' => NULL,'fb_login' => 0, 'google_login' => 0,'privacy_policy'=> NULL);     
        }
        
        $appGeneralSettings = DB::table('app_settings')->select(DB::raw("*"))->first();
        if(!$appGeneralSettings){
            $appGeneralSettings = (object) array('video_time_limit' => '15,30');     
        }

        return view('admin.app-settings-create',compact('action','id','appSettings','type','appLoginSettings','appGeneralSettings'));
    }

    public function  appSettingUpdate(Request $request)
    {
        $appData = array();
        $appData['api_key'] = is_null($request->api_key) ? '' : preg_replace('/\s+/', '',$request->api_key);
        $appData['api_user'] = is_null($request->api_user) ? '' : preg_replace('/\s+/', '',$request->api_user);
        
        DB::table('app_settings')->updateOrInsert(
        ['app_setting_id' => 1],
        $appData);
      
        $env_values = array(
            "API_KEY"=>$appData['api_key'],
            "API_USER"=>$appData['api_user']
         
         );
        // dd($env_values);
        Functions::setEnvironmentValue($env_values);
        
        $msg = "App Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/app-settings/'.$request->type)->with('success',$msg);
    }

    public function  appLoginSettingUpdate(Request $request)
    {
        // dd($request->all());
        $appData = array();
        $appData['title'] = ($request->tile) ? '' : $request->title;
        $appData['description'] = is_null($request->description) ? '' : $request->description;
        $appData['fb_login'] = ($request->fb_login) ? $request->fb_login : 0;
        $appData['google_login'] = ($request->google_login) ? $request->google_login : 0;
        $appData['apple_login'] = ($request->apple_login) ? $request->apple_login : 0;
        $appData['privacy_policy'] = is_null($request->privacy_policy) ? '' : $request->privacy_policy;
        

        if($request->hasFile('logo')) {
            $path = 'public/uploads/logos';
            $filenametostore = $request->file('logo')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $fileName = array_pop($fileArray);
            // $file_path = asset(config('app.profile_path').$request->user_id."/".$fileName);
        }else{
            if(isset($request->id) && $request->id>0 && $request->old_logo!=''){
                // dd($request->old_img);
                $fileName= $request->old_logo;
            }else{
               $fileName= '';
            }
        }
        if($request->hasFile('background_img')) {
            $path = 'public/uploads/background_img';
            $filenametostore = $request->file('background_img')->store($path);
            Storage::setVisibility($filenametostore, 'public');
            $fileArray = explode('/',$filenametostore);
            $fileName2 = array_pop($fileArray);
            // $file_path = asset(config('app.profile_path').$request->user_id."/".$fileName);
        }else{
            if(isset($request->id) && $request->id>0 && $request->old_background_img!=''){
                // dd($request->old_img);
                $fileName2= $request->old_background_img;
            }else{
               $fileName2= '';
            }
        }
        $appData['logo']=$fileName;
        $appData['background_img']=$fileName2;
        DB::table('app_login_page')->updateOrInsert(
        ['app_login_page_id' => 1],
        $appData);
   
        
        $msg = "App Login Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/app-settings/'.$request->type)->with('success',$msg);
    }

    public function  appGeneralSettingUpdate(Request $request)
    {
        $appData = array();
        $timingArr=$request->video_time_limit;
        $times='';
        foreach($timingArr as $arr){
            $arrVal= ltrim($arr, "0");  
            if($arrVal>0 && $arrVal<=300){
                $times.=','.$arrVal;
            }
        }
        if($times!=""){
            $times=trim($times,",");
            $appData['video_time_limit'] =  $times;
            
            DB::table('app_settings')->updateOrInsert(
            ['app_setting_id' => 1],
            $appData);
            
            $msg = "App General Settings Updated Successfully";
            return redirect( config("app.admin_url").'/app-settings/'.$request->type)->with('success',$msg);
        }else{
            $msg = "Invalid values";
            return redirect( config("app.admin_url").'/app-settings/'.$request->type)->with('error',$msg);
        }
        
    }

    public function  storageSettings($id=1,Request $request)
    {
        $action = 'edit';
        if(isset($request->type) && $request->type!=""){
            $type=$request->type;
        }else{
            $type="S";
        }
        $driverDetail=DB::table('driver')->select('driver','driver_id')->where('active',1)->first();
        $activeDriver=$driverDetail->driver;

        $storageDriver = DB::table('driver')->select(DB::raw("*"))->get();
        $storageSettings = DB::table('storage_settings')->select(DB::raw("*"))->first();
        //dd($storageSettings);

        if(!$storageSettings){
            $storageSettings = (object) array('driver' => 'local', 'access_key_id'=> '', 'secret_access_key'=> '', 'region'=> '', 'bucket'=> '', 'url'=> '', 'active'=> '1');     
        }
        return view('admin.storage-settings-create',compact('action','id','storageSettings','activeDriver','storageDriver','type'));
    }

    public function  storageSettingUpdate(Request $request)
    {
        $storageData = array();
        DB::table('driver')->update(['active'=>0]);
        DB::table('driver')->where('driver_id',$request->driver)->update(['active'=>1]);
        
        if($request->driver > 1){
            $rules = [
                'access_key_id'     => 'required',
                'secret_access_key'     => 'required',
                'region'     => 'required',
                'bucket'     => 'required',
                'url'     => 'required'
            ];
            $messages = [
                'access_key_id.required' => 'Access Key field is required',
                'secret_access_key.required' => 'Secret Access Key field is required',
                'region.required' => 'Region field is required',
                'bucket.required' => 'Bucket field is required',
                'url.required' => 'Url field is required'
            ];
            $this->validate($request,$rules,$messages);

            $driverDetail= DB::table('driver')->where('driver_id',$request->driver)->first();
            $storageData['driver_id'] = $request->driver;
            $storageData['access_key_id'] = is_null($request->access_key_id) ? '' : preg_replace('/\s+/', '', $request->access_key_id);
            $storageData['secret_access_key'] = is_null($request->secret_access_key) ? '' : preg_replace('/\s+/', '',$request->secret_access_key);
            $storageData['region'] = is_null($request->region) ? '' : preg_replace('/[+]/','', preg_replace('/\s+/', '',$request->region));
            $storageData['bucket'] = is_null($request->bucket) ? '' : preg_replace('/\s+/', '',$request->bucket);
            $storageData['url'] = is_null($request->url) ? '' : preg_replace('/\s+/', '',$request->url);
            // $storageData['active'] = is_null($request->active) ? '1' : $request->active;
        
            DB::table('storage_settings')->updateOrInsert(
            ['driver_id' => $request->driver],
            $storageData);
        
            $env_values = array(
                "FILESYSTEM_DRIVER"=>$driverDetail->driver,
                "AWS_ACCESS_KEY_ID"=>$storageData['access_key_id'],
                "AWS_SECRET_ACCESS_KEY"=>$storageData['secret_access_key'],
                "AWS_DEFAULT_REGION"=>$storageData['region'],
                "AWS_BUCKET"=>$storageData['bucket'],
                "AWS_URL"=>$storageData['url'],
            
            );
        // dd($env_values);
            Functions::setEnvironmentValue($env_values);
           
        }else{
            $env_values = array(
                "FILESYSTEM_DRIVER"=>'local',
            );
            // dd($env_values);
            Functions::setEnvironmentValue($env_values);
        }
        $msg = "Storage Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/storage-settings/'.$request->type)->with('success',$msg);
    }


    public function chatMigration(){
        $migrated=DB::table('settings')->pluck('migrated')->first();
        $type="S";
        $id=1;
        return view('admin.chat-migration',compact('migrated','type','id'));
    }
}
