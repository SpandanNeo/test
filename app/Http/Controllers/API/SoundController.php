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

class SoundController extends Controller
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
        $this->middleware('auth:api', ['except' => ['index']]);
    }
    public function index(Request $request){
        // die(print_r($request->all()));
        // $storagePath  = secure_asset(config("app.sound_path"));
        // $userDpPath = secure_asset(config('app.profile_path'));
        if(auth()->guard('api')->user()){
            $user_id=auth()->guard('api')->user()->user_id;
        }else{
            $user_id=0;
        }
        $storagePath  = asset(Storage::url('public/sounds'));
        $imageStoragePath = asset(Storage::url('public/sounds/images'));
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $data = array();
        
        // $page_size = isset($request->page_size) ? $request->page_size : 2;
        $page_size = 10;
        $categories = DB::table("categories")
                            ->select(DB::raw("*"))
                            ->orderBy("rank","asc")
                            ->paginate($page_size);
        
        if( count($categories) > 0 ) {
            $count = 0;
            foreach($categories as $key => $value) {
                
                $sounds = DB::table("sounds as s")->select(DB::raw("s.sound_id,s.duration,title,s.album,case when s.user_id = 0  then concat('".$storagePath."/',sound_name) else concat('".$storagePath."/',s.user_id,'/',sound_name) end as sound_url,duration,s.user_id,tags,ifnull( case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',fs.user_id,'/small/',u.user_dp) END,case when s.image='' then concat('".asset('default/music-icon.png')."') else concat('".$imageStoragePath."/',s.image) end) as image_url,ifnull(fs.sound_id,0) as fav,s.cat_id,s.used_times,ifnull(s.created_at,'NA') as created_at"))
                        ->leftJoin("users as u","s.user_id","u.user_id")->where("s.deleted",0)
                        ->leftJoin('favourite_sound as fs', function ($join)use ($request,$user_id){
                            $join->on('fs.sound_id','=','s.sound_id')
                                ->where('fs.user_id',$user_id);
                            })
                        ->orderBy("s.used_times","desc")
                        ->where("s.cat_id",$value->cat_id);
                        
                if(isset($request->cat_id) && $request->cat_id!=""){
                    $cat_id = $request->cat_id;
                    $sounds = $sounds->where('s.cat_id',$cat_id);
                }
                        
                if(isset($request->search) && $request->search!=""){
                    
                    $search = $request->search;
                    $sounds = $sounds->where('title', 'like', '%' . $search . '%')->orWhere('sound_name', 'like', '%' . $search . '%')->orWhere('tags', 'like', '%' . $search . '%');
                }
                $sounds = $sounds->where('s.active',1)->limit(4)->get();
                if( count($sounds) > 0 ) {
                    foreach($sounds as $key1 => $value1) {
                        $data[$count]['category'] = $value->cat_name;
                        $data[$count]['cat_id'] = "$value->cat_id";
                        $data[$count]['sound_id'] = $value1->sound_id;
                        $data[$count]['duration'] = $value1->duration;
                        $data[$count]['title'] = strip_tags($value1->title);
                        $data[$count]['sound_url'] = $value1->sound_url;
                        $data[$count]['user_id'] = $value1->user_id;
                        $data[$count]['tags'] = strip_tags($value1->tags);
                        $data[$count]['album'] = strip_tags(($value1->album!="") ? $value1->album : "Unknown");
                        $data[$count]['image_url'] = $value1->image_url;
                        $data[$count]['used_times'] = $value1->used_times;
                        $data[$count]['created_at'] = $value1->created_at;
                        $data[$count]['fav'] = $value1->fav;
                        $count++;
                    }
                }
            }
        }

        $response = array("status" => "success",'data' => $data,'total_record' => count($data));
        return response()->json($response); 
    }
    
    public function getCategorySounds(Request $request){
        // $storagePath  = secure_asset(config("app.sound_path"));
        // $userDpPath = secure_asset(config('app.profile_path'));  
        
        if(auth()->guard('api')->user()){
            $login_id=auth()->guard('api')->user()->user_id;
        }else{
            $login_id=0;
        }
        
        $storagePath = asset(Storage::url('public/sounds'));
        $imageStoragePath = asset(Storage::url('public/sounds/images'));
        $userDpPath = asset(Storage::url('public/profile_pic'));    
        $page_size = isset($request->page_size) ? $request->page_size : 20;
        
        $sounds = DB::table("categories as c")->select(DB::raw("s.sound_id,case when s.album = '' then 'Unknown' else s.album end as album,s.duration,title,case when s.user_id = 0  then concat('".$storagePath."/',sound_name) else concat('".$storagePath."/',s.user_id,'/',sound_name) end as sound_url,duration,s.user_id,tags,ifnull( case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',fs.user_id,'/small/',u.user_dp) END,case when s.image='' then concat('".asset('default/music-icon.png')."') else concat('".$imageStoragePath."/',s.image) end) as image_url,ifnull(c.cat_name,'No category') as category,ifnull(fs.sound_id,0) as fav,s.cat_id,s.used_times,ifnull(s.created_at,'NA') as created_at"))
            ->join('sounds as s', function ($join) use ($request){
                $join->on('c.cat_id','=','s.cat_id');
                    
                })
                ->leftJoin("users as u","s.user_id","u.user_id")->where("s.deleted",0)
                ->leftJoin('favourite_sound as fs', function ($join)use ($request,$login_id){
                    $join->on('fs.sound_id','=','s.sound_id')
                        ->where('fs.user_id',$login_id);
                });
                
        
        if(isset($request->cat_id) && $request->cat_id!=""){
            $cat_id = $request->cat_id;
            $sounds = $sounds->where('s.cat_id',$cat_id);
        }
                
        if(isset($request->search) && $request->search!=""){
            $search = $request->search;
            $sounds = $sounds->where('title', 'like', '%' . $search . '%')->orWhere('sound_name', 'like', '%' . $search . '%')->orWhere('tags', 'like', '%' . $search . '%');
        }
        
        $sounds= $sounds->orderBy("sound_id","desc");
        $sounds= $sounds->where('s.cat_id','>',0)->paginate($page_size);
        $response = array("status" => "success",'data' => $sounds->all());
        return response()->json($response); 
    }
    
    public function favSounds(Request $request){
        // print_r($request->all());
        // exit;
        // $storagePath  = secure_asset(config("app.sound_path"));
        // $userDpPath = secure_asset(config('app.profile_path'));
        if(auth()->guard('api')->user()){
            $login_id=auth()->guard('api')->user()->user_id;
        }else{
            $login_id=0;
        }
        $storagePath = asset(Storage::url('public/sounds'));
        $imageStoragePath = asset(Storage::url('public/sounds/images'));
        $userDpPath = asset(Storage::url('public/profile_pic'));
        // $storagePath  = asset(config("app.sound_path"));
        // dd($storagePath);
        $page_size = isset($request->page_size) ? $request->page_size : 20;
        
        $sounds = DB::table("favourite_sound as fs")->select(DB::raw("fs.sound_id,title,album,case when s.user_id = 0  then concat('".$storagePath."/',sound_name) else concat('".$storagePath."/',s.user_id,'/',sound_name) end as sound_url,duration,fs.user_id,tags,case when s.image='' then concat('".asset('default/music-icon.png')."') else concat('".$imageStoragePath."/',s.image) end as image_url,ifnull(fs.sound_id,0) as fav,s.used_times,ifnull(s.created_at,'NA') as created_at"))
            ->leftJoin("users as u","fs.user_id","u.user_id")
            ->where("s.deleted",0)
            ->join('sounds as s','fs.sound_id','s.sound_id')
            ->where('fs.user_id',$login_id);
        
        if(isset($request->search) && $request->search!=""){
            $search = $request->search;
            $sounds = $sounds->where('title', 'like', '%' . $search . '%')->orWhere('sound_name', 'like', '%' . $search . '%')->orWhere('tags', 'like', '%' . $search . '%');
        }
        $sounds= $sounds->orderBy("sound_id","desc");
        $sounds= $sounds->where('s.cat_id','>',0)
        // ->toSql();
        ->paginate($page_size);
        
        // dd($sounds->all());
        $response = array("status" => "success",'data' => $sounds->all());
        return response()->json($response); 
    }
    
    public function setFavSound(Request $request){
        // print_r($request->all);
        // exit;
        // dd(auth()->user('api'));
        // $validator = Validator::make($request->all(), [ 
        //     'login_id'          => 'required',              
        //     'app_token'          => 'required'
        // ],[ 
        //     'login_id.required'   => 'You must me logged In.',
        //     'app_token.required'   => 'You must me logged In.'
        // ]);

        if(auth()->guard('api')->user()){
             $validator = Validator::make($request->all(), [ 
            'sound_id'          => 'required',              
        ],[ 
            'sound_id.required'   => 'Sound Id  is required.',
           
        ]);

        if (!$validator->passes()) {
            return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        }else{
        // if (!$validator->passes()) {
        //     return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
        // }else{
            $user_id = auth()->guard('api')->user()->user_id;
            $sound_id = $request->sound_id;
            $set_sound = $request->set;
            $created_at = date("Y-m-d H:i:s");
            $msg="";
            if($set_sound=="true"){
                $chkFav = DB::table("favourite_sound")->where('user_id',$user_id)->where('sound_id',$sound_id)->first();
                if(!$chkFav){
                    $msg = "Sound set as favourite";
                    DB::table("favourite_sound")->insert(array('user_id'=>$user_id,'sound_id'=>$sound_id,'created_at'=>$created_at));
                }else{
                    $msg = "Sound set as favourite";
                    DB::table("favourite_sound")->where('user_id',$user_id)->where('sound_id',$sound_id)->delete();
                    DB::table("favourite_sound")->insert(array('user_id'=>$user_id,'sound_id'=>$sound_id,'created_at'=>$created_at));
                }
                
                
            }else{
                $chkFav = DB::table("favourite_sound")->where('user_id',$user_id)->where('sound_id',$sound_id)->first();
                if($chkFav){
                    DB::table("favourite_sound")->where('user_id',$user_id)->where('sound_id',$sound_id)->delete();
                    $msg = "Sound removed from favourites";
                    
                }
                
            }
            
            $response = array("status" => "success",'msg' => $msg,'set' => $set_sound);
            return response()->json($response); 
        }
        }else{
            return response()->json([
                "status" => "error", "msg" => "Unauthorized user!"
            ]);
        }
    }
    
    public function getSound(Request $request){
        $storagePath  = asset(Storage::url('public/sounds'));
        $imageStoragePath = asset(Storage::url('public/sounds/images'));
        
        if(auth()->guard('api')->user()){
             $validator = Validator::make($request->all(), [ 
                'sound_id'          => 'required',              
            ],[ 
                'sound_id.required'   => 'Sound Id  is required.',
               
            ]);

            if (!$validator->passes()) {
                return response()->json(['status'=>'error','msg'=> $this->_error_string($validator->errors()->all()) ]);
            }else{
                $sound_detail = DB::table("sounds as s")->select(DB::raw("*,case when s.user_id = 0  then concat('".$storagePath."/',sound_name) else concat('".$storagePath."/',s.user_id,'/',sound_name) end as sound_url,case when s.image='' then concat('".asset('default/music-icon.png')."') else concat('".$imageStoragePath."/',s.image) end as image_url"))->leftJoin("users as u","s.user_id","u.user_id")->where("s.deleted",0)->where('s.sound_id',$request->sound_id)->first();
                $response = array("status" => "success",'data' => $sound_detail);
                return response()->json($response); 
            }   
        }else{
            return response()->json([
                "status" => "error", "msg" => "Unauthorized user!"
            ]);
        }
       
    }
}   