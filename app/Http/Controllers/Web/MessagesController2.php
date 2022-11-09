<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Auth;
use Exception;
use App\Models\Chat;
use App\Models\Favorite;
use App\Helpers\Common\ChatifyMessenger as Chatify;
use Illuminate\Support\Facades\Response;
use App\Helpers\Common\Functions;
use Illuminate\Support\Facades\DB;

class MessagesController2 extends WebBaseController
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

    public function index($id=null)
    {

        $route = (in_array(\Request::route()->getName(), ['user', config('chatify.path')]))
            ? 'user'
            : \Request::route()->getName();

        // prepare id
        return view('web.messages', [
            'id' => ($id == null) ? 0 : $route . '_' . $id,
            'route' => $route,
            // 'messengerColor' => '#2180f3',
            // 'dark_mode' => 'light',
            'messengerColor' => Auth::user()->messenger_color,
            'dark_mode' => Auth::user()->dark_mode < 1 ? 'light' : 'dark',
            
        ]);

        //return view('web.messages');
    }

    public function send(Request $request)
    {
        // default variables
        $error_msg = $attachment = $attachment_title = null;

        // if there is attachment [file]
        if ($request->hasFile('file')) {
            // allowed extensions
            $allowed_images = Chatify::getAllowedImages();
            $allowed_files  = Chatify::getAllowedFiles();
            $allowed        = array_merge($allowed_images, $allowed_files);

            $file = $request->file('file');
            // if size less than 150MB
            if ($file->getSize() < 150000000) {
                if (in_array($file->getClientOriginalExtension(), $allowed)) {
                    // get attachment name
                    $attachment_title = $file->getClientOriginalName();
                    // upload attachment and store the new name
                    $attachment = Str::uuid() . "." . $file->getClientOriginalExtension();
                    $file->storeAs("public/" . config('chatify.attachments.folder'), $attachment);
                } else {
                    $error_msg = "File extension not allowed!";
                }
            } else {
                $error_msg = "File size is too long!";
            }
        }

        if (!$error_msg) {
            $blocked=false;
            $authUserId=auth()->user()->user_id;
            if($authUserId > 0) {
                $blocked = DB::table('blocked_users')
                ->whereRaw('(user_id = '.$request['id'].' and blocked_by ='. $authUserId.') or (user_id = '.$authUserId.' and blocked_by ='.$request['id'].')')->exists();
            }
            if($blocked==false){
                // send to database
                // $messageID = mt_rand(9, 999999999) + time();
                $messageID= Chatify::newMessage([
                    'type' => $request['type'],
                    'from_id' => Auth::user()->user_id,
                    'to_id' => $request['id'],
                    'sent_on' => date('Y-m-d H:i:s'),
                    'msg' => trim(htmlentities($request['message'])),
                    'attachment' => ($attachment) ? $attachment . ',' . $attachment_title : null,
                ]);

                // fetch message to send it with the response
                $messageData = Chatify::fetchMessage($messageID);

                // send to user using pusher
                // Chatify::push('private-chatify', 'messaging', [
                //     'from_id' => Auth::user()->user_id,
                //     'to_id' => $request['id'],
                //     'message' => Chatify::messageCard($messageData, 'default')
                // ]);
            }
        }

        // send the response
        return Response::json([
            'status' => '200',
            'error' => $error_msg ? 1 : 0,
            'error_msg' => $error_msg,
            'msg' =>$request['message'],
            'message' => Chatify::messageCard(@$messageData),
            'sent_on' => date('Y-m-d H:i:s'),
            'tempID' => $request['temporaryMsgId'],
            'time' => now()->diffForHumans(),
        ]);
    }


    public function updateSettings(Request $request)
    {
        $msg = null;
        $error = $success = 0;

        // dark mode
        if ($request['dark_mode']) {
            $request['dark_mode'] == "dark"
                ? User::where('user_id', Auth::user()->user_id)->update(['dark_mode' => 1])  // Make Dark
                : User::where('user_id', Auth::user()->user_id)->update(['dark_mode' => 0]); // Make Light
        }

        // If messenger color selected
        if ($request['messengerColor']) {

            $messenger_color = explode('-', trim(filter_var($request['messengerColor'], FILTER_SANITIZE_STRING)));
            $messenger_color = Chatify::getMessengerColors()[$messenger_color[1]];
            User::where('user_id', Auth::user()->user_id)
                ->update(['messenger_color' => $messenger_color]);
        }
        // if there is a [file]
        if ($request->hasFile('avatar')) {
            // allowed extensions
            $allowed_images = Chatify::getAllowedImages();

            $file = $request->file('avatar');
            // if size less than 150MB
            if ($file->getSize() < 150000000) {
                if (in_array($file->getClientOriginalExtension(), $allowed_images)) {
                    // delete the older one
                    // if (Auth::user()->avatar != config('chatify.user_avatar.default')) {
                    //     $path = storage_path('app/public/' . config('chatify.user_avatar.folder') . '/' . Auth::user()->avatar);
                    //     if (file_exists($path)) {
                    //         @unlink($path);
                    //     }
                    // }
                    // // upload
                    // $avatar = Str::uuid() . "." . $file->getClientOriginalExtension();
                    // $update = User::where('id', Auth::user()->id)->update(['avatar' => $avatar]);
                    // $file->storeAs("public/" . config('chatify.user_avatar.folder'), $avatar);

                    $functions = new Functions();
                
                    $path = 'public/profile_pic/'.Auth::user()->user_id;
                    
                    $filenametostore = request()->file('avatar')->store($path);  
                    \Storage::setVisibility($filenametostore, 'public');
                    $fileArray = explode('/',$filenametostore);  
                    $fileName = array_pop($fileArray); 
                    // dd(asset(Storage::url('public/profile_pic/'.$user->user_id.'/'.$fileName)));
                    $functions->_cropImage($file,300,300,0,0,$path.'/small',$fileName);
                    \Storage::setVisibility($path.'/small/'.$fileName, 'public');
                    $update = User::where('user_id', Auth::user()->user_id)->update(['user_dp' => $fileName]);
                    $success = $update ? 1 : 0;
                } else {
                    $msg = "File extension not allowed!";
                    $error = 1;
                }
            } else {
                $msg = "File extension not allowed!";
                $error = 1;
            }
        }

        // send the response
        return Response::json([
            'status' => $success ? 1 : 0,
            'error' => $error ? 1 : 0,
            'message' => $error ? $msg : 0,
        ], 200);
    }

    public function pusherAuth(Request $request)
    {
        
    }

    public function getContacts(Request $request)
    {
        // get all users that received/sent message from/to [Auth user]
        $users = Chat::join('users',  function ($join) {
            $join->on('chats.from_id', '=', 'users.user_id')
                ->orOn('chats.to_id', '=', 'users.user_id');
        })
            ->where('chats.from_id', Auth::user()->user_id)
            ->orWhere('chats.to_id', Auth::user()->user_id)
            ->orderBy('chats.sent_on', 'desc')
            ->get()
            ->unique('user_id');

        if ($users->count() > 0) {
            // fetch contacts
            $contacts = null;
            foreach ($users as $user) {
                if ($user->user_id != Auth::user()->user_id) {
                    // Get user data
                    $userCollection = User::where('user_id', $user->user_id)->first();
                    $contacts .= Chatify::getContactItem($request['messenger_id'], $userCollection);
                }
            }
        }

        // send the response
        return \Response::json([
            'contacts' => $users->count() > 0 ? $contacts : '<br><p class="message-hint"><span>Your contatct list is empty</span></p>',
        ], 200);
    }

       /**
     * Get favorites list
     *
     * @param Request $request
     * @return void
     */
    public function getFavorites(Request $request)
    {
        $favoritesList = null;
        $favorites = Favorite::where('user_id', Auth::user()->user_id);
        foreach ($favorites->get() as $favorite) {
            // get user data
            $user = User::where('user_id', $favorite->favorite_id)->first();
            $favoritesList .= view('layouts.chatify.favorite', [
                'user' => $user,
            ]);
        }
        // send the response
        return Response::json([
            'favorites' => $favorites->count() > 0
                ? $favoritesList
                : '<p class="message-hint"><span>Your favorite list is empty</span></p>',
        ], 200);
    }
    
    public function favorite(Request $request)
    {
        // check action [star/unstar]
        if (Chatify::inFavorite($request['user_id'])) {
            // UnStar
            Chatify::makeInFavorite($request['user_id'], 0);
            $status = 0;
        } else {
            // Star
            Chatify::makeInFavorite($request['user_id'], 1);
            $status = 1;
        }

        // send the response
        return Response::json([
            'status' => @$status,
        ], 200);
    }

    public function idFetchData(Request $request)
    {
        // Favorite
        $favorite = Chatify::inFavorite($request['id']);
        $fetch=array();
        // User data
        if ($request['type'] == 'user') {
            $fetch = User::where('user_id', $request['id'])->first();
        }

        // send the response
        return Response::json([
            'favorite' => $favorite,
            'fetch' => $fetch,
            'user_avatar' => Functions::getProfilepic($fetch->user_id,$fetch->user_dp),
        ]);
    }

    public function search(Request $request)
    {
        $getRecords = null;
        $input = trim(filter_var($request['input'], FILTER_SANITIZE_STRING));
        $records = User::where('fname', 'LIKE', "%{$input}%")->where('user_id','<>',auth()->user()->user_id);
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

    public function sharedPhotos(Request $request)
    {
        $shared = Chatify::getSharedPhotos($request['user_id']);
        $sharedPhotos = null;

        // shared with its template
        for ($i = 0; $i < count($shared); $i++) {
            $sharedPhotos .= view('layouts.chatify.listItem', [
                'get' => 'sharedPhoto',
                'image' => asset('storage/attachments/' . $shared[$i]),
            ])->render();
        }
        // send the response
        return Response::json([
            'shared' => count($shared) > 0 ? $sharedPhotos : '<p class="message-hint"><span>Nothing shared yet</span></p>',
        ], 200);
    }

    public function deleteConversation(Request $request)
    {
        // delete
        $delete = Chatify::deleteConversation($request['id']);

        // send the response
        return Response::json([
            'deleted' => $delete ? 1 : 0,
        ], 200);
    }

    public function fetch(Request $request)
    {
        // messages variable
        $allMessages = null;

        // fetch messages
        $query = Chatify::fetchMessagesQuery($request['id'])->orderBy('sent_on', 'asc');
        $messages = $query->get();
        $blocked=false;
        $authUserId=auth()->user()->user_id;
            if($authUserId > 0) {
                $blocked = DB::table('blocked_users')
                ->whereRaw('(user_id = '.$request['id'].' and blocked_by ='. $authUserId.') or (user_id = '.$authUserId.' and blocked_by ='.$request['id'].')')->exists();
                    }
        // if there is a messages
        if ($query->count() > 0) {
            foreach ($messages as $message) {
                $allMessages .= Chatify::messageCard(
                    Chatify::fetchMessage($message->id)
                );
            }
            // send the response
            return Response::json([
                'count' => $query->count(),
                'messages' => $allMessages,
                'isBlocked' => $blocked
            ]);
        }

                    
        // send the response
        return Response::json([
            'count' => $query->count(),
            'messages' => '<p class="message-hint"><span>Say \'hi\' and start messaging</span></p>',
            'isBlocked' => $blocked
        ]);
    }

    public function seen(Request $request)
    {
        // make as seen
        $seen = Chatify::makeSeen($request['id']);
        // send the response
        return Response::json([
            'status' => $seen,
        ], 200);
    }

    public function updateContactItem(Request $request)
    {
        // Get user data
        $userCollection = User::where('user_id', $request['user_id'])->first();
        $contactItem = Chatify::getContactItem($request['messenger_id'], $userCollection);

        // send the response
        return Response::json([
            'contactItem' => $contactItem,
        ], 200);
    }

    public function setActiveStatus(Request $request)
    {
        $update = $request['status'] > 0
            ? User::where('user_id', $request['user_id'])->update(['active_status' => 1])
            : User::where('user_id', $request['user_id'])->update(['active_status' => 0]);
        // send the response
        return Response::json([
            'status' => $update,
        ], 200);
    }

    public function deleteSingleMsg(Request $request){
        $user_id=auth()->user()->user_id;
        $msg_id=$request->msg_id;
        DB::table('chats')->where('from_id',$user_id)->where('id',$msg_id)->delete();
        return Response::json([
            'status' => true,'msg' => 'Message deleted Successfully!']);
    }

}