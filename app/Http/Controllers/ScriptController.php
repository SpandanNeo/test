<?php 
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ScriptController extends Controller
{
    public function moveChatOldToNew(Request $request){

        DB::table('chat_chats')->truncate();
        DB::table('chat_conversations')->truncate();
        DB::table('chat_messages')->truncate();

        $oldChats = DB::table('chats')
                        ->select('*')
						->get();

        if( $oldChats->count() > 0 ) {
            $convId = 0;
            $msgId = 0;
            foreach($oldChats as $key=>$chat) {    
                $convertsationExist = DB::table('chat_conversations')
                                        ->select('*')
                                        ->whereRaw(DB::raw(" (user_from=".$chat->from_id." and user_to=".$chat->to_id.") OR (user_from=".$chat->to_id." and user_to=".$chat->from_id.") "))
                                        ->first();
                if(!$convertsationExist){
                    $convId = DB::table('chat_conversations')->insertGetId(['user_from' => $chat->from_id , 'user_to' => $chat->to_id,'created_at' => date('Y-m-d H:i:s')]);
                }else{
                    $convId= $convertsationExist->id;
                }
                $msgId = DB::table('chat_messages')->insertGetId(['msg' => $chat->msg , 'conversation_id' => $convId,'created_at' => $chat->sent_on]);

                DB::table('chat_chats')->insertGetId(['message_id' => $msgId , 'conversation_id' => $convId,'user_id' => $chat->from_id , 'type' => 0,'created_at' => $chat->sent_on,'read_at' => $chat->read_on]);
                DB::table('chat_chats')->insertGetId(['message_id' => $msgId , 'conversation_id' => $convId,'user_id' => $chat->to_id , 'type' => 1,'created_at' => $chat->sent_on,'read_at' => $chat->read_on]);
            }
        }

        DB::table('settings')->where('setting_id',1)->update(['migrated'=>1]);
        return response()->json(['status' => true]);
        echo "Done";
    }
}
