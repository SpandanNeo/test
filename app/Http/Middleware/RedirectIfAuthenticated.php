<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Support\Facades\Auth;
use \Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Support\Facades\Route;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */

    private $guardName;

    public function __construct()
    {
        $routeName = explode('.', Route::currentRouteName());
        $this->guardName = $routeName[0];
    }

    public function handle($request, Closure $next, $guard = null)
    {
        // if (Auth::guard($guard)->check()) {
        //     return redirect(config('app.admin_url').'/dashboard');
        // }

        // if (Auth::guard('web')->check()) {
        //     return redirect(config('app.web_url').'/home');
        // } elseif(Auth::guard('admin')->check()) {
        //     return redirect(config('app.admin_url').'/dashboard');
        // }

        if ($this->guardName == 'admin') {
            if (Auth::guard('web')->check()) {
                Auth::guard('web')->logout();
            }
            if (Auth::guard('admin')->check()) {
                return redirect(config('app.admin_url').'/dashboard');
            }
        }

        if ($this->guardName == 'web') {
            if (Auth::guard('admin')->check()) {
                Auth::guard('admin')->logout();
            }
            if (Auth::guard('web')->check()) {
                return redirect(config('app.admin_url').'/');
            }
        }

        return $next($request);
    }
}
