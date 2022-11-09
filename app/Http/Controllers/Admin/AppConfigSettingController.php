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

class AppConfigSettingController extends Controller
{
    use MigrationsHelper;

    public function  index($id=1,Request $request)
    {
        $action = 'edit';
        $id=1;
        $appSettings = DB::table('app_settings')->select(DB::raw("*"))->first();
        if(!$appSettings){
            $appSettings = (object) array(
                'api_key' => '', 
                'api_user'=> '',
                'bg_color' => '', 
                'accent_color'=> '',
                'button_color' => '',
                'button_text_color' => '',
                'text_color' => '', 
                'sender_msg_color'=> '',
                'sender_msg_text_color' => '',
                'my_msg_color' => '',
                'my_msg_text_color' => '',
                'heading_color' => '',
                'sub_heading_color' => '',
                'icon_color' => '',
                'dashboard_icon_color' => '',
                'grid_item_border_color' => '',
                'grid_border_radius' => '',
                'divider_color' => '',
                'dp_border_color' => '',
                'dashboard_icon_background_color' => '',
                'inactive_button_color'=>'',
                'inactive_button_text_color'=>'',
                'header_bg_color'=>'',
                'bottom_nav'=>'',
                'bg_shade'=>''
            );     
            
        }


        return view('admin.app_configration',compact('action','id','appSettings'));
    }

  
    public function  appConfigSettingUpdate(Request $request)
    {
        $appData = array();
        $appData['bg_color'] = is_null($request->bg_color) ? '' : $request->bg_color;
        $appData['accent_color'] = is_null($request->accent_color) ? '' : $request->accent_color;
        $appData['button_color'] =is_null($request->button_color) ? '' : $request->button_color;
        $appData['button_text_color'] =is_null($request->button_text_color) ? '' : $request->button_text_color;
        $appData['text_color'] =is_null($request->text_color) ? '' : $request->text_color;
        $appData['sender_msg_color'] =is_null($request->sender_msg_color) ? '' : $request->sender_msg_color;
        $appData['sender_msg_text_color'] =is_null($request->sender_msg_text_color) ? '' : $request->sender_msg_text_color;
        $appData['my_msg_color'] =is_null($request->my_msg_color) ? '' : $request->my_msg_color;
        $appData['my_msg_text_color'] =is_null($request->my_msg_text_color) ? '' : $request->my_msg_text_color;
        $appData['heading_color'] =is_null($request->heading_color) ? '' : $request->heading_color;
        $appData['sub_heading_color'] =is_null($request->sub_heading_color) ? '' : $request->sub_heading_color;
        $appData['icon_color'] =is_null($request->icon_color) ? '' : $request->icon_color;
        $appData['dashboard_icon_color'] =is_null($request->dashboard_icon_color) ? '' : $request->dashboard_icon_color;
        $appData['grid_item_border_color'] =is_null($request->grid_item_border_color) ? '' : $request->grid_item_border_color;
        $appData['grid_border_radius'] =is_null($request->grid_border_radius) ? '' : $request->grid_border_radius;
        $appData['divider_color'] =is_null($request->divider_color) ? '' : $request->divider_color;
        $appData['dp_border_color'] =is_null($request->dp_border_color) ? '' : $request->dp_border_color;
        $appData['dashboard_icon_background_color'] = is_null($request->dashboard_icon_background_color) ? '' : $request->dashboard_icon_background_color;
        $appData['inactive_button_color'] = is_null($request->inactive_button_color) ? '' : $request->inactive_button_color;
        $appData['inactive_button_text_color'] = is_null($request->inactive_button_text_color) ? '' : $request->inactive_button_text_color;
        $appData['header_bg_color'] = is_null($request->header_bg_color) ? '' : $request->header_bg_color;
        $appData['bottom_nav'] = is_null($request->bottom_nav) ? '' : $request->bottom_nav;
        $appData['bg_shade'] = is_null($request->bg_shade) ? '' : $request->bg_shade;

        DB::table('app_settings')->updateOrInsert(
        ['app_setting_id' => 1],
        $appData);
      
        
        $msg = "App Settings Updated Successfully";
        
        return redirect( config("app.admin_url").'/app-config-settings/'.$request->type)->with('success',$msg);
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
        $storageSettings = DB::table('storage_settings')->select(DB::raw("*"))->where('driver_id',$driverDetail->driver_id)->first();
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
            $driverDetail= DB::table('driver')->where('driver_id',$request->driver)->first();
            $storageData['driver_id'] = $request->driver;
            $storageData['access_key_id'] = is_null($request->access_key_id) ? '' : $request->access_key_id;
            $storageData['secret_access_key'] = is_null($request->secret_access_key) ? '' : $request->secret_access_key;
            $storageData['region'] = is_null($request->region) ? '' : $request->region;
            $storageData['bucket'] = is_null($request->bucket) ? '' : $request->bucket;
            $storageData['url'] = is_null($request->url) ? '' : $request->url;
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

}
