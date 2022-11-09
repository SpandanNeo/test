<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use App\Http\Controllers\Controller;
use Hash;
use Auth;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
    public function __construct()
    {
    }

    public function index()
    {
        $candidates = DB::table('users')->where('active', '1')->count();
        $pending_candidates = DB::table('users')
            ->select(DB::raw('users.*'))
            ->where('active', '0')
            ->orderBy('user_id', 'DESC')
            ->limit(5)
            ->get();

        $active_candidates = DB::table('users')
            ->select(DB::raw('users.*'))
            ->where('active', '1')
            ->orderBy('user_id', 'DESC')
            ->limit(5)
            ->get();

        $total_active_candidates = count($active_candidates);
        $total_pending_candidates = count($pending_candidates);
        $now = date('Y-m');
        $d_arr = array();
        for ($x = 11; $x >= 0; $x--) {
            $tm = date('Y-m', strtotime($now . " -$x month"));
            $d_arr[$tm] = 0;
        }
        $register = DB::table('users')
            ->select(DB::raw('count(*) as total,DATE_FORMAT(created_at, "%Y-%m") AS Mon'))
            ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') >  $tm")
            ->groupBy(DB::raw("Mon"))
            ->orderby(DB::raw("CAST(Mon as SIGNED)"))
            ->get();

        foreach ($register as $data) {
            $d_arr[$data->Mon] = $data->total;
        }

        $total_videos = DB::table('videos')->count();
        $total_active_videos = DB::table('videos')->where('active', 1)->count();
        $total_inactive_videos = DB::table('videos')->where('active', 0)->count();
        $isColExist = Schema::hasColumn('videos', 'flag');
        if ($isColExist) {
            $total_flagged_videos = DB::table('videos')->where('flag', 1)->count();
        } else {
            $total_flagged_videos = 0;
        }

        $total_users = DB::table('users')->count();

        $total_likes = DB::table('likes')->count();
        $total_comments = DB::table('comments')->count();
        $total_views = DB::table('video_views')->count();

        $total_sounds = DB::table('sounds')->count();
        $total_active_sounds = DB::table('sounds')->where('active', 1)->count();
        $total_inactive_sounds = DB::table('sounds')->where('active', 0)->count();
        $client = new \GuzzleHttp\Client();
        $request = $client->get('http://support.unifysofttech.com/get/leuke-version');
        $response = $request->getBody();
        $latest_version = trim($response);
        
        $current_version = "v100";
        $isColExist = Schema::hasColumn('settings', 'cur_version');
        if ($isColExist) {
            $current_version = DB::table('settings')->select('cur_version')->first();
            if ($current_version) {
                $current_version = $current_version->cur_version;
                $current_version = ($current_version == "") ? "v100" : $current_version;
            }
            
        }

        return view("admin.dashboard", compact('candidates', 'total_users', 'active_candidates', 'total_active_candidates', 'pending_candidates', 'total_videos', 'total_active_videos', 'total_inactive_videos', 'total_flagged_videos', 'total_likes', 'total_comments', 'total_views', 'total_sounds', 'total_pending_candidates', 'total_active_sounds', 'd_arr', 'latest_version', 'current_version'));
    }

    private function _get_months()
    {
        $start    = new DateTime(date('Y-m-d', strtotime('-1 year')));
        $start->modify('first day of this month');
        $end      = new DateTime(date('Y-m-d'));
        $end->modify('first day of next month');
        $interval = DateInterval::createFromDateString('1 month');
        $period   = new DatePeriod($start, $interval, $end);
        $month_array = array();
        foreach ($period as $dt) {
            $month_array[$dt->format("m/Y")] = 0;
        }
        return $month_array;
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();
        $request->session()->flush();
        $request->session()->regenerate();
        return redirect(route('admin.login'));
    }

    public function changePassword()
    {
        return view("admin.change_password");
    }

    public function updatePassword(Request $request)
    {

        $rules = [
            'old_password'      => 'required|min:6',
            'new_password'      => 'required|min:6|different:old_password',
            'confirm_password'  => 'required|min:6|same:new_password'
        ];

        $messages = [
            'old_password.required'         => 'You cant leave old password field empty',
            'old_password.min'              => 'Old Password must be 6 characters long',
            'new_password.required'         => 'You cant leave new password field empty',
            'new_password.min'              => 'New password must be 6 characters long',
            'new_password.different'        => 'New password must be different from from old password',
            'confirm_password.required'     => 'You cant leave confirm password field empty',
            'confirm_password.min'          => 'Confirm password must be 6 characters long',
            'confirm_password.same'         => 'Confirm password must be same as the new password'
        ];


        $this->validate($request, $rules, $messages);
        $current_password = Auth::User()->password;
        if (Hash::check($request['old_password'], $current_password)) {
            $admin_id = Auth::User()->admin_id;
            $admin = Admin::find($admin_id);
            $admin->password = Hash::make($request['new_password']);
            $admin->save();
            return redirect(route('admin.change_password.index'))->with('success', 'Password changed successfully');
        } else {
            return redirect(route('admin.change_password.index'))->with('error', 'Old Password is not correct');
        }
    }
}
