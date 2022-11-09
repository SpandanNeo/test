<?php

namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class SettingController extends Controller
{
	public function __construct()
    {
        $this->middleware('auth:api');
    }
	public function updateNotificationSetting(Request $request) {
            $user_id = auth()->guard("api")->user()->user_id;
        
            $checkRecord = DB::table("notification_settings")
				                ->select("id")
				                ->where("user_id",$user_id)
				                ->first();
			
		    $follow = $request->follow == null ? 0 : $request->follow;
		    $like = $request->like == null ? 0 : $request->like;
		    $comment = $request->comment == null ? 0 : $request->comment;
            if($checkRecord) {
                $updatetData = array();
                $updatetData['user_id'] = $user_id;
                $updatetData['follow'] = $follow;
                $updatetData['like'] = $like;
                $updatetData['comment'] = $comment;
                DB::table('notification_settings')->where('id', $checkRecord->id)->update($updatetData);
            } else {
                $updatetData = array();
                $updatetData['user_id'] = $user_id;
                $updatetData['follow'] = $follow;
                $updatetData['like'] = $like;
                $updatetData['comment'] = $comment;
                DB::table('notification_settings')->insert($updatetData);
            }
	}
	
	public function userNotification(Request $request) {
        $user_id = auth()->guard("api")->user()->user_id;
        $notificationSetting = DB::table("notification_settings")
    				                ->select("*")
    				                ->where("user_id",$user_id)
    				                ->first();
			                
        $response =array("status"=>true,"data"=> $notificationSetting);
        return response()->json($response);
		
	}
	
	private function _error_string($errArray)
	{
		$error_string = '';
		foreach ($errArray as $key) {
			$error_string.= $key."\n";
		}
		return $error_string;
	}
}
