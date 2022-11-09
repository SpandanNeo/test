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

use Auth;
use Mail;
use Illuminate\Support\Facades\URL; 

class adController extends Controller
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
     
        $ad_info = DB::table("ad_settings")->select(DB::raw("*"))->first();
        if($ad_info){
        $data=array("ad_setting_id"=>$ad_info->ad_setting_id,
                    "android_app_id" => $ad_info->android_app_id,
                    "android_banner_app_id" => !empty($ad_info->disable_banner) ? $ad_info->android_banner_app_id : '',
                    "android_interstitial_app_id"     => !empty($ad_info->disable_inter) ? $ad_info->android_interstitial_app_id : '',
                    "android_video_app_id" => !empty($ad_info->disable_rewarded) ? $ad_info->android_video_app_id : '',
                    "is_banner" =>  !empty($ad_info->disable_banner) ? $ad_info->is_banner : '',
                    "is_interstitial" => !empty($ad_info->disable_inter) ? $ad_info->is_interstitial : '',
                    "is_video"=> !empty($ad_info->disable_rewarded) ? $ad_info->is_video : '',
                    "banner_show_on" =>  !empty($ad_info->disable_banner) ? $ad_info->banner_show_on : '',
                    "interstitial_show_on" => !empty($ad_info->disable_inter) ? $ad_info->interstitial_show_on : '',
                    "video_show_on" => !empty($ad_info->disable_rewarded) ? $ad_info->video_show_on : '',
                    "ios_app_id" => $ad_info->ios_app_id,
                    "ios_banner_app_id" =>  !empty($ad_info->disable_banner) ? $ad_info->ios_banner_app_id : '',
                    "ios_interstitial_app_id" => !empty($ad_info->disable_inter) ? $ad_info->ios_interstitial_app_id : '',
                    "ios_video_app_id" => !empty($ad_info->disable_rewarded) ? $ad_info->ios_video_app_id : '',
                    "status" => 'success'           
            );
        }else{
            $data=array("status"=>'error');
        }
        $response = $data;
        return response()->json($response); 
    }


}   