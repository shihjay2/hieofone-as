<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use Illuminate\Support\Facades\Auth;
use Session;

class Authenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->guest()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response('Unauthorized.', 401);
            } else {
                return redirect()->guest('login');
            }
        }
        $oauth_user = DB::table('oauth_users')->where('username', '=', Session::get('username'))->first();
        $owner_query = DB::table('owner')->first();
        Session::put('full_name', $oauth_user->first_name . ' ' . $oauth_user->last_name);
        Session::put('owner', $owner_query->firstname . ' ' . $owner_query->lastname);
        Session::put('email', $oauth_user->email);
        if (Session::has('temp_image')) {
            Session::forget('temp_image');
        }
        return $next($request);
    }
}
