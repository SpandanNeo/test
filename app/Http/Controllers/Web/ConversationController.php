<?php
namespace App\Http\Controllers\Web;

use Auth;
use App\User;
use Exception;
use App\Models\Chat;
use App\Models\Favorite;
use App\Events\NewChatMsg;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Events\ConversationEvent;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class ConversationController extends WebBaseController
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

    public function index(){
        $user_id = auth()->guard('web')->user()->user_id;
        $conversations = Conversation::
            whereRaw(DB::raw("(chat_conversations.user_from=$user_id or chat_conversations.user_to=$user_id)"))
            ->pluck('id');
        return $conversations;
    }
    public function store($id)
    {
            if (auth()->guard('web')->user()) {

                $user_to = $id;
                $user_from = auth()->guard('web')->user()->user_id;

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
                        ->where('read_at', '==', null)->where('type', 0)->where('user_id', '!=', $user_from)->count();

                    $modifiedConversation['id'] = $conversation_id;
                    $modifiedConversation['open'] = false;
                    $modifiedConversation['users'] = [$conversation->user_from, $conversation->user_to];
                    $modifiedConversation['unReadCount'] = $chatsCount;
                    // $modifiedConversation = new ApiConversationResource($conversation);
                    // dd($modifiedConversation);
                 
                    broadcast(new ConversationEvent($modifiedConversation, $user_from));
                } else {
                    $conversation_id = $chkIfExist->id;

                    // $chatsCount = DB::table('chat_chats')->where('conversation_id', $conversation_id)
                    //     ->where('read_at', '==', null)->where('type', 0)->where('user_id', '!=', $user_from)->count();

                    // $modifiedConversation['id'] = $conversation_id;
                    // $modifiedConversation['open'] = false;
                    // $modifiedConversation['users'] = [$chkIfExist->user_from, $chkIfExist->user_to];
                    // $modifiedConversation['unReadCount'] = $chatsCount;

                    // $modifiedConversation = new ApiConversationResource($chkIfExist);
                }

                return redirect()->route('web.messages.chat',$conversation_id);
                // return response()->json(['status'=>true,'id'=>$conversation_id]);
            }
        
        // return $modifiedConversation;
    }

    public function deleteMessage(Request $request)
    {
        $user_id = auth()->guard('web')->user()->user_id;
        Chat::where('conversation_id',$request->id)->where('user_id',$user_id)->delete();
        $count=Chat::where('conversation_id',$request->id)->count();
        $count == 0 ? Message::where('conversation_id',$request->id)->delete() : "";

        return true;
    }
}