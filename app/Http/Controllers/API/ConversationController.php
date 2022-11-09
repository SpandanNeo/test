<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Events\ConversationEvent;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\ApiConversationResource;
use App\User;
use App\Models\Conversation;

class ConversationController extends Controller
{

    private function _error_string($errArray)
    {
        $error_string = '';
        foreach ($errArray as $key) {
            $error_string .= $key . "\n";
        }
        return $error_string;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'app_token'          => 'required',
            // 'login_id'          => 'required',
            'user_id'          => 'required'
        ], [
            // 'login_id.required' => 'Login Id is required',
            'user_id.required' => 'User Id is required',
            // 'app_token.required' => 'app token is required',
        ]);

        if (!$validator->passes()) {
            return response()->json(['status' => 'error', 'msg' => $this->_error_string($validator->errors()->all())]);
        } else {
          
            if (auth()->guard('api')->user()) {

                $user_to = $request->user_id;
                $user_from = auth()->guard('api')->user()->user_id;

                $chkIfExist = DB::table('chat_conversations')
                    ->whereRaw(DB::raw("((user_from= $user_from and user_to= $user_to ) or (user_from=$user_to and user_to= $user_from ) )"))
                    ->first();

                if (!$chkIfExist) {
                    $conversation_id = DB::table('chat_conversations')->insertGetId([
                        'user_from' => $user_from,
                        'user_to' => $user_to,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $conversation = DB::table('chat_conversations')->where('id', $conversation_id)->first();

                    $chatsCount = DB::table('chat_chats')->where('conversation_id', $conversation_id)
                        ->where('read_at', null)->where('type', 0)->where('user_id', '!=', $user_from)->count();

                    $modifiedConversation['id'] = $conversation_id;
                    $modifiedConversation['open'] = false;
                    $modifiedConversation['users'] = [$conversation->user_from, $conversation->user_to];
                    $modifiedConversation['unReadCount'] = $chatsCount;
                    // $modifiedConversation = new ApiConversationResource($conversation);

                    broadcast(new ConversationEvent($modifiedConversation, $user_from));
                } else {
                    $conversation_id = $chkIfExist->id;

                    $chatsCount = DB::table('chat_chats')->where('conversation_id', $conversation_id)
                        ->where('read_at', null)->where('type', 0)->where('user_id', '!=', $user_from)->count();

                    $modifiedConversation['id'] = $conversation_id;
                    $modifiedConversation['open'] = false;
                    $modifiedConversation['users'] = [$chkIfExist->user_from, $chkIfExist->user_to];
                    $modifiedConversation['unReadCount'] = $chatsCount;

                    // $modifiedConversation = new ApiConversationResource($chkIfExist);
                }

                return response()->json(['status'=>true,'id'=>$conversation_id]);
            }
        }
        // return $modifiedConversation;
    }

    public function getConversation(Request $request)
    {
            if (auth()->guard('api')->user()) {
                $user_id=auth()->guard('api')->user()->user_id;
                
            //   $conversations = DB::table('chat_conversations')
            //                     ->select(DB::raw('chat_conversations.id as id,CASE WHEN IFNULL(cc.read_at,0) <> 0 THEN 1 ELSE 0 END as isRead ,chat_conversations.user_from as user_from,chat_conversations.user_to as user_to,max(chat_messages.id) as last_msg,chat_messages.msg as msg,chat_messages.created_at as time'))
            //                     ->leftJoin('chat_messages', function ($join) {
            //                         $join->on('chat_conversations.id', '=', 'chat_messages.conversation_id')
            //                         ->orderBy('chat_messages.id','desc');
            //                       // ->having(DB::raw('max(chat_messages.id)'));
            //                         // ->orderBy(DB::raw('max(chat_messages.id)'))
            //                         // ->first();
            //                     })
            //                     ->leftJoin('chat_chats as cc','cc.message_id','chat_messages.id')
            //                     ->orderBy('chat_messages.id','desc')
            //                     // ->groupBy('chat_messages.conversation_id')
            //                     ->whereRaw(DB::raw("chat_conversations.user_from= $user_id or chat_conversations.user_to= $user_id"));
            //                 $count = $conversations->count();
            //                 $conversations = $conversations->paginate(10);
        
            $conversations = Conversation::whereHas('chats',function($q) use($user_id){
                    $q->whereHas('message',function($q) use($user_id){
                        $q->where('user_id',$user_id);
                    });
                })->with(array('messages_list' => function ($q) {
                    $q->orderBy('created_at', 'DESC');
                }))
                ->selectRaw("chat_conversations.*,(SELECT COUNT(IFNULL(chat_chats.read_at,0)) from chat_chats where IFNULL(chat_chats.read_at,0)=0 and chat_chats.type=0 and chat_chats.user_id!=$user_id and chat_conversations.id=chat_chats.conversation_id) as isRead,(SELECT MAX(created_at) from chat_messages WHERE chat_messages.conversation_id=chat_conversations.id) as latest_message_on")
                // ->selectRaw("chat_conversations.*, (SELECT chat_chats.read_at from chat_chats where chat_chats.user_id!=$user_id and chat_conversations.id=chat_chats.conversation_id) as count,(SELECT MAX(created_at) from chat_messages WHERE chat_messages.conversation_id=chat_conversations.id) as latest_message_on")
                ->whereRaw(DB::raw("(chat_conversations.user_from=$user_id or chat_conversations.user_to=$user_id)"))
                ->orderBy("latest_message_on", "DESC");
            $count = $conversations->count();
             if($request->search !="") {
                 $conversations = $conversations->get();
             }else{
                 $conversations = $conversations->paginate(10);
             }
            
             
                $conversation = [];
                $conver =[];
                foreach ($conversations as $con) {
                    // $conversation=$con;
                    // dd($con);
                    $user_from = $con->user_from;
                    $user_to = $con->user_to;
                    if ($user_from == $user_id) {
                        $from_user = $user_to;
                    } else {
                        $from_user = $user_from;
                    }

                    $userDpPath = asset(Storage::url('public/profile_pic'));
                    $user = DB::table('users')->select(DB::raw("*,case when user_dp !='' THEN case when INSTR(user_dp,'https://') > 0 THEN user_dp ELSE concat('" . $userDpPath . "/',user_id,'/small/',user_dp)  END ELSE '' END as user_dp"))
                                ->where('user_id', $from_user);
                        if($request->search !="") {
    					    $searchString = $request->search;
    					    $user=$user->whereRaw(DB::raw("( ( username like '%".$searchString."%' ) or ( concat(fname,' ',lname) like '%".$searchString."%') )" ));
    					}
                                $user=$user->first();
                    // $custom = collect(['user' => $user]);
                    if($user){
                        $conversation['id'] = $con->id;
                        $conversation['user_id'] = $user->user_id;
                        $conversation['person_name'] = $user->fname . ' ' . $user->lname;
                        $conversation['username'] = $user->username;
                        $conversation['user_dp'] = $user->user_dp;
                         $conversation['message'] = $con->messages_list->first()->msg;
                        $conversation['isRead'] = $con->isRead;
                        // $conversation['time '] = \Carbon\Carbon::parse($con->time)->diffForHumans();
                        $conversation['time '] = \Carbon\Carbon::parse($con->messages_list->first()->created_at)->diffForHumans();
                        // $conversation = $custom->merge($conversation);
                        $conver[]=$conversation;
                    }
                }
                
                
                return response()->json(['status' => true, 'data' => $conver, 'totalRecords' => $count]);
                // dd(ConversationListResource::collection($conversation));
            }
        
    }
    
    public function getOnlineUsers(Request $request){
        if(auth()->guard('api')->user()){
            $ids=explode(',',$request->ids);
            $user_ids=array_slice($ids, 0, 20);
            $user_id=auth()->guard('api')->user()->user_id;
            $users= User::whereIn('user_id',$user_ids)
                        ->where('user_id','!=',$user_id)
                        ->get();
            $data=array();
              
           foreach($users as $user){
                 $res=array();
                 if($user->user_dp!=""){
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
                 }else{
                     $file_path="";
                 }
			
               $res['id']=$user->user_id;
               $res['name']=$user->fname.' '.$user->lname;
               $res['user_dp']=$file_path;
               array_push($data,$res);
           }
           $response = array( "status" => "success", "data" => $data );
        //   dd($data);
        }else{
            $response = array( "status" => "failed", "msg" => "Invalid user!" );
        }
        return response()->json($response); 
    } 
    
    public function chatUsers(Request $request){
		
			if(auth()->guard('api')->user()){
			    $login_id=auth()->guard('api')->user()->user_id;
			    $userDpPath = asset(Storage::url('public/profile_pic'));
				$limit = 10;
				$user=User::find($login_id);
			
				if($user->chat_with=='FL'){
    				//following
    				$users = DB::table("users as u")
    				->select(DB::raw("u.user_id,
    					case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp,
    					concat('@',u.username) as username,u.fname,u.lname"))
    					->leftJoin('follow as f', function ($join) use ($request,$login_id){
    						$join->on('u.user_id','=','f.follow_to')
    						->where('f.follow_by',$login_id);
    					})
    					->leftJoin('follow as f2', function ($join) use ($request,$login_id){
    						$join->on('u.user_id','=','f2.follow_by')
    						->where('f2.follow_to',$login_id);
    					});
    					$users = $users->whereRaw( DB::Raw(' f.follow_id is NOT null or f2.follow_id is NOT null '));
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
    					
    					if($request->search !="") {
    					    $searchString = $request->search;
    					    $users=$users->whereRaw(DB::raw("( ( u.username like '%".$searchString."%' ) or ( concat(u.fname,' ',u.lname) like '%".$searchString."%') )" ));
    					}
    				
    				// 	$users=$users->where('f.follow_to','<>', $login_id);
    				// 	$users=$users->where('f.follow_by', $login_id);
    					$users=$users->where("u.deleted",0)
    					->where("u.active",1);
    				
    			
    				$users = $users->orderBy('u.user_id','desc')->groupBy('u.user_id');
    				$users= $users->paginate($limit);   
    				// dd($users);
				}else{
    				//followers
    				$users = DB::table("users as u")->select(DB::raw("u.user_id,
    					case when u.user_dp !='' THEN case when INSTR(u.user_dp,'https://') > 0 THEN u.user_dp ELSE concat('".$userDpPath."/',u.user_id,'/small/',u.user_dp)  END ELSE '' END as user_dp,
    					concat('@',u.username) as username,u.fname,u.lname"))
    				->leftJoin('follow as f', function ($join) use ($request){
    					$join->on('u.user_id','=','f.follow_by');
    				// 	->where('f.follow_to',$request->login_id);
    				});
    				// ->leftJoin('follow as f2', function ($join) use ($request,$login_id){
    				// 		$join->on('u.user_id','=','f2.follow_to')
    				// 		->where('f2.follow_by',$login_id);
    				// 	});
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
                    if($request->search !="") {
    					    $searchString = $request->search;
    					    $users=$users->whereRaw(DB::raw("( ( u.username like '%".$searchString."%' ) or ( concat(u.fname,' ',u.lname) like '%".$searchString."%') )" ));
    					}
                    $users=$users->where('f.follow_by','<>', $login_id);
    				$users=$users->where('f.follow_to', $login_id);
    				$users=$users->where("u.deleted",0)
    				->where("u.active",1);
    				
    				$users = $users->orderBy('u.user_id','desc');
    				$users= $users->paginate($limit);
				}
				$total_records=$users->total();   
				
				$response = array("status" => "success",'data' => $users,'total_records'=>$total_records);
			}else{
			    return response()->json([
                    "status" => "error", "msg" => "Unauthorized user!"
                ]);
			}
		
		
		return response()->json($response); 
	
	}
	
}
