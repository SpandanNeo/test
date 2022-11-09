<?php

namespace App\Http\Middleware;

use Closure;

class ApiCheck
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
       if( !isset($_SERVER['HTTP_USER'])){
            $response = array("status" => "error", "msg"=>"Api User is required");
            return response()->json($response);           
            die;
       }
       else if( !isset($_SERVER['HTTP_KEY'])){
            $response = array("status" => "error", "msg"=>"Api Key is required");
            return response()->json($response);        
            die;
       }
       else if( $_SERVER['HTTP_USER'] != config('app.api_user') ||  $_SERVER['HTTP_KEY'] != config('app.api_key') ){
            $response = array("status" => "error", "msg"=>"Api User or Key is invalid");
            echo json_encode($response);
            die;
       }
       else {           
            return $next($request);
       }       
    }
}