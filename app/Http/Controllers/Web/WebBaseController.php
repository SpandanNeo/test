<?php
namespace App\Http\Controllers\Web;

use App\Events\MyEvent;
use App\Helpers\Common\Functions;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Storage;
use Auth;
use Carbon\Carbon;
use Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Exception;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class WebBaseController extends Controller
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
    public $authUser;
    public $newNotificationCount;
    public function __construct()
    {
        $this->middleware(function($req, $next) {

            if (Auth::guard('web')->check()) {
                $this->authUser = Auth::guard('web')->user();
            }

            // $commonNotifications = $this->commonNotifications();
            
            // view()->share('commonNotifications', $commonNotifications);

            $this->newNotificationCount = count($this->commonNotifications(0)); //dd($this->commonNotifications(0));
            view()->share('newNotificationCount', $this->newNotificationCount);

            return $next($req);
        });
    }

    public function commonNotifications($readType = 1)
    {
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();

        $query = DB::table('notifications as n')
        ->join('users as u', 'u.user_id', 'n.notify_by')
        ->select('n.*', 'u.user_id', 'u.username', 'u.user_dp', 'u.login_type', DB::raw("GROUP_CONCAT(n.notify_id SEPARATOR ',') as notify_ids"),
                DB::raw("DATE_FORMAT(n.added_on, '%Y-%m-%d') as date"),'uv.verified')
        // ->leftJoin('user_verify as uv','uv.user_id','u.user_id')
        ->leftJoin('user_verify as uv', function ($join){
            $join->on('uv.user_id','=','u.user_id')
            ->where('uv.verified','A');
        })
        ->selectRaw('count(n.type) as notify_total')
        ->where([['n.notify_to',  Auth::guard('web')->user()->user_id ?? 0]])   //, ['n.read', 0]])
        ->whereNotIn('n.type', ['UF', 'UL']);
        // ->whereDate('n.added_on', '>=', Carbon::now()->subDays(2)->toDateTimeString())
        // ->orWhere('n.read', 0)
        
        if (empty($readType)) {
            $query = $query->where('n.read', 0);
        }

        $commonNotifications = $query->orderBy('n.added_on', 'desc')
        ->groupBy('date', 'n.type')
        ->get();
        // ->groupBy('video_id')
        // ->reverse();
        config()->set('database.connections.mysql.strict', false);
        DB::reconnect();    //dd($commonNotifications);

        return $commonNotifications;
    }

}