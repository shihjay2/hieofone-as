<?php
namespace App\Http\Controllers;

use App;
use App\Http\Controllers\Controller;
use App\User;
use ADCI\FullNameParser\Parser;
use Artisan;
use Auth;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use DB;
use File;
use Google_Client;
use GuzzleHttp;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use OAuth2\HttpFoundationBridge\Request as BridgeRequest;
use OAuth2\Response as OAuthResponse;
// use OAuth2\HttpFoundationBridge\Response as BridgeResponse;
use Response;
use Shihjay2\OpenIDConnectUMAClient;
use Schema;
use Socialite;
use Storage;
use URL;
use phpseclib\Crypt\RSA;
use Session;
use SimpleXMLElement;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class OauthController extends Controller
{
    /**
    * Base funtions
    */

    public function github_all()
    {
        $filesystemAdapter = new Local(storage_path('app/public/'));
        $filesystem = new Filesystem($filesystemAdapter);
        $pool = new FilesystemCachePool($filesystem);
        $pool->setFolder('/tmp/github-api-cache');
        $client = new \Github\Client();
        $client->addCache($pool);
        $result = $client->api('repo')->commits()->all('shihjay2', 'hieofone-as', array('sha' => 'master'));
        $client->removeCache();
        return $result;
    }

    public function github_release()
    {
        $filesystemAdapter = new Local(storage_path('app/public/'));
        $filesystem = new Filesystem($filesystemAdapter);
        $pool = new FilesystemCachePool($filesystem);
        $pool->setFolder('/tmp/github-api-cache');
        $client = new \Github\Client();
        $client->addCache($pool);
        $result = $client->api('repo')->releases()->latest('shihjay2', 'hieofone-as');
        $client->removeCache();
        return $result;
    }

    public function github_single($sha)
    {
        $filesystemAdapter = new Local(storage_path('app/public/'));
        $filesystem = new Filesystem($filesystemAdapter);
        $pool = new FilesystemCachePool($filesystem);
        $pool->setFolder('/tmp/github-api-cache');
        $client = new \Github\Client();
        $client->addCache($pool);
        $result = $client->api('repo')->commits()->show('shihjay2', 'hieofone-as', $sha);
        $client->removeCache();
        return $result;
    }

    public function welcome(Request $request)
    {
        $query = DB::table('owner')->first();
        if ($query) {
            if (Auth::check() && Session::get('is_owner') == 'yes') {
                return redirect()->route('home');
            }
            $data['name'] = $query->firstname . ' ' . $query->lastname;
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            return view('welcome', $data);
        } else {
            return redirect()->route('install');
        }
    }

    /**
    * Installation
    */

        public function install(Request $request)
    {
        // Check if already installed, if so, go back to home page
        $query = DB::table('owner')->first();
        $pnosh_exists = false;
        if (env('DOCKER') == '1') {
            $pnosh_exists = true;
        } else {
            if (File::exists('/noshdocuments/nosh2/.env')) {
                $pnosh_exists = true;
            }
        }
        // $pnosh_exists = true;
        // if ($query) {
        if (! $query) {
            $as_url = $request->root();
            $as_url = str_replace(array('http://','https://'), '', $as_url);
            $root_url = explode('/', $as_url);
            $root_url1 = explode('.', $root_url[0]);
            if (isset($root_url1[1])) {
                $root_url1 = array_slice($root_url1, -2, 2, false);
                $final_root_url = implode('.', $root_url1);
            } else {
                $final_root_url = $root_url[0];
            }
            // Tag version number for baseline prior to updating system in the future
            if (env('DOCKER') == null) {
                if (!File::exists(base_path() . "/.version")) {
                    // First time after install
                  $result = $this->github_all();
                    File::put(base_path() . "/.version", $result[0]['sha']);
                }
                $env_arr['DOCKER'] = '0';
                $this->changeEnv($env_arr);
            }
            // $update = $this->update_system('', true);
            // Is this from a submit request or not
            $dir_exists = false;
            if ($request->isMethod('post')) {
                $search_as = '';
                if (Session::has('search_as')) {
                    $search_as = Session::get('search_as');
                    $dir_exists = true;
                    Session::forget('search_as');
                }
                $val_arr = [
                    'email' => 'required',
                    // 'username' => 'required',
                    // 'password' => 'required|min:4',
                    // 'confirm_password' => 'required|min:4|same:password',
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'date_of_birth' => 'required',
                    // 'google_client_id' => 'required',
                    // 'google_client_secret' => 'required',
                    // 'smtp_username' => 'required'
                ];
                if ($pnosh_exists == true) {
                    $val_arr = [
                        'email' => 'required',
                        // 'username' => 'required',
                        // 'password' => 'required|min:4',
                        // 'confirm_password' => 'required|min:4|same:password',
                        'first_name' => 'required',
                        'last_name' => 'required',
                        'date_of_birth' => 'required',
                        'gender' => 'required',
                        'address' => 'required',
                        'city' => 'required',
                        'state' => 'required',
                        'zip' => 'required',
                        // 'google_client_id' => 'required',
                        // 'google_client_secret' => 'required',
                        // 'smtp_username' => 'required'
                    ];
                }
                $this->validate($request, $val_arr);
                if ($search_as !== '') {
                    if (in_array($request->input('username'), $search_as)) {
                        return redirect()->back()->withErrors(['username' => 'Username already exists in the Directory.  Try again'])->withInput();
                    }
                }
                // Register user
                $sub = $this->gen_uuid();
                $user_data = [
                    'username' => $sub,
                    'password' => sha1($sub),
                    // 'username' => $request->input('username'),
                    // 'password' => sha1($request->input('password')),
                    //'password' => substr_replace(Hash::make($request->input('password')),"$2a",0,3),
                    'first_name' => $request->input('first_name'),
                    'last_name' => $request->input('last_name'),
                    'sub' => $sub,
                    'email' => $request->input('email')
                ];
                DB::table('oauth_users')->insert($user_data);
                $user_data1 = [
                    // 'name' => $request->input('username'),
                    'name' => $sub,
                    'email' => $request->input('email')
                ];
                $user = DB::table('users')->insertGetId($user_data1);
                // Register owner
                $clientId = $this->gen_uuid();
                $clientSecret = $this->gen_secret();
                $owner_data = [
                    'lastname' => $request->input('last_name'),
                    'firstname' => $request->input('first_name'),
                    'DOB' => date('Y-m-d', strtotime($request->input('date_of_birth'))),
                    'email' => $request->input('email'),
                    'mobile' => $request->input('mobile'),
                    'client_id' => $clientId,
                    'sub' => $sub
                ];
                DB::table('owner')->insert($owner_data);
                // Register server as its own client
                $grant_types = 'client_credentials password authorization_code implicit jwt-bearer refresh_token';
                $scopes = 'openid profile email address phone offline_access';
                $data = [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_types' => $grant_types,
                    'scope' => $scopes,
                    'user_id' => $sub,
                    // 'user_id' => $request->input('username'),
                    'client_name' => 'HIE of One AS for ' . $request->input('first_name') . ' ' . $request->input('last_name'),
                    'client_uri' => URL::to('/'),
                    'redirect_uri' => URL::to('oauth_login'),
                    'authorized' => 1,
                    'allow_introspection' => 1
                ];
                DB::table('oauth_clients')->insert($data);
                $data1 = [
                    'type' => 'self',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret
                ];
                DB::table('oauth_rp')->insert($data1);
                // Register scopes as default
                $scopes_array = explode(' ', $scopes);
                $scopes_array[] = 'uma_protection';
                $scopes_array[] = 'uma_authorization';
                foreach ($scopes_array as $scope) {
                    $scope_data = [
                        'scope' => $scope,
                        'is_default' => 1
                    ];
                    DB::table('oauth_scopes')->insert($scope_data);
                }
                // Login
                $new_user = DB::table('oauth_users')->where('username', '=', $request->input('username'))->first();
                $this->login_sessions($new_user, $clientId);
                Auth::loginUsingId($user);
                $this->activity_log($new_user->email, 'Login');
                Session::save();
                // Setup e-mail server with Mailgun
                $mailgun_secret = '';
                if ($final_root_url == 'hieofone.org') {
                    $mailgun_url = 'https://dir.' . $final_root_url . '/mailgun';
                    $params = ['uri' => $as_url];
                    $post_body = json_encode($params);
                    $content_type = 'application/json';
                    $ch = curl_init();
                    curl_setopt($ch,CURLOPT_URL, $mailgun_url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Content-Type: {$content_type}",
                        'Content-Length: ' . strlen($post_body)
                    ]);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($ch,CURLOPT_FAILONERROR,1);
                    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch,CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
                    $mailgun_secret = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close ($ch);
                    if ($httpCode !== 404 && $httpCode !== 0) {
                        if ($mailgun_secret !== 'Not authorized.' && $mailgun_secret !== 'Try again.') {
                            $mail_arr = [
                                'MAIL_DRIVER' => 'mailgun',
                                'MAILGUN_DOMAIN' => 'mg.hieofone.org',
                                'MAILGUN_SECRET' => $mailgun_secret,
                                'MAIL_HOST' => '',
                                'MAIL_PORT' => '',
                                'MAIL_ENCRYPTION' => '',
                                'MAIL_USERNAME' => '',
                                'MAIL_PASSWORD' => '',
                                'GOOGLE_KEY' => '',
                                'GOOGLE_SECRET' => '',
                                'GOOGLE_REDIRECT_URI' => ''
                            ];
                            $this->changeEnv($mail_arr);
                        } else {

                        }
                    }
                }
                Session::put('install_picture', 'yes');
                if ($pnosh_exists == true) {
                    $params1 = [
                        'username' => 'admin',
                        'password' => $sub,
                        // 'password' => $request->input('password'),
                        'firstname' => $request->input('first_name'),
                        'lastname' => $request->input('last_name'),
                        'address' => $request->input('address'),
                        'city' => $request->input('city'),
                        'state' => $request->input('state'),
                        'zip' => $request->input('zip'),
                        'DOB' => $request->input('date_of_birth'),
                        'gender' => $request->input('gender'),
                        'pt_username' => $sub,
                        // 'pt_username' => $request->input('username'),
                        'email' => $request->input('email'),
                        'mobile' => $request->input('mobile'),
                        'mailgun_secret' => $mailgun_secret
                    ];
                    Session::put('pnosh_params', $params1);
                }
                if ($dir_exists == true) {
                    // Add to directory
                    $as_url1 = $request->root();
                    $owner = DB::table('owner')->first();
                    $rs = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
                    $rs_arr = [];
                    if ($rs->count()) {
                        foreach ($rs as $rs_row) {
                            $rs_arr[] = [
                                'name' => $rs_row->client_name,
                                'uri' => $rs_row->client_uri,
                                'public' => $rs_row->consent_public_publish_directory,
                                'private' => $rs_row->consent_private_publish_directory
                            ];
                        }
                    }
                    $params = [
                        'as_uri' => $as_url1,
                        'redirect_uri' => route('directory_add', ['approve']),
                        'name' => $new_user->username,
                        'last_update' => time(),
                        'rs' => $rs_arr,
                        'first_name' => $new_user->first_name,
                        'last_name' => $new_user->last_name,
                        'email' => $new_user->email,
                        'password' => $sub
                        // 'password' => $request->input('password')
                    ];
                    $root_domain = 'https://dir.' . $final_root_url;
                    Session::put('directory_uri', $root_domain);
                    $response = $this->directory_api($root_domain, $params);
                    if ($response['status'] == 'error') {
                        return $response['message'];
                    } else {
                        $default_policy_types = $this->default_policy_type();
                        if (isset($response['arr']['policies'])) {
                            foreach ($response['arr']['policies'] as $default_policy_type_k => $default_policy_type_v) {
                                $dir_data[$default_policy_type_k] = $default_policy_type_v;
                            }
                            DB::table('owner')->where('id', '=', $owner->id)->update($dir_data);
                        }
                        return redirect($response['arr']['uri']);
                    }
                } else {
                    return redirect()->route('install');
                }
                // Register oauth for Google and Twitter
                // $google_data = [
                //     'type' => 'google',
                //     'client_id' => $request->input('google_client_id'),
                //     'client_secret' => $request->input('google_client_secret'),
                //     'redirect_uri' => URL::to('account/google'),
                //     'smtp_username' => $request->input('smtp_username')
                // ];
                // DB::table('oauth_rp')->insert($google_data);
                // if ($request->input('twitter_client_id') !== '') {
                //     $twitter_data = [
                //         'type' => 'twitter',
                //         'client_id' => $request->input('twitter_client_id'),
                //         'client_secret' => $request->input('twitter_client_secret'),
                //         'redirect_uri' => URL::to('account/twitter')
                //     ];
                //     DB::table('oauth_rp')->insert($twitter_data);
                // }
                // Go register with Google to get refresh token for email setup
                // return redirect()->route('installgoogle');
                // Check if pNOSH associated in same domain as this authorization server and begin installation there
            } else {
                // if ($final_root_url == 'hieofone.org') {
                // if ($final_root_url == 'trustee.ai') {
                //     $final_root_url = 'hieofone.org';
                // }
                    $search_url = 'https://dir.' . $final_root_url . '/check_as';
                    $ch2 = curl_init($search_url);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    $search_arr = curl_exec($ch2);
                    $httpcode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    curl_close($ch2);
                    if($httpcode==200){
                        Session::put('search_as', json_decode($search_arr, true));
                    }
                // }
                $data2['noheader'] = true;
                $data2['email_default'] = '';
                if ($pnosh_exists == true) {
                    $data2['pnosh'] = true;
                }
                if (File::exists(base_path() . "/.email")) {
                    $data2['email_default'] = File::get(base_path(). "/.email");
                }
                return view('install', $data2);
            }
        } else {
            if (Session::has('install_picture')) {
                return redirect()->route('picture');
            }
            if (Session::has('install_redirect')) {
                Session::forget('install_redirect');
                if ($pnosh_exists == true) {
                    $url0 = URL::to('/') . '/nosh';
                    $params1 = Session::get('pnosh_params');
                    Session::forget('pnosh_params');
                    $user = DB::table('oauth_users')->where('sub', '=', $query->sub)->first();
                    if ($user) {
                        if (!empty($user->picture)) {
                            $ch3 = curl_init();
                            $pnosh_url3 = $url0 . '/pnosh_install_photo';
                            $img = file_get_contents($user->picture);
                            $type = pathinfo($user->picture, PATHINFO_EXTENSION);
                            $data_img = 'data:image/' . $type . ';base64,' . base64_encode($img);
                            $filename = str_replace(storage_path('app/public/'), '', $user->picture);
                            $params1['photo_data'] = $data_img;
                            $params1['photo_filename'] = $filename;
                        }
                    }
                    $post_body1 = json_encode($params1);
                    $content_type1 = 'application/json';
                    $ch1 = curl_init();
                    $pnosh_url = $url0 . '/pnosh_install';
                    curl_setopt($ch1, CURLOPT_URL, $pnosh_url);
                    curl_setopt($ch1, CURLOPT_POST, 1);
                    curl_setopt($ch1, CURLOPT_POSTFIELDS, $post_body1);
                    curl_setopt($ch1, CURLOPT_HTTPHEADER, [
                        "Content-Type: {$content_type1}",
                        'Content-Length: ' . strlen($post_body1)
                    ]);
                    curl_setopt($ch1, CURLOPT_HEADER, 0);
                    curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, FALSE);
                    curl_setopt($ch1, CURLOPT_FAILONERROR,1);
                    curl_setopt($ch1, CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch1, CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch1, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT ,0);
                    $pnosh_result = curl_exec($ch1);
                    curl_close ($ch1);
                    if ($pnosh_result == 'Success') {
                        // Register pNOSH resoures
                        return redirect($url0);
                    } else {
                        Session::put('message_action', $pnosh_result);
                        return redirect()->route('home');
                    }
                } else {
                    return redirect()->route('home');
                }
            }
        }
        return redirect()->route('home');
    }

    /**
    * Login and logout functions
    */

    public function login(Request $request)
    {
        if (Auth::guest()) {
            $owner_query = DB::table('owner')->first();
            $proxies = DB::table('owner')->where('sub', '!=', $owner_query->sub)->get();
            $proxy_arr = [];
            if ($proxies->count()) {
                foreach ($proxies as $proxy_row) {
                    $proxy_arr[] = $proxy_row->sub;
                }
            }
            if ($request->isMethod('post')) {
                $this->validate($request, [
                    'username' => 'required',
                    'password' => 'required'
                ]);
                // Check if there was an old request from the ouath_authorize function, else assume login is coming from server itself
                if (Session::get('oauth_response_type') == 'code') {
                    $client_id = Session::get('oauth_client_id');
                    $data['nooauth'] = true;
                } else {
                    $client = DB::table('owner')->first();
                    $client_id = $client->client_id;
                }
                // Get client secret
                $client1 = DB::table('oauth_clients')->where('client_id', '=', $client_id)->first();
                // Run authorization request
                $request->merge([
                    'client_id' => $client_id,
                    'client_secret' => $client1->client_secret,
                    'username' => $request->username,
                    'password' => $request->password,
                    'grant_type' => 'password'
                ]);
                $bridgedRequest = BridgeRequest::createFromRequest($request);
                $bridgedResponse = new OAuthResponse();
                // $bridgedResponse = new BridgeResponse();
                $bridgedResponse = App::make('oauth2')->grantAccessToken($bridgedRequest, $bridgedResponse);
                if (isset($bridgedResponse['access_token'])) {
                    // Update to include JWT for introspection in the future if needed
                    $new_token_query = DB::table('oauth_access_tokens')->where('access_token', '=', substr($bridgedResponse['access_token'], 0, 255))->first();
                    $jwt_data = [
                        'jwt' => $bridgedResponse['access_token'],
                        'expires' => $new_token_query->expires
                    ];
                    DB::table('oauth_access_tokens')->where('access_token', '=', substr($bridgedResponse['access_token'], 0, 255))->update($jwt_data);
                    // Access token granted, authorize login!
                    $oauth_user = DB::table('oauth_users')->where('username', '=', $request->username)->first();
                    Session::put('access_token',  $bridgedResponse['access_token']);
                    Session::put('client_id', $client_id);
                    Session::put('owner', $owner_query->firstname . ' ' . $owner_query->lastname);
                    Session::put('username', $request->input('username'));
                    Session::put('full_name', $oauth_user->first_name . ' ' . $oauth_user->last_name);
                    Session::put('client_name', $client1->client_name);
                    Session::put('logo_uri', $client1->logo_uri);
                    Session::put('sub', $oauth_user->sub);
                    Session::put('email', $oauth_user->email);
                    Session::put('login_origin', 'login_direct');
                    Session::put('invite', 'no');
                    Session::put('is_owner', 'no');
                    if ($oauth_user->sub == $owner_query->sub || in_array($oauth_user->sub, $proxy_arr)) {
                        Session::put('is_owner', 'yes');
                        if ($oauth_user->sub == $owner_query->sub) {
                            Session::put('password', $request->input('password'));
                        }
                    }
                    if ($owner_query->sub == $oauth_user->sub) {
                        Session::put('invite', 'yes');
                    }
                    $as_url = $request->root();
                    $as_url = str_replace(array('http://','https://'), '', $as_url);
                    $root_url = explode('/', $as_url);
                    $root_url1 = explode('.', $root_url[0]);
                    if (isset($root_url1[1])) {
                        $root_url1 = array_slice($root_url1, -2, 2, false);
                        $final_root_url = implode('.', $root_url1);
                    } else {
                        $final_root_url = $root_url[0];
                    }
                    Session::put('domain_url', $final_root_url);
                    $user1 = DB::table('users')->where('name', '=', $request->username)->first();
                    Auth::loginUsingId($user1->id);
                    $this->activity_log($user1->email, 'Login');
                    $this->notify($oauth_user);
                    Session::save();
                    if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
                        // If generated from rqp_claims endpoint, do this
                        return redirect()->route('rqp_claims');
                    } elseif (Session::get('oauth_response_type') == 'code') {
                        // Confirm if client is authorized
                        $authorized = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
                        if ($authorized) {
                            // This call is from authorization endpoint and client is authorized.  Check if user is associated with client
                            $user_array = explode(' ', $authorized->user_id);
                            if (in_array($request->username, $user_array)) {
                                // Go back to authorize route
                                Session::put('is_authorized', 'true');
                                return redirect()->route('authorize');
                            } else {
                                // Get user permission
                                return redirect()->route('login_authorize');
                            }
                        } else {
                            // Get owner permission if owner is logging in from new client/registration server
                            if ($oauth_user) {
                                if ($owner_query->sub == $oauth_user->sub) {
                                    return redirect()->route('authorize_resource_server');
                                } else {
                                    // Somehow, this is a registered user, but not the owner, and is using an unauthorized client - return back to login screen
                                    return redirect()->back()->withErrors(['tryagain' => 'Please contact the owner of this authorization server for assistance.']);
                                }
                            } else {
                                // Not a registered user
                                return redirect()->back()->withErrors(['tryagain' => 'Please contact the owner of this authorization server for assistance.']);
                            }
                        }
                    } else {
                        //  This call is directly from the home route.
                        return redirect()->intended('home');
                    }
                } else {
                    //  Incorrect login information
                    return redirect()->back()->withErrors(['tryagain' => 'Try again']);
                }
            } else {
                $query = DB::table('owner')->first();
                if ($query) {
                    // Show login form
                    $data['name'] = $query->firstname . ' ' . $query->lastname;
                    $data['noheader'] = true;
                    if (Session::get('oauth_response_type') == 'code') {
                        // Check if owner has set default policies and show other OIDC IDP's to relay information with HIE of One AS as relaying IDP
                        if ($owner_query->login_md_nosh == 0 && $owner_query->any_npi == 0 && $owner_query->login_google == 0) {
                            $data['nooauth'] = true;
                        }
                    } else {
                        Session::forget('oauth_response_type');
                        Session::forget('oauth_redirect_uri');
                        Session::forget('oauth_client_id');
                        Session::forget('oauth_nonce');
                        Session::forget('oauth_state');
                        Session::forget('oauth_scope');
                        Session::forget('is_authorized');
                    }
                    $data['google'] = 'yes';
                    // $data['google'] = DB::table('oauth_rp')->where('type', '=', 'google')->first();
                    // $data['twitter'] = DB::table('oauth_rp')->where('type', '=', 'twitter')->first();
                    if (file_exists(base_path() . '/.version')) {
                        $data['version'] = file_get_contents(base_path() . '/.version');
                    } else {
                        $version = $this->github_all();
                        $data['version'] = $version[0]['sha'];
                    }
                    $data['demo_username'] = '';
                    $data['demo_password'] = '';
                    if (route('welcome') == 'https://shihjay.xyz') {
                        $data['demo_username'] = 'Demo Username: AlicePatient';
                        $data['demo_password'] = 'demo';
                    }
                    if (route('welcome') == 'https://as1.hieofone.org') {
                        $data['demo_username'] = 'Demo Username: Alice1Patient';
                        $data['demo_password'] = 'demo';
                    }
                    return view('auth.login', $data);
                } else {
                    // Not installed yet
                    $data2 = [
                        'noheader' => true
                    ];
                    return view('install', $data2);
                }
            }
        } else {
            if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
                // If generated from rqp_claims endpoint, do this
                return redirect()->route('rqp_claims');
            }
            return redirect()->route('home');
        }
    }

    public function logout(Request $request)
    {
        Session::flush();
        Auth::logout();
        // Ensure pNOSH logs out too for safety
        $pnosh = DB::table('oauth_clients')->where('client_name', 'LIKE', "%Patient NOSH for%")->first();
        if ($pnosh) {
            $redirect_uri = $pnosh->client_uri;
            $params = [
    			'redirect_uri' => URL::to('/')
    		];
    		$redirect_uri .= '/remote_logout?' . http_build_query($params, null, '&');
            return redirect($redirect_uri);
        }
        return redirect()->route('welcome');
    }

    public function login_passwordless(Request $request)
    {
        if (Auth::guest()) {
            $owner_query = DB::table('owner')->first();
            $proxies = DB::table('owner')->where('sub', '!=', $owner_query->sub)->get();
            $proxy_arr = [];
            if ($proxies->count()) {
                foreach ($proxies as $proxy_row) {
                    $proxy_arr[] = $proxy_row->sub;
                }
            }
            if ($request->isMethod('post')) {
                $this->validate($request, [
                    'email' => 'required',
                ]);
                $user1 = DB::table('users')->where('email', '=', $request->input('email'))->first();
                if ($user1) {
                    $url = URL::temporarySignedRoute(
                        'login_passwordless', now()->addMinutes(30), [
                            'user_id' => $user1->id
                        ]
                    );
                    $data1['message_data'] = '<h3>Your Magic Link</h3><p><a href="' . $url . '" target="_blank">Click and confirm</a> that you want to login to ' . $owner_query->firstname . ' ' . $owner_query->lastname . "'s Trustee Authorization Server.";
                    $data1['message_data'] .= '  This link will expire in 30 minutes.</p><p>Or you can copy and paste this link:</p>'. $url;
                    $data1['message_data'] .= '<p>If you are having any issues with your account, please contact us at <a href="mailto:info@healthurl.com">info@healthurl.com</a></p>';
                    $title = 'Your Magic Link';
                    $to = $request->input('email');
                    $this->send_mail('auth.emails.generic', $data1, $title, $to);
                    $data['email'] = $request->input('email');
                    return view('login_passwordless', $data);
                }
            } else {
                if (! $request->hasValidSignature()) {
                    abort(403);
                }
                if (Session::get('oauth_response_type') == 'code') {
        			$client_id = Session::get('oauth_client_id');
        		} else {
        			$client = DB::table('owner')->first();
        			$client_id = $client->client_id;
        		}
        		Session::put('login_origin', 'login_direct');
        		$user = DB::table('users')->where('id', '=', $request->input('user_id'))->first();
                $oauth_user = DB::table('oauth_users')->where('email', '=', $user->email)->where('password', '!=', 'Pending')->first();
        		$this->login_sessions($oauth_user, $client_id);
        		Auth::loginUsingId($user->id);
        		$this->activity_log($oauth_user->email, 'Login - Magic Link');
        		$this->notify($oauth_user);
        		Session::save();
        		$return['message'] = 'OK';
        		if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
        			// If generated from rqp_claims endpoint, do this
                    return redirect()->route('rqp_claims');
        		} elseif (Session::get('oauth_response_type') == 'code') {
        			// Confirm if client is authorized
        			$authorized = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
        			if ($authorized) {
        				// This call is from authorization endpoint and client is authorized.  Check if user is associated with client
        				$user_array = explode(' ', $authorized->user_id);
        				if (in_array($oauth_user->username, $user_array)) {
        					// Go back to authorize route
        					Session::put('is_authorized', 'true');
                            return redirect()->route('authorize');
        				} else {
        					// Get user permission
                            return redirect()->route('login_authorize');
        				}
        			} else {
        				// Get owner permission if owner is logging in from new client/registration server
        				if ($oauth_user) {
        					if ($owner_query->sub == $oauth_user->sub) {
                                return redirect()->route('authorize_resource_server');
        					} else {
        						// Somehow, this is a registered user, but not the owner, and is using an unauthorized client - return back to login screen
                                return redirect()->route('login')->withErrors(['tryagain' => 'Please contact the owner of this authorization server for assistance.']);
        					}
        				} else {
        					// Not a registered user
                            return redirect()->route('login')->withErrors(['tryagain' => 'Not a registered user.  Please contact the owner of this authorization server for assistance.']);
        				}
        			}
        		} else {
        			//  This call is directly from the home route.
                    return redirect()->route('home');
        		}
            }
        } else {
            if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
                // If generated from rqp_claims endpoint, do this
                return redirect()->route('rqp_claims');
            }
            return redirect()->route('home');
        }
    }

    public function login_uport(Request $request)
    {
        $owner_query = DB::table('owner')->first();
        $proxies = DB::table('owner')->where('sub', '!=', $owner_query->sub)->get();
        $proxy_arr = [];
        if ($proxies->count()) {
            foreach ($proxies as $proxy_row) {
                $proxy_arr[] = $proxy_row->sub;
            }
        }
        if ($request->has('uport')) {
            $uport_notify = false;
            $valid_npi = '';
            $uport_user1 = DB::table('oauth_users')->where('uport_id', '=', $request->input('uport'))->where('password', '!=', 'Pending')->first();
            if ($uport_user1) {
                $return = $this->login_uport_common($uport_user1);
            } else {
                if ($request->has('email') && $request->input('email') !== '') {
                    // Start searching for users by checking name - will need to be more specific with e-mail address, verifiable claims, once in production etc
                    $name = $request->input('name');
                    $parser = new Parser();
                    $nameObject = $parser->parse($name);
                    if ($request->has('npi')) {
                        $uport_user = DB::table('oauth_users')
                            ->where('first_name', '=', $nameObject->getFirstName())
                            ->where('last_name', '=', $nameObject->getLastName())
                            ->where('npi', '=', $request->input('npi'))
                            ->where('email', '=', $request->input('email'))
                            ->where('password', '!=', 'Pending')->first();
                    } else {
                        $uport_user_query = DB::table('oauth_users')
                            ->where('first_name', '=', $nameObject->getFirstName())
                            ->where('last_name', '=', $nameObject->getLastName())
                            ->where('email', '=', $request->input('email'))
                            ->where('password', '!=', 'Pending');
                        $uport_user_query->where(function($query_array) {
                            $query_array->where('npi', '=', null)
                            ->orWhere('npi', '=', '');
                        });
                        $uport_user = $uport_user_query->first();
                    }
                    if ($uport_user) {
                        $uport['uport_id'] = $request->input('uport');
                        DB::table('oauth_users')->where('username', '=', $uport_user->username)->update($uport);
                        $return = $this->login_uport_common($uport_user);
                    } else {
                        // Check if NPI field exists
                        if ($request->has('npi')) {
                            if ($request->input('npi') !== '') {
                                if (is_numeric($request->input('npi'))) {
                                    $npi1 = $request->input('npi');
                                    if (strlen($npi1) == '10') {
                                        // Obtain NPI information
                                        $npi_arr = $this->npi_lookup($npi1);
                                        $name = '';
                                        if (! $npi_arr) {
                                            $return['message'] = 'NPI lookup API is temporarily not working; try again later';
                                        } else {
                                            if ($npi_arr['result_count'] > 0) {
                                                $npi_name = $npi_arr['results'][0]['basic']['first_name'];
                                                if (isset($npi_arr['results'][0]['basic']['middle_name'])) {
                                                    $npi_name .= ' ' . $npi_arr['results'][0]['basic']['middle_name'];
                                                }
                                                $npi_name .= ' ' . $npi_arr['results'][0]['basic']['last_name'] . ', ' . $npi_arr['results'][0]['basic']['credential'];
                                            }
                                            if ($npi_name !== '') {
                                                if ($owner_query->any_npi == 1) {
                                                    // Automatically add user if NPI is valid
                                                    if (Session::get('oauth_response_type') == 'code') {
                                                        $client_id = Session::get('oauth_client_id');
                                                    } else {
                                                        $client_id = $owner_query->client_id;
                                                    }
                                                    $authorized = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
                                                    if ($authorized) {
                                                        // Make sure email is unique
                                                        $email_check = DB::table('users')->where('email', '=', $request->input('email'))->first();
                                                        if ($email_check) {
                                                            $return['message'] = 'You are not authorized to access this authorization server.  Email address already exists for another user.';
                                                        } else {
                                                            // Add new user
                                                            Session::put('uport_first_name', $nameObject->getFirstName());
                                                            Session::put('uport_last_name', $nameObject->getLastName());
                                                            Session::put('uport_id', $request->input('uport'));
                                                            Session::put('uport_email', $request->input('email'));
                                                            Session::put('uport_npi', $npi1);
                                                            Session::save();
                                                            $return['message'] = 'OK';
                                                            $return['url'] = route('uport_user_add');
                                                        }
                                                    } else {
                                                        $return['message'] = 'Unauthorized client.  Please contact the owner of this authorization server for assistance.';
                                                    }
                                                } else {
                                                    $uport_notify = true;
                                                    $valid_npi = $npi1;
                                                }
                                            } else {
                                                $return['message'] = 'You are not authorized to access this authorization server.  NPI not found in database.';
                                            }
                                        }
                                    } else {
                                        if ($owner_query->login_uport == 1) {
                                            $uport_notify = true;
                                        } else {
                                            $return['message'] = 'You are not authorized to access this authorization server.  NPI not 10 characters.';
                                        }
                                    }
                                } else {
                                    if ($owner_query->login_uport == 1) {
                                        $uport_notify = true;
                                    } else {
                                        $return['message'] = 'You are not authorized to access this authorization server.  NPI not numeric.';
                                    }
                                }
                            } else {
                                if ($owner_query->login_uport == 1) {
                                    $uport_notify = true;
                                } else {
                                    $return['message'] = 'You are not authorized to access this authorization server.  NPI is blank.';
                                }
                            }
                        } else {
                            if ($owner_query->login_uport == 1) {
                                $uport_notify = true;
                            } else {
                                $return['message'] = 'You are not authorized to access this authorization server';
                            }
                        }
                    }
                } else {
                    $return['message'] = 'Login cannot be completed.  No email address is associated with your uPort account.  Add an e-mail address to your uPort and try again.';
                }
            }
            if ($uport_notify == true) {
                // Check email if duplicate
                $email_query = DB::table('users')->where('email', '=', $request->input('email'))->first();
                if ($email_query) {
                    $return['message'] = 'There is already a user that has your email address';
                } else {
                    // Email notification to owner that someone is trying to login via uPort
                    $uport_data = [
                        'username' => $request->input('uport'),
                        'first_name' => $nameObject->getFirstName(),
                        'last_name' => $nameObject->getLastName(),
                        'uport_id' => $request->input('uport'),
                        'password' => 'Pending',
                        'email' => $request->input('email'),
                        'npi' => $valid_npi
                    ];
                    DB::table('oauth_users')->insert($uport_data);
                    $uport_data1 = [
                        'name' => $request->input('uport'),
                        'email' => $request->input('email')
                    ];
                    DB::table('users')->insert($uport_data1);
                    $data1['message_data'] = $name . ' has just attempted to login using your Trustee Authorizaion Server via uPort.';
                    $data1['message_data'] .= 'Go to ' . route('authorize_user') . '/ to review and authorize.';
                    $title = 'New uPort User';
                    $to = $owner_query->email;
                    $this->send_mail('auth.emails.generic', $data1, $title, $to);
                    if ($owner_query->mobile != '') {
                        if (env('NEXMO_API') == null) {
    						$this->textbelt($owner_query->mobile, $data['message_data']);
    					} else {
    						$this->nexmo($owner_query->mobile, $data['message_data']);
    					}
                    }
                    $return['message'] = 'Authorization owner has been notified and wait for an email for your approval';
                }
            }
        } else {
            $return['message'] = 'Please contact the owner of this authorization server for assistance.';
        }
        return $return;
    }

    public function uport_user_add(Request $request)
    {
        $owner = DB::table('owner')->first();
        if (Session::get('oauth_response_type') == 'code') {
            $client_id = Session::get('oauth_client_id');
        } else {
            $client_id = $owner->client_id;
        }
        $sub = Session::get('uport_id');
        $email = Session::get('uport_email');
        $user_data = [
            'username' => $sub,
            'password' => sha1($sub),
            'first_name' => Session::get('uport_first_name'),
            'last_name' => Session::get('uport_last_name'),
            'email' => $email,
            'npi' => Session::get('uport_npi'),
            'sub' => $sub,
            'uport_id' => $sub
        ];
        Session::forget('uport_first_name');
        Session::forget('uport_last_name');
        Session::forget('uport_npi');
        Session::forget('uport_id');
        Session::forget('uport_email');
        DB::table('oauth_users')->insert($user_data);
        $user_data1 = [
            'name' => $sub,
            'email' => $email
        ];
        DB::table('users')->insert($user_data1);
        $this->add_user_policies($email, ['All']);
        $user = DB::table('oauth_users')->where('username', '=', $sub)->first();
        $local_user = DB::table('users')->where('name', '=', $sub)->first();
        $this->login_sessions($user, $client_id);
        Auth::loginUsingId($local_user->id);
        $this->activity_log($user->email, 'Login - uPort, New User');
        if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
            // If generated from rqp_claims endpoint, do this
            return redirect()->route('rqp_claims');
        } elseif (Session::get('oauth_response_type') == 'code') {
            Session::put('is_authorized', 'true');
            Session::save();
            return redirect()->route('authorize');
        } else {
            Session::save();
            return redirect()->route('home');
        }
    }

    public function remote_logout(Request $request)
    {
        Session::flush();
        Auth::logout();
        return redirect($request->input('redirect_uri'));
    }

    public function oauth_login(Request $request)
    {
        $code = $request->input('code');
        return $code;
    }

    public function password_email(Request $request)
    {
        $owner = DB::table('owner')->first();
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'email' => 'required',
            ]);
            $query = DB::table('oauth_users')->where('email', '=', $request->input('email'))->first();
            if ($query) {
                $data['password'] = $this->gen_secret();
                DB::table('oauth_users')->where('email', '=', $request->input('email'))->update($data);
                $url = URL::to('password_reset') . '/' . $data['password'];
                $data2['message_data'] = 'This message is to notify you that you have reset your password with the Trustee Authorization Server for ' . $owner->firstname . ' ' . $owner->lastname . '.<br>';
                $data2['message_data'] .= 'To finish this process, please click on the following link or point your web browser to:<br>';
                $data2['message_data'] .= $url;
                $title = 'Reset password for ' . $owner->firstname . ' ' . $owner->lastname  . "'s Authorization Server";
                $to = $request->input('email');
                $this->send_mail('auth.emails.generic', $data2, $title, $to);
            }
            return redirect()->route('welcome');
        } else {
            return view('password');
        }
    }

    public function password_reset(Request $request, $id)
    {
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'password' => 'required|min:7',
                'confirm_password' => 'required|min:7|same:password',
            ]);
            $query = DB::table('oauth_users')->where('password', '=', $id)->first();
            if ($query) {
                $data['password'] = sha1($request->input('password'));
                DB::table('oauth_users')->where('password', '=', $id)->update($data);
            }
            return redirect()->route('home');
        } else {
            $query1 = DB::table('oauth_users')->where('password', '=', $id)->first();
            if ($query1) {
                $data1['id'] = $id;
                return view('resetpassword', $data1);
            } else {
                return redirect()->route('welcome');
            }
        }
    }

    public function picture(Request $request)
    {
        ini_set('memory_limit','196M');
        ini_set('max_execution_time', '300');
        $owner = DB::table('owner')->first();
        if (Session::get('is_owner') == 'yes' || Session::has('install_picture')) {
            if ($request->isMethod('post')) {
                $postData = $request->post();
                $img = Arr::has($postData, 'img');
                if ($img) {
                    $img_data = substr($request->input('img'), strpos($request->input('img'), ',') + 1);
                    $img_data = base64_decode($img_data);
                    $filename = uniqid() . '.png';
                    Storage::disk('public')->put($filename, $img_data);
                    $data['picture'] = storage_path('app/public/') . $filename;
                } else {
                    $file = $request->file('file_input');
                    $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move(storage_path('app/public/'), $filename);
                    $data['picture'] = storage_path('app/public/') . $filename;
                }
                DB::table('oauth_users')->where('sub', '=', $owner->sub)->update($data);
                if (Session::has('install_picture')) {
                    Session::forget('install_picture');
                    Session::put('install_redirect', 'yes');
                }
                if (Session::has('my_info')) {
                    Session::forget('my_info');
                    return redirect()->route('my_info');
                } else {
                    return redirect()->route('install');
                }
            } else {
                $data['title'] = 'Your Photo';
                $data['content'] = '<div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
                $data['content'] .= '<p>It is recommended to associate your photograph to this Trustee so that other clinicians can verify that they are accessing the correct health records.</p>';
                $data['content'] .= '<p>You can choose to upload a picture or take a snapshot if you have a camera attached to your device.</p>';
                $data['content'] .= '</div>';
                $user = DB::table('oauth_users')->where('sub', '=', $owner->sub)->first();
                if ($user) {
                    if (!empty($user->picture)) {
                        $img_src = asset(str_replace(storage_path('app/public'), 'storage', $user->picture));
                        $data['content'] .= '<br><div style="margin:10px;"><b>My Current Picture:</b><br><img src="'. $img_src . '" style="height:200px;"></img></div>';
                    }
                }
                $data['content'] .= '<div class="panel panel-default"><div class="panel-heading" id="start_video" data-toggle="collapse" data-target="#snapshot1"><h4 class="panel-title">Take a Snapshot</h4></div><div id="snapshot1" class="panel-collapse collapse"><div class="panel-body">';
                $data['content'] .= '<form action="' . route('picture') . '" method="POST">' . csrf_field() . '<div style="margin:auto;" id="screenshot"><video autoplay style="display:none;width: 100% !important;height: auto !important;"></video><img src="" style="width: 100% !important;height: auto !important;"><canvas style="display:none;"></canvas><input type="hidden" name="img" id="img"></div>';
                $data['content'] .= '<div style="margin:auto;"><button type="button" id="stop_video" class="btn btn-primary" style="margin:5px;"><i class="fa fa-pause fa-fw" style="margin-right:10px"></i>Snap</button><button type="button" id="restart_picture" class="btn btn-primary" style="margin:5px;display:none;"><i class="fa fa-repeat fa-fw" style="margin-right:10px;"></i>Retake</button><button type="submit" id="save_picture" class="btn btn-success" style="margin:5px;display:none;"><i class="fa fa-camera fa-fw" style="margin-right:10px"></i>Save</button><button type="button" id="cancel_picture" class="btn btn-danger" style="margin:5px;display:none;"><i class="fa fa-times fa-fw" style="margin-right:10px"></i>Cancel</button></form></div></div></div></div>';
                $data['content'] .= '<div class="panel panel-default"><div class="panel-heading" data-toggle="collapse" data-target="#upload1"><h4 class="panel-title">or Upload a Picture</h4></div><div id="upload1" class="panel-collapse collapse"><div class="panel-body">';
                if (Session::has('install_picture')) {
                    $back_text = 'Skip, no photo!';
                    $data['back'] = '<a href="' . route('picture_cancel') . '" class="btn btn-danger" role="button"><i class="fa fa-btn fa-times"></i> No photo</a>';
                }
                if (Session::has('my_info')) {
                    $back_text = 'Back';
                }
                $data['back'] = '<a href="' . route('picture_cancel') . '" class="btn btn-danger" role="button"><i class="fa fa-btn fa-chevron-left"></i> Back</a>';
                $data['document_upload'] = route('picture');
                $type_arr = ['png', 'jpg'];
                $data['document_type'] = json_encode($type_arr);
                return view('document_upload', $data);
            }
        }
    }

    public function picture_cancel(Request $request)
    {
        if (Session::has('install_picture')) {
            Session::forget('install_picture');
            Session::put('install_redirect', 'yes');
            return redirect()->route('install');
        }
        if (Session::has('my_info')) {
            return redirect()->route('my_info');
        }
    }

    /**
    * Update system through GitHub
    */

    public function update_system($type='', $local=false)
    {
        if (env('DOCKER') == null || env('DOCKER') == '0') {
            if (env('DOCKER') == null) {
                $env_arr['DOCKER'] = '0';
                $this->changeEnv($env_arr);
            }
            ini_set('memory_limit','196M');
            ini_set('max_execution_time', '300');
            $current_version = File::get(base_path() . "/.version");
            $composer = false;
            if ($type !== '') {
                if ($type == 'composer_install' || $type == 'migrate' || $type == 'clear_cache') {
                    if ($type == 'composer_install') {
                        $install = new Process("/usr/local/bin/composer install");
                        $install->setWorkingDirectory(base_path());
                        $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                        $install->setTimeout(null);
                        $install->run();
                        $return = nl2br($install->getOutput());
                    }
                    if ($type == 'migrate') {
                        $migrate = new Process("php artisan migrate --force");
                        $migrate->setWorkingDirectory(base_path());
                        $migrate->setTimeout(null);
                        $migrate->run();
                        $return = nl2br($migrate->getOutput());
                    }
                    if ($type == 'clear_cache') {
                        $clear_cache = new Process("php artisan cache:clear");
                        $clear_cache->setWorkingDirectory(base_path());
                        $clear_cache->setTimeout(null);
                        $clear_cache->run();
                        $return = nl2br($clear_cache->getOutput());
                        $clear_view = new Process("php artisan view:clear");
                        $clear_view->setWorkingDirectory(base_path());
                        $clear_view->setTimeout(null);
                        $clear_view->run();
                        $return .= '<br>' . nl2br($clear_view->getOutput());
                    }
                } else {
                    $result1 = $this->github_single($type);
                    if (isset($result1['files'])) {
                        foreach ($result1['files'] as $row1) {
                            $filename = base_path() . "/" . $row1['filename'];
                            if ($row1['status'] == 'added' || $row1['status'] == 'modified' || $row1['status'] == 'renamed') {
                                $github_url = str_replace(' ', '%20', $row1['raw_url']);
                                if ($github_url !== '') {
                                    $file = file_get_contents($github_url);
                                    $parts = explode('/', $row1['filename']);
                                    array_pop($parts);
                                    $dir = implode('/', $parts);
                                    if (!is_dir(base_path() . "/" . $dir)) {
                                        if ($parts[0] == 'public') {
                                            mkdir(base_path() . "/" . $dir, 0777, true);
                                        } else {
                                            mkdir(base_path() . "/" . $dir, 0755, true);
                                        }
                                    }
                                    file_put_contents($filename, $file);
                                    if ($row1['filename'] == 'composer.json' || $row1['filename'] == 'composer.lock') {
                                        $composer = true;
                                    }
                                }
                            }
                            if ($row1['status'] == 'removed') {
                                if (file_exists($filename)) {
                                    unlink($filename);
                                }
                            }
                        }
                        define('STDIN',fopen("php://stdin","r"));
                        File::put(base_path() . "/.version", $type);
                        $return = "System Updated with version " . $type . " from " . $current_version;
                        $migrate = new Process("php artisan migrate --force");
                        $migrate->setWorkingDirectory(base_path());
                        $migrate->setTimeout(null);
                        $migrate->run();
                        $return .= '<br>' . nl2br($migrate->getOutput());
                        if ($composer == true) {
                            $install = new Process("/usr/local/bin/composer install");
                            $install->setWorkingDirectory(base_path());
                            $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                            $install->setTimeout(null);
                            $install->run();
                            $return .= '<br>' .nl2br($install->getOutput());
                        }
                    } else {
                        $return = "Wrong version number";
                    }
                }
            } else {
                $result = $this->github_all();
                if ($current_version != $result[0]['sha']) {
                    $arr = [];
                    foreach ($result as $row) {
                        $arr[] = $row['sha'];
                        if ($current_version == $row['sha']) {
                            break;
                        }
                    }
                    $arr2 = array_reverse($arr);
                    foreach ($arr2 as $sha) {
                        $result1 = $this->github_single($sha);
                        if (isset($result1['files'])) {
                            foreach ($result1['files'] as $row1) {
                                $filename = base_path() . "/" . $row1['filename'];
                                if ($row1['status'] == 'added' || $row1['status'] == 'modified' || $row1['status'] == 'renamed') {
                                    $github_url = str_replace(' ', '%20', $row1['raw_url']);
                                    if ($github_url !== '') {
                                        $file = file_get_contents($github_url);
                                        $parts = explode('/', $row1['filename']);
                                        array_pop($parts);
                                        $dir = implode('/', $parts);
                                        if (!is_dir(base_path() . "/" . $dir)) {
                                            if ($parts[0] == 'public') {
                                                mkdir(base_path() . "/" . $dir, 0777, true);
                                            } else {
                                                mkdir(base_path() . "/" . $dir, 0755, true);
                                            }
                                        }
                                        file_put_contents($filename, $file);
                                        if ($row1['filename'] == 'composer.json' || $row1['filename'] == 'composer.lock') {
                                            $composer = true;
                                        }
                                    }
                                }
                                if ($row1['status'] == 'removed') {
                                    if (file_exists($filename)) {
                                        unlink($filename);
                                    }
                                }
                            }
                        }
                    }
                    define('STDIN',fopen("php://stdin","r"));
                    File::put(base_path() . "/.version", $result[0]['sha']);
                    $return = "System Updated with version " . $result[0]['sha'] . " from " . $current_version;
                    $migrate = new Process("php artisan migrate --force");
                    $migrate->setWorkingDirectory(base_path());
                    $migrate->setTimeout(null);
                    $migrate->run();
                    $return .= '<br>' . nl2br($migrate->getOutput());
                    if ($composer == true) {
                        $install = new Process("/usr/local/bin/composer install");
                        $install->setWorkingDirectory(base_path());
                        $install->setEnv(['COMPOSER_HOME' => '/usr/local/bin/composer']);
                        $install->setTimeout(null);
                        $install->run();
                        $return .= '<br>' .nl2br($install->getOutput());
                    }
                    $clear_cache = new Process("php artisan cache:clear");
                    $clear_cache->setWorkingDirectory(base_path());
                    $clear_cache->setTimeout(null);
                    $clear_cache->run();
                    $return .= '<br>' . nl2br($clear_cache->getOutput());
                    $clear_view = new Process("php artisan view:clear");
                    $clear_view->setWorkingDirectory(base_path());
                    $clear_view->setTimeout(null);
                    $clear_view->run();
                    $return .= '<br>' . nl2br($clear_view->getOutput());
                } else {
                    $return = "No update needed";
                }
            }
        } else {
            $return = "Update function disabled";
        }
        if ($local == false) {
            Session::put('message_action', $return);
            if (Auth::guest()) {
                return redirect()->route('welcome');
            } else {
                return back();
            }
        } else {
            return $return;
        }
    }

    /**
    * Client registration page if they are given a QR code by the owner of this authorization server
    */
    public function client_register(Request $request)
    {
        if ($request->isMethod('post')) {
        } else {
        }
    }

    /**
    * Social authentication as Open ID Connect relying party
    *
    * @return RQP claims route when authentication is successful
    * $user->token;
    * $user->getId();
    * $user->getNickname();
    * $user->getName();
    * $user->getEmail();
    * $user->getAvatar();
    *
    */

    public function installgoogle(Request $request)
    {
        $query0 = DB::table('oauth_rp')->where('type', '=', 'google')->first();
        $url = URL::to('installgoogle');
        $google = new Google_Client();
        $google->setRedirectUri($url);
        $google->setApplicationName('HIE of One');
        $google->setClientID($query0->client_id);
        $google->setClientSecret($query0->client_secret);
        $google->setAccessType('offline');
        $google->setApprovalPrompt('force');
        $google->setScopes(array('https://mail.google.com/'));
        if (isset($_REQUEST["code"])) {
            $credentials = $google->authenticate($_GET['code']);
            $data['refresh_token'] = $credentials['refresh_token'];
            DB::table('oauth_rp')->where('type', '=', 'google')->update($data);
            return redirect()->route('setup_mail_test');
        } else {
            $authUrl = $google->createAuthUrl();
            header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
            exit;
        }
    }

    public function google_redirect()
    {
        $query0 = DB::table('oauth_rp')->where('type', '=', 'google')->first();
        config(['services.google.client_id' => $query0->client_id]);
        config(['services.google.client_secret' => $query0->client_secret]);
        config(['services.google.redirect' => $query0->redirect_uri]);
        return Socialite::driver('google')->redirect();
    }

    public function google_old(Request $request)
    {
        $query0 = DB::table('oauth_rp')->where('type', '=', 'google')->first();
        $owner_query = DB::table('owner')->first();
        $proxies = DB::table('owner')->where('sub', '!=', $owner->sub)->get();
        $proxy_arr = [];
        if ($proxies->count()) {
            foreach ($proxies as $proxy_row) {
                $proxy_arr[] = $proxy_row->sub;
            }
        }
        config(['services.google.client_id' => $query0->client_id]);
        config(['services.google.client_secret' => $query0->client_secret]);
        config(['services.google.redirect' => $query0->redirect_uri]);
        $user = Socialite::driver('google')->user();
        $google_user = DB::table('oauth_users')->where('email', '=', $user->getEmail())->first();
        // Get client if from OIDC call
        if (Session::get('oauth_response_type') == 'code') {
            $client_id = Session::get('oauth_client_id');
        } else {
            $client = DB::table('owner')->first();
            $client_id = $client->client_id;
        }
        $authorized = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
        if ($google_user) {
            // Google email matches
            Session::put('login_origin', 'login_google');
            $local_user = DB::table('users')->where('email', '=', $google_user->email)->first();
            if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
                // If generated from rqp_claims endpoint, do this
                return redirect()->route('rqp_claims');
            } elseif (Session::get('oauth_response_type') == 'code') {
                if ($authorized) {
                    Session::put('is_authorized', 'true');
                    $this->login_sessions($google_user, $client_id);
                    Auth::loginUsingId($local_user->id);
                    $this->activity_log($local_user->email, 'Login - oAuth2, Google');
                    Session::save();
                    return redirect()->route('authorize');
                } else {
                    // Get owner permission if owner is logging in from new client/registration server
                    if ($owner_query->sub == $google_user->sub) {
                        $this->login_sessions($google_user, $client_id);
                        Auth::loginUsingId($local_user->id);
                        $this->activity_log($local_user->email, 'Login - oAuth2, Google');
                        Session::save();
                        return redirect()->route('authorize_resource_server');
                    } else {
                        return redirect()->route('login')->withErrors(['tryagain' => 'Unauthorized client.  Please contact the owner of this authorization server for assistance.']);
                    }
                }
            } else {
                $this->login_sessions($google_user, $client_id);
                Auth::loginUsingId($local_user->id);
                $this->activity_log($local_user->email, 'Login - oAuth2, Google');
                Session::save();
                return redirect()->route('home');
            }
        } else {
            if ($owner_query->any_npi == 1 || $owner_query->login_google == 1) {
                if ($authorized) {
                    // Add new user
                    Session::put('google_sub' ,$user->getId());
                    Session::put('google_name', $user->getName());
                    Session::put('google_email', $user->getEmail());
                    return redirect()->route('google_md1');
                    // return redirect()->route('google_md');
                } else {
                    return redirect()->route('login')->withErrors(['tryagain' => 'Unauthorized client.  Please contact the owner of this authorization server for assistance.']);
                }
            } else {
                return redirect()->route('login')->withErrors(['tryagain' => 'Not a registered user.  Any NPI or Any Google not set.  Please contact the owner of this authorization server for assistance.']);
            }
        }
    }

    public function google(Request $request)
    {
        if (! Session::has('oidc_relay')) {
            $param = [
                'origin_uri' => route('google'),
                'response_uri' => route('google'),
                'fhir_url' => '',
                'fhir_auth_url' => '',
                'fhir_token_url' => '',
                'type' => 'google',
                'cms_pid' => '',
                'refresh_token' => ''
            ];
            // $param = [
            //     'origin_uri' => route('cms_bluebutton'),
            //     'response_uri' => route('cms_bluebutton'),
            //     'fhir_url' => '',
            //     'fhir_auth_url' => '',
            //     'fhir_token_url' => '',
            //     'type' => 'cms_bluebutton',
            //     'cms_pid' => '',
            //     'refresh_token' => ''
            // ];
            $oidc_response = $this->oidc_relay($param);
            if ($oidc_response['message'] == 'OK') {
                Session::put('oidc_relay', $oidc_response['state']);
                return redirect($oidc_response['url']);
            } else {
                Session::put('message_action', $oidc_response['message']);
                return redirect(Session::get('last_page'));
            }
        } else {
            $owner_query = DB::table('owner')->first();
            $proxies = DB::table('owner')->where('sub', '!=', $owner_query->sub)->get();
            $proxy_arr = [];
            if ($proxies->count()) {
                foreach ($proxies as $proxy_row) {
                    $proxy_arr[] = $proxy_row->sub;
                }
            }
            $param1['state'] = Session::get('oidc_relay');
            Session::forget('oidc_relay');
            $oidc_response1 = $this->oidc_relay($param1, true);
            if ($oidc_response1['message'] == 'Tokens received') {
                if ($oidc_response1['tokens']['access_token'] == '') {
                    return redirect()->route('google');
                }
                $access_token = $oidc_response1['tokens']['access_token'];
                $user = Socialite::driver('google')->userFromToken($access_token);
                $google_user = DB::table('oauth_users')->where('email', '=', $user->getEmail())->first();
                // Get client if from OIDC call
                if (Session::get('oauth_response_type') == 'code') {
                    $client_id = Session::get('oauth_client_id');
                } else {
                    $client = DB::table('owner')->first();
                    $client_id = $client->client_id;
                }
                $authorized = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
                if ($google_user) {
                    // Google email matches
                    Session::put('login_origin', 'login_google');
                    $local_user = DB::table('users')->where('email', '=', $google_user->email)->first();
                    if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
                        // If generated from rqp_claims endpoint, do this
                        return redirect()->route('rqp_claims');
                    } elseif (Session::get('oauth_response_type') == 'code') {
                        if ($authorized) {
                            Session::put('is_authorized', 'true');
                            $this->login_sessions($google_user, $client_id);
                            Auth::loginUsingId($local_user->id);
                            $this->activity_log($local_user->email, 'Login - oAuth2, Google');
                            Session::save();
                            return redirect()->route('authorize');
                        } else {
                            // Get owner permission if owner is logging in from new client/registration server
                            if ($owner_query->sub == $google_user->sub) {
                                $this->login_sessions($google_user, $client_id);
                                Auth::loginUsingId($local_user->id);
                                $this->activity_log($local_user->email, 'Login - oAuth2, Google');
                                Session::save();
                                return redirect()->route('authorize_resource_server');
                            } else {
                                return redirect()->route('login')->withErrors(['tryagain' => 'Unauthorized client.  Please contact the owner of this authorization server for assistance.']);
                            }
                        }
                    } else {
                        $this->login_sessions($google_user, $client_id);
                        Auth::loginUsingId($local_user->id);
                        $this->activity_log($local_user->email, 'Login - oAuth2, Google');
                        Session::save();
                        return redirect()->route('home');
                    }
                } else {
                    if ($owner_query->any_npi == 1 || $owner_query->login_google == 1) {
                        if ($authorized) {
                            // Add new user
                            Session::put('google_sub' ,$user->getId());
                            Session::put('google_name', $user->getName());
                            Session::put('google_email', $user->getEmail());
                            return redirect()->route('google_md1');
                            // return redirect()->route('google_md');
                        } else {
                            return redirect()->route('login')->withErrors(['tryagain' => 'Unauthorized client.  Please contact the owner of this authorization server for assistance.']);
                        }
                    } else {
                        return redirect()->route('login')->withErrors(['tryagain' => 'Not a registered user.  Any NPI or Any Google not set.  Please contact the owner of this authorization server for assistance.']);
                    }
                }
            } else {
                Session::put('message_action', $oidc_response1['message']);
                return redirect()->route('login');
            }
        }
    }

    public function google_md1(Request $request)
    {
        $owner = DB::table('owner')->first();
        $name = Session::get('google_name');
        $name_arr = explode(' ', $name);
        if (Session::get('oauth_response_type') == 'code') {
            $client_id = Session::get('oauth_client_id');
        } else {
            $client_id = $owner->client_id;
        }
        $sub = Session::get('google_sub');
        $email = Session::get('google_email');
        Session::forget('google_sub');
        Session::forget('google_name');
        Session::forget('google_email');
        $npi = '1234567890';
        $user_data = [
            'username' => $sub,
            'password' => sha1($sub),
            'first_name' => $name_arr[0],
            'last_name' => $name_arr[1],
            'sub' => $sub,
            'email' => $email,
            'npi' => $npi
        ];
        DB::table('oauth_users')->insert($user_data);
        $user_data1 = [
            'name' => $sub,
            'email' => $email
        ];
        DB::table('users')->insert($user_data1);
        $user = DB::table('oauth_users')->where('username', '=', $sub)->first();
        $local_user = DB::table('users')->where('name', '=', $sub)->first();
        $this->login_sessions($user, $client_id);
        Auth::loginUsingId($local_user->id);
        if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
            // If generated from rqp_claims endpoint, do this
            return redirect()->route('rqp_claims');
        } elseif (Session::get('oauth_response_type') == 'code') {
            Session::put('is_authorized', 'true');
            Session::save();
            return redirect()->route('authorize');
        } else {
            Session::save();
            return redirect()->route('home');
        }
    }

    public function google_md(Request $request, $npi='')
    {
        $owner = DB::table('owner')->first();
        $name = Session::get('google_name');
        $name_arr = explode(' ', $name);
        if ($request->isMethod('post') || $npi !== '') {
            if (Session::get('oauth_response_type') == 'code') {
                $client_id = Session::get('oauth_client_id');
            } else {
                $client_id = $owner->client_id;
            }
            $sub = Session::get('google_sub');
            $email = Session::get('google_email');
            Session::forget('google_sub');
            Session::forget('google_name');
            Session::forget('google_email');
            if ($npi == '') {
                $npi = $request->input('npi');
            }
            $user_data = [
                'username' => $sub,
                'password' => sha1($sub),
                'first_name' => $name_arr[0],
                'last_name' => $name_arr[1],
                'sub' => $sub,
                'email' => $email,
                'npi' => $npi
            ];
            DB::table('oauth_users')->insert($user_data);
            $user_data1 = [
                'name' => $sub,
                'email' => $email
            ];
            DB::table('users')->insert($user_data1);
            $user = DB::table('oauth_users')->where('username', '=', $sub)->first();
            $local_user = DB::table('users')->where('name', '=', $sub)->first();
            $this->login_sessions($user, $client_id);
            Auth::loginUsingId($local_user->id);
            if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
                // If generated from rqp_claims endpoint, do this
                return redirect()->route('rqp_claims');
            } elseif (Session::get('oauth_response_type') == 'code') {
                Session::put('is_authorized', 'true');
                Session::save();
                return redirect()->route('authorize');
            } else {
                Session::save();
                return redirect()->route('home');
            }
        } else {
            $data['noheader'] = true;
            $data['owner'] = $owner->firstname . ' ' . $owner->lastname . "'s Authorization Server";
            $npi_arr = $this->npi_lookup($name_arr[0], $name_arr[1]);
            $data['npi'] = '<div class="list-group">';
            if ($npi_arr['result_count'] > 0) {
                foreach ($npi_arr['results'] as $npi) {
                    $label = '<strong>Name:</strong> ' . $npi['basic']['first_name'];
                    if (isset($npi['basic']['middle_name'])) {
                        $label .= ' ' . $npi['basic']['middle_name'];
                    }
                    $label .= ' ' . $npi['basic']['last_name'] . ', ' . $npi['basic']['credential'];
                    $label .= '<br><strong>NPI:</strong> ' . $npi['number'];
                    $label .= '<br><strong>Specialty:</strong> ' . $npi['taxonomies'][0]['desc'];
                    $label .= '<br><strong>Location:</strong> ' . $npi['addresses'][0]['city'] . ', ' . $npi['addresses'][0]['state'];
                    $data['npi'] .= '<a class="list-group-item" href="' . route('google_md', [$npi['number']]) . '">' . $label . '</a>';
                }
            }
            $data['npi'] .= '</div>';
            return view('google_md', $data);
        }
    }

    /**
    * Authorization endpoint
    *
    * @return Response
    */

    public function oauth_authorize(Request $request)
    {
        if (Auth::check()) {
            // Logged in, check if there was old request info and if so, plug into request since likely request is empty on the return.
            if (Session::has('oauth_response_type')) {
                $request->merge([
                    'response_type' => Session::get('oauth_response_type'),
                    'redirect_uri' => Session::get('oauth_redirect_uri'),
                    'client_id' => Session::get('oauth_client_id'),
                    'nonce' => Session::get('oauth_nonce'),
                    'state' => Session::get('oauth_state'),
                    'scope' => Session::get('oauth_scope')
                ]);
                if (Session::get('is_authorized') == 'true') {
                    $authorized = true;
                } else {
                    $authorized = false;
                }
                Session::forget('oauth_response_type');
                Session::forget('oauth_redirect_uri');
                Session::forget('oauth_client_id');
                Session::forget('oauth_nonce');
                Session::forget('oauth_state');
                Session::forget('oauth_scope');
                Session::forget('is_authorized');
            } else {
                $owner_query = DB::table('owner')->first();
                $oauth_user = DB::table('oauth_users')->where('username', '=', Session::get('username'))->first();
                $authorized_query = DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->where('authorized', '=', 1)->first();
                if ($authorized_query) {
                    // This call is from authorization endpoint and client is authorized.  Check if user is associated with client
                    $user_array = explode(' ', $authorized_query->user_id);
                    if (in_array(Session::get('username'), $user_array)) {
                        $authorized = true;
                    } else {
                        Session::put('oauth_response_type', $request->input('response_type'));
                        Session::put('oauth_redirect_uri', $request->input('redirect_uri'));
                        Session::put('oauth_client_id', $request->input('client_id'));
                        Session::put('oauth_nonce', $request->input('nonce'));
                        Session::put('oauth_state', $request->input('state'));
                        Session::put('oauth_scope', $request->input('scope'));
                        // Get user permission
                        return redirect()->route('login_authorize');
                    }
                } else {
                    if ($owner_query->sub == $oauth_user->sub) {
                        Session::put('oauth_response_type', $request->input('response_type'));
                        Session::put('oauth_redirect_uri', $request->input('redirect_uri'));
                        Session::put('oauth_client_id', $request->input('client_id'));
                        Session::put('oauth_nonce', $request->input('nonce'));
                        Session::put('oauth_state', $request->input('state'));
                        Session::put('oauth_scope', $request->input('scope'));
                        $scopes = $request->input('scope');
                        $scopes_array = explode(' ', $scopes);
                        // check if this client is a resource server
                        if (in_array('uma_protection', $scopes_array)) {
                            return redirect()->route('authorize_resource_server');
                        } else {
                            return redirect()->route('login_authorize');
                        }
                    } else {
                        // Somehow, this is a registered user, but not the owner, and is using an unauthorized client - logout and return back to login screen
                        Session::flush();
                        Auth::logout();
                        return redirect()->route('login')->withErrors(['tryagain' => 'Please contact the owner of this authorization server for assistance.']);
                    }
                }
            }
            $bridgedRequest = BridgeRequest::createFromRequest($request);
            $bridgedResponse = new OAuthResponse();
            // $bridgedResponse = new BridgeResponse();
            $bridgedResponse = App::make('oauth2')->handleAuthorizeRequest($bridgedRequest, $bridgedResponse, $authorized, Session::get('sub'));
            return $this->convertOAuthResponseToSymfonyResponse($bridgedResponse);
            // return $bridgedResponse;
        } else {
            // Do client check
            $query = DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->first();
            if ($query) {
                // Validate request
                $bridgedRequest = BridgeRequest::createFromRequest($request);
                $bridgedResponse = new OAuthResponse();
                // $bridgedResponse = new BridgeResponse();
                $bridgedResponse = App::make('oauth2')->validateAuthorizeRequest($bridgedRequest, $bridgedResponse);
                if ($bridgedResponse == true) {
                    // Save request input to session prior to going to login route
                    Session::put('oauth_response_type', $request->input('response_type'));
                    Session::put('oauth_redirect_uri', $request->input('redirect_uri'));
                    Session::put('oauth_client_id', $request->input('client_id'));
                    Session::put('oauth_nonce', $request->input('nonce'));
                    Session::put('oauth_state', $request->input('state'));
                    Session::put('oauth_scope', $request->input('scope'));
                    return redirect()->route('login');
                } else {
                    return response('invalid_request', 400);
                }
            } else {
                return response('unauthorized_client', 400);
            }
        }
    }

    public function token(Request $request)
    {
        // $bridgedRequest = OAuth2\HttpFoundationBridge\Request::createFromRequest(Request::instance());
        // $bridgedResponse = new App\Libraries\BridgedResponse();
        // $bridgedResponse = new OAuth2\Response();
        // $bridgedResponse = App::make('oauth2')->handleTokenRequest($bridgedRequest, $bridgedResponse);
        // return $bridgedResponse;
        $bridgedRequest = BridgeRequest::createFromRequest($request);
        $bridgedResponse = new OAuthResponse();
        $bridgedResponse = App::make('oauth2')->handleTokenRequest($bridgedRequest, $bridgedResponse);
        return $this->convertOAuthResponseToSymfonyResponse($bridgedResponse);
    }
    /**
    * Userinfo endpoint
    *
    * @return Response
    */

    public function userinfo(Request $request)
    {
        $bridgedRequest = BridgeRequest::createFromRequest($request);
        $bridgedResponse = new OAuthResponse();
        // $bridgedResponse = new BridgeResponse();
        // Fix for Laravel
        $bridgedRequest->request = new \Symfony\Component\HttpFoundation\ParameterBag();
        $rawHeaders = getallheaders();
        if (isset($rawHeaders["Authorization"])) {
            $authorizationHeader = $rawHeaders["Authorization"];
            $bridgedRequest->headers->add([ 'Authorization' => $authorizationHeader]);
        }
        // $bridgedResponse = App::make('oauth2')->handleUserInfoRequest($bridgedRequest, $bridgedResponse);
        // return $bridgedResponse;
        if (App::make('oauth2')->verifyResourceRequest($bridgedRequest, $bridgedResponse)) {
            $token = App::make('oauth2')->getAccessTokenData($bridgedRequest);
            // Grab user details
            $query = DB::table('oauth_users')->where('sub', '=', $token['user_id'])->first();
            $owner_query = DB::table('owner')->first();
            if ($owner_query->sub == $token['user_id']) {
                $birthday = str_replace(' 00:00:00', '', $owner_query->DOB);
            } else {
                $birthday = '';
            }
            return Response::json(array(
                'sub' => $token['user_id'],
                'name' => $query->first_name . ' ' . $query->last_name,
                'given_name' => $query->first_name,
                'family_name' => $query->last_name,
                'email' => $query->email,
                'picture' => $query->picture,
                'birthday' => $birthday,
                'npi' => $query->npi,
                'uport_id' => $query->uport_id,
                'client'  => $token['client_id'],
                'expires' => $token['expires']
            ));
        } else {
            return Response::json(array('error' => 'Unauthorized'), $bridgedResponse->getStatusCode());
        }
    }

    /**
    * JSON Web Token signing keys
    *
    * @return Response
    */

    public function jwks_uri(Request $request)
    {
        $rsa = new RSA();
        if (env('DOCKER') == '1') {
            $publicKey = env('PUBKEY');
        } else {
            $publicKey  = File::get(base_path() . "/.pubkey.pem");
        }
        $rsa->loadKey($publicKey);
        $parts = $rsa->getPublicKey(RSA::PUBLIC_FORMAT_XML);
        $values = new SimpleXMLElement($parts);
        $n = (string) $values->Modulus;
        $e = (string) $values->Exponent;
        $keys[] = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => $n,
            'e' => $e
        ];
        $return = [
            'keys' => $keys
        ];
        return $return;
    }

    /**
    * Introspection endpoint
    *
    * @return Response
    */

    public function introspect(Request $request)
    {
        $token = $request->input('token');
        $return['active'] = false;
        $query = DB::table('oauth_access_tokens')->where('jwt', '=', $token)->first();
        if ($query) {
            $expires = strtotime($query->expires);
            if ($expires > time()) {
                $permission = DB::table('permission')->where('permission_id', '=', $query->permission_id)->first();
                $resource_scopes = [];
                $permission_scopes = DB::table('permission_scopes')->where('permission_id', '=', $query->permission_id)->get();
                foreach ($permission_scopes as $permission_scope) {
                    $resource_scopes[] = $permission_scope->scope;
                }
                $permissions_arr = [];
                $return['active'] = true;
                $return['token_type'] = 'access_token';
                $return['exp'] = $query->expires;
                $return['iss'] = URL::to('/');
                $return['permissions'][] = [
                    'resource_id' => $permission->resource_set_id,
                    'resource_scopes' => $resource_scopes,
                    'exp' => $query->expires
                ];
            }
        }
        return $return;
    }

    /**
    * Revocation endpoint
    *
    * @return Response
    */

    public function revoke(Request $request)
    {
        $bridgedRequest = BridgeRequest::createFromRequest($request);
        $bridgedResponse = new OAuthResponse();
        // $bridgedResponse = new BridgeResponse();
        // Fix for Laravel
        $bridgedRequest->request = new \Symfony\Component\HttpFoundation\ParameterBag();
        $rawHeaders = getallheaders();
        if (isset($rawHeaders["Authorization"])) {
            $authorizationHeader = $rawHeaders["Authorization"];
            $bridgedRequest->headers->add([ 'Authorization' => $authorizationHeader]);
        }
        $bridgedResponse = App::make('oauth2')->handleRevokeRequest($bridgedRequest, $bridgedResponse);
        return $this->convertOAuthResponseToSymfonyResponse($bridgedResponse);
        // return $bridgedResponse;
    }

    /**=
    * Webfinger
    *
    * @return Response
    *
    */
    public function webfinger(Request $request)
    {
        $resource = str_replace('acct:', '', $request->input('resource'));
        $rel = $request->input('rel');
        $query = DB::table('oauth_users')->where('email', '=', $resource)->first();
        if ($query) {
            $response = [
                'subject' => $request->input('resource'),
                'links' => [
                    ['rel' => $rel, 'href' => URL::to('/')]
                ]
            ];
            return $response;
        } else {
            abort(404);
        }
    }

    public function accept_invitation(Request $request, $id)
    {
        $query = DB::table('invitation')->where('code', '=', $id)->first();
        if ($query) {
            $expires = strtotime($query->expires);
            if ($expires > time()) {
                if ($request->isMethod('post')) {
                    $this->validate($request, [
                        'username' => 'unique:oauth_users,username',
                        'password' => 'min:7',
                        'confirm_password' => 'min:7|same:password'
                    ]);
                    if ($request->input('username') == '') {
                        $username = $this->gen_uuid();
                        $password = sha1($username);
                    } else {
                        $username = $request->input('username');
                        $password = sha1($request->input('password'));
                    }
                    // Add user
                    $sub = $this->gen_uuid();
                    $data = [
                        'username' => $username,
                        'first_name' => $query->first_name,
                        'last_name' => $query->last_name,
                        'password' => $password,
                        'email' => $query->email,
                        'sub' => $sub,
                        'role' => $query->role
                    ];
                    DB::table('oauth_users')->insert($data);
                    $data1 = [
                        'email' => $query->email,
                        'name' => $username
                    ];
                    DB::table('users')->insert($data1);
                    $policies = ['All'];
                    if ($query->policies !== null) {
                        $policies = explode(',', $query->policies);
                    }
                    $this->add_user_policies($query->email, $policies);
                    DB::table('invitation')->where('code', '=', $id)->delete();
                    // Find pNOSH and go there
                    $pnosh = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->where('client_name', 'LIKE', "%Patient NOSH%")->first();
                    if ($pnosh) {
                        return redirect($pnosh->client_uri);
                    }
                    return redirect()->route('home');
                } else {
                    $data['noheader'] = true;
                    $owner = DB::table('owner')->first();
                    $data['code'] = $id;
                    $data['owner'] = $owner->firstname . ' ' . $owner->lastname . "'s Authorization Server";
                    return view('accept_invite', $data);
                }
            } else {
                $error = 'Your invitation code expired.';
                return $error;
            }
        } else {
            $error = 'Your invitation code is invalid';
            return $error;
        }
    }

    public function as_push_notification(Request $request)
    {
        $owners_query = DB::table('owner')->get();
        $owner_query = DB::table('owner')->first();
        $client = DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->where('client_secret', '=', $request->input('client_secret'))->first();
        if ($client) {
            $actions = json_decode(json_encode($request->input('action')), true);
            foreach ($actions as $action_k => $action_v) {
                if ($action_k == 'notification') {
                    $data['message_data'] = $action_v;
                    $title = 'Directory Notification';
                    foreach ($owners_query as $owner) {
                        $to = $owner->email;
                        $this->send_mail('auth.emails.generic', $data, $title, $to);
                        if ($owner->mobile != '') {
                            if (env('NEXMO_API') == null) {
        						$this->textbelt($owner_query->mobile, $data['message_data']);
        					} else {
        						$this->nexmo($owner_query->mobile, $data['message_data']);
        					}
                        }
                    }
                }
                if ($action_k == 'add_clinician') {
                    $email_check = DB::table('users')->where('email', '=', $action_v['email'])->first();
                    $user_data = [
                        'username' => $action_v['uport_id'],
                        'first_name' => $action_v['first_name'],
                        'last_name' => $action_v['last_name'],
                        'uport_id' => $action_v['uport_id'],
                        'password' => 'Pending',
                        'npi' => $action_v['npi'],
                    ];
                    $user_data1 = [
                        'name' => $action_v['uport_id'],
                        'email' => $action_v['email']
                    ];
                    if (! $email_check) {
                        if ($owner_query->any_npi == 0) {
                            DB::table('oauth_users')->insert($user_data);
                            DB::table('users')->insert($user_data1);
                            $data1['message_data'] = $name . ' has just subscribed to your Trustee Authorizaion Server via the ' . $client->client_name . ' Directory.<br>';
                            $data1['message_data'] .= 'Go to ' . route('authorize_user') . '/ to review and authorize.';
                            $title1 = 'New User from the ' . $client->client_name . ' Directory';
                            $to1 = $owner_query->email;
                            $this->send_mail('auth.emails.generic', $data1, $title1, $to1);
                            if ($owner_query->mobile != '') {
                                if (env('NEXMO_API') == null) {
            						$this->textbelt($owner_query->mobile, $data['message_data']);
            					} else {
            						$this->nexmo($owner_query->mobile, $data['message_data']);
            					}
                            }
                        } else {
                            $uport_data['password'] = sha1($action_v['uport_id']);
                            $uport_data['email'] = $action_v['email'];
                            $uport_data['sub'] = $action_v['uport_id'];
                            DB::table('oauth_users')->insert($user_data);
                            DB::table('users')->insert($user_data);
                            $url = URL::to('login');
                            $data2['message_data'] = 'You are now registered to access to the HIE of One Authorization Server for ' . $owner_query->firstname . ' ' . $owner_query->lastname . '.<br>';
                            $data2['message_data'] .= 'Go to ' . $url . ' to get started.';
                            $title2 = 'New Registration';
                            $to2 = $action_v['email'];
                            $this->send_mail('auth.emails.generic', $data2, $title2, $to2);
                        }
                    }
                }
            }
            $return = 'OK';
        } else {
            $return = 'Not Authorized';
        }
        return $return;
    }

    public function pnosh_sync(Request $request)
    {
        $return = 'Error';
        if ($request->isMethod('post')) {
            $query = DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->where('client_secret', '=', $request->input('client_secret'))->first();
            if ($query) {
                $user = DB::table('users')->where('email', '=', $request->input('old_email'))->first();
                if ($user) {
                    $user1 = DB::table('oauth_users')->where('email', '=', $request->input('old_email'))->first();
                    $owner = DB::table('owner')->where('id', '=', '1')->where('sub', '=', $user1->sub)->first();
                    if ($owner) {
                        $owner_data = [
                            'email' => $request->input('email'),
                            'mobile' => $request->input('sms'),
                            'lastname' => $request->input('lastname'),
                            'firstname' => $request->input('firstname'),
                            'DOB' => $request->input('DOB')
                        ];
                        DB::table('owner')->where('id', '=', $owner->id)->update($owner_data);
                        $data['email'] = $request->input('email');
                        $data['last_name'] = $request->input('lastname');
                        $data['first_name'] = $request->input('firstname');
                        DB::table('oauth_users')->where('email', '=', $request->input('old_email'))->update($data);
                        $data1['email'] = $request->input('email');
                        DB::table('users')->where('email', '=', $request->input('old_email'))->update($data1);
                        $pnosh_update['client_name'] = 'Patient NOSH for ' .  $request->input('firstname') . ' ' . $request->input('lastname');
                        DB::table('oauth_clients')->where('client_id', '=', $query->client_id)->update($pnosh_update);
                        $response = $this->directory_update_api();
                        $return = 'Contact data synchronized';
                    }
                }
            }
        }
        return $return;
    }

    public function get_mdnosh(Request $request)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $query = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        $authorized = DB::table('oauth_clients')->where('client_id', '!=', $query->client_id)->where('authorized', '=', 1)->get();
        $return = [];
        if ($authorized->count()) {
            foreach ($authorized as $row) {
                if (preg_match('/\bmdNOSH\b/',$row->client_name)) {
                    $user_array = explode(' ', $row->user_id);
                    if (in_array($query->user_id, $user_array)) {
                        $return['urls'][] = $row->client_uri;
                    }
                }
            }
        }
        $return['access_token'] = $token;
        $return['user_id'] = $query->user_id;
        $return['client_id'] = $query->client_id;
        return $return;
    }

    // Demo functions

    public function check_demo(Request $request)
    {
        $file = File::get(base_path() . "/.timer");
        $arr = explode(',', $file);
        if (time() < $arr[0]) {
            $left = ($arr[0] - time()) / 60;
            $return = round($left) . ',' . $arr[1];
            return $return;
        } else {
            return 'OK';
        }
    }

    public function check_demo_self(Request $request)
	{
        $return = 'OK';
        $return1 = 'OK';
        $file = File::get(base_path() . "/.timer");
        $arr = explode(',', $file);
        if (time() < $arr[0]) {
            $left = ($arr[0] - time()) / 60;
            $return = round($left) . ',' . $arr[1];
        }
		if ($return !== 'OK') {
			$arr = explode(',', $return);
			if ($arr[1] !== $request->ip()) {
				// Alert
				$return1 = 'You have ' . $arr[0] . ' minutes left to finish the demo.';
			}
		}
		return $return1;
	}

    public function invite_demo(Request $request)
    {
        if (route('home') == 'https://shihjay.xyz/home') {
            if ($request->isMethod('post')) {
                $this->validate($request, [
                    'email' => 'required'
                ]);
                $data['email'] = $request->input('email');
                $owner = DB::table('owner')->first();
                DB::table('oauth_users')->where('sub', '=', $owner->sub)->update($data);
                $oauth_user = DB::table('oauth_users')->where('sub', '=', $owner->sub)->first();
                DB::table('users')->where('name', '=', $oauth_user->username)->update($data);
                $time = time() + 600;
                $file = $time . ',' . $request->ip();
                File::put(base_path() . "/.timer", $file);
                Session::flush();
                Auth::logout();
                return redirect()->route('login');
            } else {
                $data = [
                    'noheader' => true,
                    'timer' => true
                ];
                $file = File::get(base_path() . "/.timer");
                $arr = explode(',', $file);
                if (time() > $arr[0]) {
                    $data['timer'] = false;
                }
                if ($data['timer'] == true) {
                    $left = ($arr[0] - time()) / 60;
                    $data['timer_val'] = round($left);
                    $data['timer_val1'] = 10 - $data['timer_val'];
                    $newfile = $arr[0] . ',' . $request->ip();
                    File::put(base_path() . "/.timer", $newfile);
                }
                return view('reset_demo', $data);
            }
        } else {
            return redirect()->route('welcome');
        }
    }

    public function reset_demo(Request $request)
    {
        if (route('welcome') == 'https://shihjay.xyz') {
            if ($request->isMethod('post')) {
                $this->validate($request, [
                    'email' => 'required'
                ]);
                // $client = new Google_Client();
                // putenv("GOOGLE_APPLICATION_CREDENTIALS=" . base_path() . "/.google.json");
                // getenv('GOOGLE_APPLICATION_CREDENTIALS');
                // $client->useApplicationDefaultCredentials();
                // $client->setApplicationName("Sheets API");
                // $client->setScopes(['https://www.googleapis.com/auth/drive', 'https://spreadsheets.google.com/feeds']);
                // $fileId = '1CTTYbiMvR3EdS46-uWXDuRlm__JkUOQdRBCFWCD0QlA';
                // $tokenArray = $client->fetchAccessTokenWithAssertion();
                // $accessToken = $tokenArray["access_token"];
                // $url = "https://sheets.googleapis.com/v4/spreadsheets/" . $fileId . "/values/Resets!A1:B1:append?valueInputOption=USER_ENTERED";
                // $method = 'POST';
                // $headers = ["Authorization" => "Bearer $accessToken", 'Content-Type' => 'application/atom+xml'];
                // $value[] = $request->input('email');
                // $values[] = $value;
                // $post = [
                //     'range' => 'Resets!A1:B1',
                //     'majorDimension' => 'ROWS',
                //     'values' => $values,
                // ];
                // $postBody = json_encode($post);
                // //$postBody = '<entry xmlns="http://www.w3.org/2005/Atom" xmlns:gsx="http://schemas.google.com/spreadsheets/2006/extended"><gsx:email>' . $request->input('email') . '</gsx:email></entry>';
                // $httpClient = new GuzzleHttp\Client(['headers' => $headers]);
                // $resp = $httpClient->request($method, $url, ['body' => $postBody]);
                $time = time() + 600;
                $file = $time . ',' . $request->ip();
                File::put(base_path() . "/.timer", $file);
                Session::flush();
                Auth::logout();
                return redirect('https://shihjay.xyz/nosh/reset_demo');
            } else {
                $data = [
                    'noheader' => true,
                    'timer' => true
                ];
                $file = File::get(base_path() . "/.timer");
                $arr = explode(',', $file);
                if (time() > $arr[0]) {
                    $data['timer'] = false;
                }
                if ($data['timer'] == true) {
                    $left = ($arr[0] - time()) / 60;
                    $data['timer_val'] = round($left);
                    $data['timer_val1'] = 10 - $data['timer_val'];
                    $newfile = $arr[0] . ',' . $request->ip();
                    File::put(base_path() . "/.timer", $newfile);
                }
                return view('reset_demo', $data);
            }
        } else {
            return redirect()->route('welcome');
        }
    }

    public function test1(Request $request)
    {
        // $url = 'http://' . env('SYNCTHING_HOST') . ":8384/rest/system/config";
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, [
        //     "X-API-Key: " . env('SYNCTHING_APIKEY')
        // ]);
        // curl_setopt($ch, CURLOPT_HEADER, 0);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        // curl_setopt($ch, CURLOPT_FAILONERROR,1);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        // curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0);
        // $response = curl_exec($ch);
        // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close ($ch);
        // return $response;
        // $owner = DB::table('owner')->first();
        // $user = DB::table('oauth_users')->where('sub', '=', $owner->sub)->first();
        // $url0 = 'http://localhost/nosh5';
        // if ($user) {
        //     if (!empty($user->picture)) {
        //         $ch3 = curl_init();
        //         $pnosh_url3 = $url0 . '/pnosh_install_photo';
        //         $img = file_get_contents($user->picture);
        //         $type = pathinfo($user->picture, PATHINFO_EXTENSION);
        //         $data_img = 'data:image/' . $type . ';base64,' . base64_encode($img);
        //         $filename = str_replace(storage_path('app/public/'), '', $user->picture);
        //         $params3 = [
        //             'data' => $data_img,
        //             'photo_filename' => $filename
        //         ];
        //         $post_body3 = json_encode($params3);
        //         $content_type3 = 'application/json';
        //         curl_setopt($ch3,CURLOPT_URL, $pnosh_url3);
        //         curl_setopt($ch3, CURLOPT_POST, 1);
        //         curl_setopt($ch3, CURLOPT_POSTFIELDS, $post_body3);
        //         curl_setopt($ch3, CURLOPT_HTTPHEADER, [
        //             "Content-Type: {$content_type3}",
        //             'Content-Length: ' . strlen($post_body3)
        //         ]);
        //         curl_setopt($ch3, CURLOPT_HEADER, 0);
        //         curl_setopt($ch3, CURLOPT_SSL_VERIFYPEER, FALSE);
        //         curl_setopt($ch3,CURLOPT_FAILONERROR,1);
        //         curl_setopt($ch3,CURLOPT_FOLLOWLOCATION,1);
        //         curl_setopt($ch3,CURLOPT_RETURNTRANSFER,1);
        //         curl_setopt($ch3,CURLOPT_TIMEOUT, 60);
        //         curl_setopt($ch3,CURLOPT_CONNECTTIMEOUT ,0);
        //         $pnosh_result3 = curl_exec($ch3);
        //         if(curl_exec($ch3) === false) {
        //             return 'Curl error: ' . curl_error($ch3);
        //         } else {
        //             return $pnosh_result3;;
        //         }
        //     }
        // }
        // return 'No';
        $url = URL::temporarySignedRoute(
            'login', now()->addDay(), [
                'user_id'       => 1,
                'url_redirect'  => route('home'),
            ],
        );
        return $url;
    }
}
