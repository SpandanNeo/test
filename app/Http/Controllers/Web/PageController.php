<?php
namespace App\Http\Controllers\Web;

use App\Helpers\Common\Functions;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Auth;
use Dotenv\Result\Success;
use Route;
use Illuminate\Support\Facades\DB;
use Exception;

class PageController extends WebBaseController
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
    
    public function index($slug){
        $content = DB::table("pages")
            ->where('type',$slug)
            ->first();
        return view("web.page",compact('content'));
    }

}