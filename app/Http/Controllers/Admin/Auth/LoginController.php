<?php
namespace App\Http\Controllers\Admin\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\Storage;
use Auth;
use Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

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
        $this->middleware('guest:admin')->except('logout');
    }

    public function showLoginForm()
    {
        return view('admin/auth/login');
    }

    public function login(Request $request)
    {
        // Validate the form data
        $rules = [
            'name'   => 'required',
            'password' => 'required|min:6'
        ];

        $messages = [
            'name.required'            => 'You cant leave User name field empty',
            'password.required'         => 'You cant leave Password field empty',
            'password.min'              => 'Password has to be 6 chars long'
        ];

        $this->validate($request,$rules,$messages);

        // Attempt to log the user in
        if (Auth::guard('admin')->attempt(['name' => $request->name, 'password' => $request->password], $request->remember)) {
			  Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            Artisan::call('storage:link');
            return redirect()->intended(route('admin.dashboard')); 
        }

        // if unsuccessful, then redirect back to the login with the form data
        return redirect()->back()->withInput($request->only('email', 'remember'))->with('error', 'Invalid login credentials');
    }   
}