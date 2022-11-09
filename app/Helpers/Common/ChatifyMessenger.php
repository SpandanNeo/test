<?php
namespace App\Helpers\Common; 
use App\Models\Chat;
use App\Models\Favorite;
use Pusher\Pusher;
use Illuminate\Support\Facades\Auth;
use Exception;

class ChatifyMessenger
{
    /**
     * Allowed extensions to upload attachment
     * [Images / Files]
     *
     * @var
     */
    public static $allowed_images = array('png','jpg','jpeg','gif');
    public static $allowed_files  = array('zip','rar','txt');

    /**
     * This method returns the allowed image extensions
     * to attach with the message.
     *
     * @return array
     */
    public static function getAllowedImages(){
        return self::$allowed_images;
    }

    /**
     * This method returns the allowed file extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedFiles(){
        return self::$allowed_files;
    }

    /**
     * Returns an array contains messenger's colors
     *
     * @return array
     */
    public static function getMessengerColors(){
        return [
            '1' => '#2180f3',
            '2' => '#2196F3',
            '3' => '#00BCD4',
            '4' => '#3F51B5',
            '5' => '#673AB7',
            '6' => '#4CAF50',
            '7' => '#FFC107',
            '8' => '#FF9800',
            '9' => '#ff2522',
            '10' => '#9C27B0',
        ];
    }

    /**
     * Pusher connection
     */
    public function pusher()
    {
        return new Pusher(
            config('chatify.pusher.key'),
            config('chatify.pusher.secret'),
            config('chatify.pusher.app_id'),
            [
                'cluster' => config('chatify.pusher.options.cluster'),
                'useTLS' => config('chatify.pusher.options.useTLS')
            ]
        );
    }

    /**
     * Trigger an event using Pusher
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    public static function push($channel, $event, $data)
    {
        return self::pusher()->trigger($channel, $event, $data);
    }

    /**
     * Authintication for pusher
     *
     * @param string $channelName
     * @param string $socket_id
     * @param array $data
     * @return void
     */
    public function pusherAuth($channelName, $socket_id, $data = []){
        return $this->pusher()->socket_auth($channelName, $socket_id, $data);
    }

    /**
     * Fetch message by id and return the message card
     * view as a response.
     *
     * @param int $id
     * @return array
     */
    public static function fetchMessage($id){
        $attachment = $attachment_type = $attachment_title = null;
        $msg = Chat::where('id',$id)->first();

        // If message has attachment
        // if($msg->attachment){
        //     // Get attachment and attachment title
        //     $att = explode(',',$msg->attachment);
        //     $attachment       = $att[0];
        //     $attachment_title = $att[1];

        //     // determine the type of the attachment
        //     $ext = pathinfo($attachment, PATHINFO_EXTENSION);
        //     $attachment_type = in_array($ext,$this->getAllowedImages()) ? 'image' : 'file';
        // }

        return [
            'id' => $msg->id,
            'from_id' => $msg->from_id,
            'to_id' => $msg->to_id,
            'message' => $msg->msg,
            // 'attachment' => [$attachment, $attachment_title, $attachment_type],
            'time' => $msg->sent_on->diffForHumans(),
            'fullTime' => $msg->sent_on,
            'viewType' => ($msg->from_id == Auth::user()->user_id) ? 'sender' : 'default',
            'seen' => $msg->is_read,
        ];
    }

    /**
     * Return a message card with the given data.
     *
     * @param array $data
     * @param string $viewType
     * @return void
     */
    public static function messageCard($data, $viewType = null){
        $data['viewType'] = ($viewType) ? $viewType : $data['viewType'];
        return view('layouts.chatify.messageCard',$data)->render();
    }

    /**
     * Default fetch messages query between a Sender and Receiver.
     *
     * @param int $user_id
     * @return Collection
     */
    public static function fetchMessagesQuery($user_id){
        return Chat::where('from_id',Auth::user()->user_id)->where('to_id',$user_id)
                    ->orWhere('from_id',$user_id)->where('to_id',Auth::user()->user_id);
    }

    /**
     * create a new message to database
     *
     * @param array $data
     * @return void
     */
    public static function newMessage($data){
        $message = new Chat();
        // $message->type = $data['type'];
        $message->from_id = $data['from_id'];
        $message->to_id = $data['to_id'];
        $message->msg = $data['msg'];
        $message->sent_on =$data['sent_on'];
        $message->save();
        return $message->id;
    }

    /**
     * Make messages between the sender [Auth user] and
     * the receiver [User id] as seen.
     *
     * @param int $user_id
     * @return bool
     */
    public static function makeSeen($user_id){
        Chat::Where('from_id',$user_id)
                ->where('to_id',Auth::user()->user_id)
                ->where('is_read',0)
                ->update(['is_read' => 1]);
      
        return 1;
    }

    /**
     * Get last message for a specific user
     *
     * @param int $user_id
     * @return Collection
     */
    public static function getLastMessageQuery($user_id){
        return self::fetchMessagesQuery($user_id)->orderBy('sent_on','DESC')->first();
    }

    /**
     * Count Unseen messages
     *
     * @param int $user_id
     * @return Collection
     */
    public static function countUnseenMessages($user_id){
        return Chat::where('from_id',$user_id)->where('to_id',Auth::user()->user_id)->where('is_read',0)->count();
    }

    /**
     * Get user list's item data [Contact Itme]
     * (e.g. User data, Last message, Unseen Counter...)
     *
     * @param int $messenger_id
     * @param Collection $user
     * @return void
     */
    public static function getContactItem($messenger_id, $user){
        // get last message
        $lastMessage = self::getLastMessageQuery($user->user_id);

        // Get Unseen messages counter
        $unseenCounter = self::countUnseenMessages($user->user_id);

        return view('layouts.chatify.listItem', [
            'get' => 'users',
            'user' => $user,
            'lastMessage' => $lastMessage,
            'unseenCounter' => $unseenCounter,
            'type'=>'user',
            'id' => $messenger_id,
        ])->render();
    }

    /**
     * Check if a user in the favorite list
     *
     * @param int $user_id
     * @return boolean
     */
    public static function inFavorite($user_id){
        return Favorite::where('user_id', Auth::user()->user_id)
                        ->where('favorite_id', $user_id)->count() > 0
                        ? true : false;

    }

    /**
     * Make user in favorite list
     *
     * @param int $user_id
     * @param int $star
     * @return boolean
     */
    public static function makeInFavorite($user_id, $action){
        if ($action > 0) {
            // Star
            $star = new Favorite();
            $star->user_id = Auth::user()->user_id;
            $star->favorite_id = $user_id;
            $star->save();
            return $star ? true : false;
        }else{
            // UnStar
            $star = Favorite::where('user_id',Auth::user()->user_id)->where('favorite_id',$user_id)->delete();
            return $star ? true : false;
        }
    }

    /**
     * Get shared photos of the conversation
     *
     * @param int $user_id
     * @return array
     */
    public static function getSharedPhotos($user_id){
        $images = array(); // Default
        // Get messages
        $msgs = self::fetchMessagesQuery($user_id)->orderBy('sent_on','DESC');
        if($msgs->count() > 0){
            foreach ($msgs->get() as $msg) {
                // If message has attachment
                if($msg->attachment){
                    $attachment = explode(',',$msg->attachment)[0]; // Attachment
                    // determine the type of the attachment
                    in_array(pathinfo($attachment, PATHINFO_EXTENSION), $this->getAllowedImages())
                    ? array_push($images, $attachment) : '';
                }
            }
        }
        return $images;

    }

    /**
     * Delete Conversation
     *
     * @param int $user_id
     * @return boolean
     */
    public static function deleteConversation($user_id){
        try {
            foreach (self::fetchMessagesQuery($user_id)->get() as $msg) {
                // delete from database
                $msg->delete();
                // delete file attached if exist
                // if ($msg->attachment) {
                //     $path = storage_path('app/public/'.config('chatify.attachments.folder').'/'.explode(',', $msg->attachment)[0]);
                //     if(file_exists($path)){
                //         @unlink($path);
                //     }
                // }
            }
            return 1;
        }catch(Exception $e) {
            return 0;
        }
    }

}
