<?php

namespace App\Http\Controllers\API;
use DateTime;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Support\Facades\Hash;
use App\Helpers\Common\Functions;

use Auth;
use Mail;
use Illuminate\Support\Facades\URL; 

class InstallerController extends Controller
{
	public function storeUrl(Request $request){
        $url = $request->fullUrl();
        $ip = \Request::ip();
        // DB::table('')->insert();
    }
}   