<?php

namespace App\Http\Controllers\Web;

use Auth;
use App\User;
use Exception;
use App\Models\Chat;
use App\Models\Message;
use App\Models\Favorite;
use App\Events\NewChatMsg;
use App\Events\UserTyping;
use App\Events\ReadMsgEvent;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;


class MessagesController extends WebBaseController
{

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    //protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function index($id = null)
    {
        // prepare id        
        // return response()->json(['status' => true, 'data' => $conver, 'totalRecords' => $count]);

        return view('web.messages');

        //return view('web.messages');
    }

    public function chatMessages($id,Request $request)
    {
        if(isset($request->skip)){
            $skip=$request->skip;
        }else{
            $skip=0;
        }
        $user_id = auth()->guard('web')->user()->user_id;
        $data = DB::table('chat_chats as cc')
            ->select(DB::raw('m.id as id,cc.id as chat_id,cc.type as type,m.msg as msg,m.created_at as created_at'))
            ->join('chat_messages as m', 'm.id', 'cc.message_id')
            ->where('cc.user_id', $user_id)->where('cc.conversation_id', $id)
            ->orderBy('m.created_at', 'desc');
        $total=$data->count();
            // ->paginate(20);
        $data=$data->skip($skip)
            ->take(30);
        $loaded=20;
        $data=$data->get()
            ->reverse()
            ->values();
        // dd($data);

        if(isset($request->type)){
            $html='';
            foreach($data as $con){
                $html.='<div class="row m-0">';
                $html.='<div class="chk-box col-1">';
                $html.='<input type="checkbox" class="edit-convo-id" data-val="'.$con->chat_id.'"/>';
                $html.='</div>';
                if($con->type==1){
                    $html.='<div class="message-card col-11" data-id="1">';
                    $html.='<p>'.$con->msg;
                    $html.='<sub title="2021-10-12 06:52:24">'. \Carbon\Carbon::parse($con->created_at)->diffForHumans() .'</sub>';

                    $html.='</p>';
                    // $html.='<div class="chk-box">';
                    // $html.='<input type="checkbox" class="edit-convo-id" data-val="'.$con->chat_id.'"/>';
                    // $html.='</div>';
                    $html.='</div>';
                }else{
                
                    $html.='<div class="message-card mc-sender col-11" data-id="2">';
   
                    // $html.='<div class="chk-box">';
                    // $html.='<input type="checkbox" class="edit-convo-id" data-val="'.$con->chat_id.'"/>';
                    // $html.='</div>';
                    $html.='<p>'.$con->msg;
                    $html.='<sub title="2021-10-20 07:00:58" class="message-time">';
                    $html.='<svg class="svg-inline--fa fa-check fa-w-16 seen" aria-hidden="true" focusable="false" data-prefix="fas" data-icon="check" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">';
                    $html.='<path fill="currentColor" d="M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z"></path>';
                    $html.='</svg> '. \Carbon\Carbon::parse($con->created_at)->diffForHumans() .'</sub>';
                    $html.='</p>';
                    $html.='</div>';
                }

                $html.='</div>';

            }

            return response()->json(['status'=>true,'html'=>$html,'total'=>$total,'loaded'=>$loaded,'data'=>$data]);
        }

        $conversation = Conversation::find($id);
        $user_id = auth()->user('web')->user_id;
        if ($conversation->user_from == $user_id) {
            $u_id = $conversation->user_to;
        } else {
            $u_id = $conversation->user_from;
        }
        // dd($data);
        $userDpPath = asset(Storage::url('public/profile_pic'));
        $user = DB::table('users')->select(DB::raw("*,case when user_dp !='' THEN case when INSTR(user_dp,'https://') > 0 THEN user_dp ELSE concat('" . $userDpPath . "/',user_id,'/small/',user_dp)  END ELSE '' END as user_dp"))->where('user_id', $u_id)->first();
        // $data=$conversation->chats->where('user_id', auth()->user('web')->user_id);

        return view('web.messages-chat', compact('data', 'user', 'id'));
    }

    public function storeMessage($id, Request $request)
    {
        if (auth()->user('web')) {
            $content = [];
            $user_id = auth()->user('web')->user_id;

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

            broadcast(new NewChatMsg($content))->toOthers();
        }
    }

    public function typingMessage($id,Request $request)
    {
        broadcast(new UserTyping($id,$request->typing))->toOthers();
        return 'Done';
    }

    public function readMessage($id)
    {
        $chats = Chat::where('conversation_id',$id)->where('read_at', null)->where('type', 0)->where('user_id', '!=', Auth::id())->get();

        foreach ($chats as $chat) {

            // dd($chat);
            $chat->update(['read_at' => Carbon::now()]);

            broadcast(new ReadMsgEvent(new ChatResource($chat), $chat->conversation_id));
        }

        return 'Done';
    }

    public function deleteMessage(Request $request)
    {
        $user_id = auth()->guard('web')->user()->user_id;

        $ids = explode(',', $request->ids);
        foreach ($ids as $id) {
            Chat::whereIn('id',$ids)->where('user_id',$user_id)->delete();
        }

        //return response()->json(['status' => 'success']);
        

       // Chat::where('conversation_id',$request->id)->where('user_id',$user_id)->delete();
        // $count=Chat::where('conversation_id',$request->id)->count();
        // $count == 0 ? Message::where('conversation_id',$request->id)->delete() : "";

        return true;
    }

    public function search(Request $request)
    {
        $getRecords = null;
        $input = trim(filter_var($request['input'], FILTER_SANITIZE_STRING));
        $records = User::where('fname', 'LIKE', "%{$input}%")->where('user_id', '<>', auth()->user()->user_id);
        foreach ($records->get() as $record) {
            $getRecords .= view('layouts.chatify.listItem', [
                'get' => 'search_item',
                'type' => 'user',
                'user' => $record,
            ])->render();
        }
        // send the response
        return Response::json([
            'records' => $records->count() > 0
                ? $getRecords
                : '<p class="message-hint"><span>Nothing to show.</span></p>',
            'addData' => 'html'
        ], 200);
    }


}
