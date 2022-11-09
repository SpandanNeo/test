<?php

namespace App\Http\Middleware;
use Route;
use Closure;

class AppVersionCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {      
       if( config('app.app_version') =='demo' ){
         $controller=class_basename(Route::current()->controller); 
          if($controller=='SettingController'){
               return redirect()->route('admin.app-version-warning');
            }else{
               return redirect()->route('admin.admin-app-version-warning');
            }
          //   $response = array("status" => "error", "msg"=>"This feature is not available for demo version");
          //   echo json_encode($response);
          //   die;
          exit;
       }
       else {           
            return $next($request);
       }       
    }
}