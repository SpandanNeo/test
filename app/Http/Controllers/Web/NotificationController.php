<?php
namespace App\Http\Controllers\Web;

use App\Events\MyEvent;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Auth;
use Exception;
use Pusher\Pusher;

class NotificationController extends Controller
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
    }

    public function testNotification()
    {
        return view('web.testNotification');
    }

    public function saveToken(Request $request)
    {
        $user = User::find(7);
        $user->update(['device_token'=>$request->token]);
        return response()->json(['msg' => "token saved successfully"]);
    }


    public function sendNotification(Request $request)
    {

        $firebaseToken = User::whereNotNull('device_token')->pluck('device_token')->all();
        // $user = User::find(7);

        // $firebaseToken = [$user->device_token];

        $SERVER_API_KEY = config('app.server_api_key');
  

        $data = [
            "registration_ids" => $firebaseToken,
            "notification" => [
                "title" => $request->title,
                "body" => $request->body,  
            ]
        ];
        $dataString = json_encode($data);
        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);     
        $response = curl_exec($ch);
        // dd($response);
        return redirect()->back();

    }

    public function notify()
    {
        // try {
        // $options = array(
        //                 'cluster' => env('PUSHER_APP_CLUSTER'),
        //                 'encrypted' => false,
        //                     'scheme' => 'http',
        //                         );
        // $pusher = new Pusher(
        //                     env('PUSHER_APP_KEY'),
        //                     env('PUSHER_APP_SECRET'),
        //                     env('PUSHER_APP_ID'), 
        //                     $options
        //                 );
                        
        // // event(new MyEvent('abc'));

        // $message = 'hello investmentnovel';
        // $pusher->trigger('notify-channel', 'App\\Events\\MyEvent', ['abc' => 'abc']);
        //             } catch(Exception $ex) {
        //                 dd($ex->getMessage());
        //             }

        $text = ['msg' => "hello pusher notification", 'rt' => 'route'];
        event(new MyEvent($text, 6));

    }
    
}