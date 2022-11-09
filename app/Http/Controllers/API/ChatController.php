<?php

namespace App\Http\Controllers\API;

use App\Models\Chat;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use App\Events\NewChatMsg;
use App\Events\UserTyping;
use App\Events\ReadMsgEvent;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\ChatResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\User;
use Illuminate\Support\Facades\Storage;
use App\Notifications\UserNotification;

class ChatController extends Controller
{

    private function _error_string($errArray)
    {
        $error_string = '';
        foreach ($errArray as $key) {
            $error_string .= $key . "\n";
        }
        return $error_string;
    }
    
   public function storeMessage($id, Request $request)
    {
       
        $validator = Validator::make($request->all(), [
            // 'app_token'          => 'required',
            // 'user_id'          => 'required',
            'to_user'   => 'required',
            'msg' => 'required'
        ], [
            // 'user_id.required' => 'User Id is required',
            // 'app_token.required' => 'app token is required',
            'to_user.required' => 'to user is required',
            'msg.required' => 'message is required'
        ]);

        if (!$validator->passes()) {
            return response()->json(['status' => 'error', 'msg' => $this->_error_string($validator->errors()->all())]);
        } else {
            // $functions = new Functions();
            // $token_res = $functions->validate_token($request->user_id, $request->app_token);
            if (auth()->guard('api')->user()) {
                $content = [];
                $user_id=auth()->guard('api')->user()->user_id;

                $res=DB::table('blocked_users')
                ->select(DB::raw('block_id'))
                ->whereRaw(DB::raw("(user_id =$user_id and blocked_by= $request->to_user) or (user_id =$request->to_user and blocked_by= $user_id)"))
                ->first();
                if($res){
                    return response()->json(['status'=>false,'msg' => 'this your has blocked you!',"timestamp"=>$request->timestamp]);
                }else{
                    $msg_id = DB::table('chat_messages')->insertGetId([
                        'msg' => $request->msg,
                        'conversation_id' => $id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
    
                    DB::table('chat_chats')->insert([
                        'message_id' => $msg_id,
                        'conversation_id' => $id,
                        'user_id' => $user_id,
                        'type' => 0,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
    
                    $chatRes = DB::table('chat_chats')->insertGetId([
                        'message_id' => $msg_id,
                        'conversation_id' => $id,
                        'user_id' => $request->to_user,
                        'type' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
    
                    $chat = DB::table('chat_chats')->where('id', $chatRes)->first();
    
                    $content['msg'] = $request->msg;
                    $content['type'] = $chat->type;
                    $content['time'] = \Carbon\Carbon::parse($chat->created_at)->diffForHumans();
                    $content['conversation_id'] = $chat->conversation_id;
                    $content['message_id'] = $chat->message_id;
    
                    broadcast(new NewChatMsg($content));
                    
                    $user=User::find(Auth::guard('api')->user()->user_id);
                    // dd($user->user_id);
                    $user_to = User::find($request->to_user);
                    $file_path = '';
                     $small_file_path = '';
                    if ($user->user_dp != '' && $user->user_dp != null) {
                        if (stripos($user->user_dp, 'https://') !== false) {
                            $file_path = $user->user_dp;
                            $small_file_path = $user->user_dp;
                        } else {
                            $file_path = asset(Storage::url('profile_pic/' . $user->user_id . "/" . $user->user_dp));
                            $small_file_path = asset(Storage::url('profile_pic/' . $user->user_id . "/small/" . $user->user_dp));
                        }
                    }
                                  
                    $title=$user->fname.' '.$user->lname;
                    $description=$request->msg;
                    $person_name=$user->fname.' '.$user->lname;
                    $param=array();
                    $param = ['id' => strval($chat->conversation_id), 'type' => 'chat','user_id' => strval($user->user_id),'person_name'=>$person_name,'user_dp'=>$small_file_path];
                    try{
                        $user_to->notify(new UserNotification($title, $description, $small_file_path, $param));
                    }catch (\Exception $e){
                       // dd($e);
                    } 
            
                    return response()->json(['status'=>true,'id' => $chat->id,"timestamp"=>$request->timestamp]);
                }
               
                // return $chat->id;
            }
        }
    }


     public function getMessage($id, Request $request)
    {
        if (isset($request->skip)) {
            $skip = $request->skip;
        } else {
            $skip = 0;
        }
        $messages = DB::table('chat_messages as m')
        ->select(DB::raw('m.msg as msg,m.id as id,DATE_FORMAT(m.created_at, "%Y-%m-%d %H:%i:%s") as sentOn,CASE WHEN IFNULL(c.read_at,0) <> 0 THEN 1 ELSE 0 END as isRead,m.conversation_id as convId,c.user_id as userId'))
        ->join('chat_chats as c','c.message_id','m.id')
        ->where('c.type', 0)->where('c.conversation_id', $id);
        $total = $messages->count();
        $messages = $messages->latest('m.created_at')->skip($skip)->take(20)->get();
        // $messages=Message::whereHas('chats' , function ($query) use ($conversation) {
        //     $query->where('conversation_id', $conversation->id);
        //     // ->where('user_id', Auth::guard('api')->id());
        // })
        // ->paginate();
        
        //   'id' => $this->id,
        //     'msg' => $this->message['msg'],
        //     'sentOn' => date('Y-m-d H:i:s',strtotime($this->created_at)),
        //     'isRead' => $this->read_at($this),
        //     'convId' => $this->conversation_id,
        //     'adId' => $this->product_id,
        //     'userId' => $this->user_id
            
        // dd($messages);
        $data=['data' => $messages, 'total' => $total];
        return response()->json(['status'=>true,'data' => $data]);
    }


    public function readMessage($id)
    {
        $chats = DB::table('chat_chats')
                    ->where('read_at', null)
                    ->where('type', 0)
                    ->where('conversation_id',$id)
                    ->where('user_id', '!=', auth()->guard('api')->user()->user_id)
                    ->get();

        // dd($chats);

        foreach ($chats as $chat) {

            // dd($chat);
            DB::table('chat_chats')->where('id',$chat->id)->update(['read_at' => Carbon::now()]);

            broadcast(new ReadMsgEvent($chat, $chat->conversation_id));
        }

        return response()->json(['status'=>true,'data' => "Done"]);
    }

    public function typingMessage($id,Request $request)
    {
        broadcast(new UserTyping($id,$request->typing));
        return 'Done';
    }

    public function deleteMessage($id)
    {
        $conversation->deleteChats(Auth::id());

        $conversation->chats->count() == 0 ? $conversation->deleteMessages() : "";

        return 'cleared';
    }
}
