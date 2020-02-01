<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use File;
use Form;
use Illuminate\Http\Request;
use QrCode;
use Session;
use Storage;
use URL;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('csrf');
    }

    /**
     * Show the registered resource services.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (Session::get('is_owner') == 'no') {
            return redirect()->route('welcome');
        }
        $data['name'] = Session::get('owner');
        $data['title'] = 'My Consents';
        $users = DB::table('oauth_users')->where('password', '!=', 'Pending')->get()->count();
        $rs = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get()->count();
        $pnosh = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->where('client_name', 'LIKE', "%Patient NOSH for%")->first();
        $smart_on_fhir = array();
        if ($pnosh) {
            $pnosh_url = $pnosh->client_uri;
            $url = $pnosh->client_uri . '/smart_on_fhir_list';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
            $result = curl_exec($ch);
            curl_close ($ch);
            // $smart_on_fhir = json_decode($result, true);
        }
        $rs1 = count($smart_on_fhir);
        $resource_servers = $rs + $rs1;
        $certifiers = count($this->certifier_default());
        $policy = count($this->user_policies());
        $policy1 = DB::table('custom_policy')->get()->count();
        $policies = $policy + $policy1;
        $directories = DB::table('directories')->get()->count();
        $data['content'] = '<h2>I have <a href="' . route('users') . '">' . $users . ' <i class="fa fa-users fa-2x"></i> authorized user';
        if ($users !== 1) {
            $data['content'] .= 's';
        }
        $data['content'] .= '</a></h2><h2 class="as_h2_1">who have access to <a href="' . route('resource_servers') . '">' . $resource_servers . ' <i class="fa fa-folder fa-2x"></i> health record';
        if ($resource_servers !== 1) {
            $data['content'] .= 's';
        }
        $data['content'] .= '</a></h2><h2 class="as_h2_2">governed by <a href="' . route('custom_policies') . '">' . $policies . ' <i class="fa fa-bookmark fa-2x"></i> ';
        if ($policies !== 1) {
            $data['content'] .= 'policies';
        } else  {
            $data['content'] .= 'policy';
        }
        $data['content'] .= '</a></h2><h2 class="as_h2_3">authorized by <a href="' . route('certifiers') . '">' . $certifiers . ' <i class="fa fa-thumbs-o-up fa-2x"></i> certifier';
        if ($certifiers !== 1) {
            $data['content'] .= 's';
        }
        $data['content'] .= '</a></h2><h2 class="as_h2_4">and belonging to <a href="' . route('directories') . '">' . $directories . ' <i class="fa fa-sitemap fa-2x"></i> ';
        if ($directories !== 1) {
            $data['content'] .= 'directories';
        } else {
            $data['content'] .= 'directory';
        }
        $data['content'] .= '.</a></h2>';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $this->default_user_policies_create();
        // $data = array_merge($data, $this->resources_list());
        return view('home', $data);
    }

    public function activity_logs(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Activity Log';
        $data['content'] = 'No activities yet.';
        $query = DB::table('activity_log')->orderBy('created_at', 'desc')->get();
        if ($query->count()) {
            $data['content'] = '<form role="form"><div class="form-group">' . csrf_field() . '<input class="form-control" id="searchinput" type="search" placeholder="Filter Results..." /></div>';
            $data['content'] .= '<ul class="list-group searchlist">';
            foreach ($query as $row) {
                $data['content'] .= '<li class="list-group-item">' .  date('Y-m-d H:i:s', strtotime($row->created_at)) . ' - ' . $row->username . ' - ' . $row->action . '</li>';
            }
            $data['content'] .= '</ul></form>';
        }
        return view('home', $data);
    }

    public function ajax_change_user_policy(Request $request)
    {
        $names = explode(',', $request->input('name'));
        $claim_id = $request->input('claim_id');
        $setting = $request->input('setting');
        $type = $request->input('type');
        $confirm_names = [];
        if ($type == 'authorized') {
            foreach ($names as $name) {
                $query = DB::table('policy')->where('name', '=', $name)->get();
                if ($query->count()) {
                    foreach ($query as $row) {
                        DB::table('claim_to_policy')->where('claim_id', '=', $claim_id)->where('policy_id', '=', $row->policy_id)->delete();
                    }
                }
                $claim = DB::table('claim')->where('claim_id', '=', $claim_id)->first();
                $for = $claim->claim_value;
                if ($setting == true) {
                    if ($query->count()) {
                        foreach ($query as $row) {
                            DB::table('claim_to_policy')->where('claim_id', '=', $claim_id)->where('policy_id', '=', $row->policy_id)->delete();
                            $data = [
                                'claim_id' => $claim_id,
                                'policy_id' => $row->policy_id
                            ];
                            DB::table('claim_to_policy')->insert($data);
                        }
                        $confirm_names[] = $name;
                    }
                }
            }
        } else {
            $query1 = DB::table('invitation')->where('id', '=', $claim_id)->first();
            $for = $query1->email;
            $policies_arr = json_decode($query1->policies, true);
            foreach ($names as $name) {
                if ($setting == true) {
                    $policies_arr[] = $name;
                    $confirm_names[] = $name;
                } else {
                    unset($polices_arr[$name]);
                }
                $data2['policies'] = json_encode($polices_arr);
                DB::table('invitation')->where('id', '=', $claim_id)->update($data2);
            }
        }
        $message = implode(',', $confirm_names) . ' set as a custom policy for '. $for;
        return $message;
    }

    public function authorize_client(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Clients Pending Authorization';
        $data['message_action'] = Session::get('message_action');
        $data['content'] = '<p>Clients are outside apps that work on behalf of users to access your health information resources.  You can authorize or unauthorize them at any time.</p>';
        Session::forget('message_action');
        $oauth_scope_array = [
            'openid' => 'OpenID Connect',
            'uma_authorization' => 'Access Resources',
            'uma_protection' => 'Register Resources'
        ];
        $query = DB::table('oauth_clients')->where('authorized', '=', 0)->get();
        if ($query->count()) {
            $data['content'] .= '<ul class="list-group">';
            foreach ($query as $client) {
                // Label
                $data['content'] .= '<li class="list-group-item container-fluid"><span><b>' . $client->client_name . '</b>';
                // Info
                $data['content'] .= '<br><br><i class="fa fa-lg fa-key" style="margin-right:10px;" as-info="This specifies what information is available and how they are accessible to the authorized client."></i>';
                $scope_array = explode(' ', $client->scope);
                $i = 0;
                foreach ($scope_array as $scope) {
                    if (array_key_exists($scope, $oauth_scope_array)) {
                        if ($i > 0) {
                            $data['content'] .= ', ';
                        }
                        $data['content'] .= $oauth_scope_array[$scope];
                        $i++;
                    }
                }
                $data['content'] .= '</span>';
                // Actions
                $data['content'] .= '<span class="pull-right">';
                $data['content'] .= '<a href="' . route('authorize_client_action', [$client->client_id]) . '" class="btn fa-btn" data-toggle="tooltip" title="Authorize"><i class="fa fa-check fa-lg" style="color:green"></i></a>';
                $data['content'] .= '<a href="' . route('authorize_client_disable', [$client->client_id]) . '" class="btn fa-btn" data-toggle="tooltip" title="Deny"><i class="fa fa-times fa-lg" style="color:red"></i></a>';
                $data['content'] .= '</span>';
                $data['content'] .= '</li>';
            }
            $data['content'] .= '</ul>';
        } else {
            $data['content'] .= 'No clients pending authorization.';
        }
        return view('home', $data);
    }

    public function authorize_client_action(Request $request, $id)
    {
        $data['authorized'] = 1;
        DB::table('oauth_clients')->where('client_id', '=', $id)->update($data);
        $query = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
        Session::put('message_action', 'You just authorized a client named ' . $query->client_name);
        return redirect()->route('clients');
    }

    public function authorize_client_disable(Request $request, $id)
    {
        $query = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
        Session::put('message_action', 'You just unauthorized a client named ' . $query->client_name);
        DB::table('oauth_clients')->where('client_id', '=', $id)->delete();
        return redirect()->route('clients');
    }

    public function authorize_resource_server(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Resource Registration Consent';
        $data['content'] = 'No resource servers pending authorization.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $query = DB::table('oauth_clients')->where('client_id', '=', Session::get('oauth_client_id'))->first();
        if ($query) {
            $client_name_arr = explode(' ', $query->client_name);
            if (Session::get('oauth_response_type') == 'code') {
                // Automatic registration if pNOSH or Directory from subdomain
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
                $target_url = $query->client_uri;
                $target_url = str_replace(array('http://','https://'), '', $target_url);
                $root_url2 = explode('/', $target_url);
                $root_url3 = explode('.', $root_url2[0]);
                if (isset($root_url3[1])) {
                    $root_url3 = array_slice($root_url3, -2, 2, false);
                    $final_root_url1 = implode('.', $root_url3);
                } else {
                    $final_root_url1 = $root_url2[0];
                }
                if ($client_name_arr[0] . $client_name_arr[1] == 'PatientNOSH' || $final_root_url == $final_root_url1) {
                    $owner = DB::table('owner')->first();
                    $default_policy_types = $this->default_policy_type();
                    $types = [];
                    foreach ($default_policy_types as $default_policy_type) {
                        $consent = 'consent_' . $default_policy_type;
                        $data1[$consent] = $owner->{$default_policy_type};
                        if ($owner->{$default_policy_type} == 1) {
                            $types[] = $default_policy_type;
                        }
                    }
                    $data1['authorized'] = 1;
                    $user_array = explode(' ', $query->user_id);
                    $user_array[] = Session::get('username');
                    $data1['user_id'] = implode(' ', $user_array);
                    Session::put('is_authorized', 'true');
                    DB::table('oauth_clients')->where('client_id', '=', Session::get('oauth_client_id'))->update($data1);
                    $this->group_policy($request->input('client_id'), $types, 'update');
                    // Session::put('message_action', 'You just authorized a resource server named ' . $query->client_name);
                    return redirect()->route('authorize');
                }
            }
            $scopes_array = explode(' ', $query->scope);
            if ($query->logo_uri == '') {
                $data['content'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
            } else {
                $data['content'] = '<div><img src="' . $query->logo_uri . '" style="margin:20px;text-align: center;"></div>';
            }
            $data['content'] .= '<h3>Resource Registration Consent for ' . $query->client_name . '</h3>';
            $data['content'] .= '<p>By clicking Allow, you consent to sharing your information on ' . $query->client_name . ' according to the policies selected below. You can revoke consent or change your policies for ' . $query->client_name . ' at any time using the My Resources page.  Parties requesting access to your information will be listed on the My Clients page where their access can also be revoked or changed.   Your sharing defaults can be changed on the My Policies page.</p>';
            $data['content'] .= '<input type="hidden" name="client_id" value="' . $query->client_id . '"/>';
            $data['client'] = $query->client_name;
            $data['policies'] = $this->policy_build();
            return view('rs_authorize', $data);
        } else {
            return redirect()->route('home');
        }
    }

    public function authorize_user(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Users Pending Authorization';
        $data['content'] = 'No users pending authorization.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data = array_merge($data, $this->users_list(true));
        return view('home', $data);
    }

    public function authorize_user_action(Request $request, $id)
    {
        $data['password'] = sha($id);
        DB::table('oauth_users')->where('username', '=', $id)->update($data);
        $query = DB::table('oauth_users')->where('username', '=', $id)->first();
        $owner_query = DB::table('owner')->first();
        $data1['message_data'] = 'You have been authorized access to HIE of One Authorizaion Server for ' . $owner_query->firstname . ' ' . $owner_query->lastname;
        $data1['message_data'] .= 'Go to ' . route('login') . '/ to login.';
        $title = 'Access to HIE of One';
        $to = $query->email;
        $this->send_mail('auth.emails.generic', $data1, $title, $to);
        Session::put('message_action', 'You just authorized a user named ' . $query->first_name . ' ' . $query->last_name);
        return redirect()->route('authorize_user');
    }

    public function authorize_user_disable(Request $request, $id)
    {
        $query = DB::table('oauth_users')->where('username', '=', $id)->first();
        Session::put('message_action', 'You just unauthorized a user named ' . $query->first_name . ' ' . $query->last_name);
        DB::table('oauth_users')->where('username', '=', $id)->delete();
        DB::table('users')->where('name', '=', $id)->delete();
        return redirect()->route('authorize_user');
    }

    public function certifier_add(Request $request)
    {
        Session::put('message_action', 'Pending feature!');
        return redirect()->route('home');
    }

    public function certifiers(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Certifiers';
        $data['content'] = '<div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        $data['content'] .= '<p>Certifiers are used during the user authentication process to verify the identity of the user accessing your health information.  Click on a role for more information.</p>';
        $data['content'] .= '<p><div style="background-color:#000000;height:30px;width:30px;display:inline-block;float:none;margin-right:10px"><img src="' . asset('assets/uport-logo-white.svg') . '" height="30" width="30" style="margin-right:5px"></img></div> designates that the certifier uses uPort, an open identity system that features a self-sovereign wallet and verified credentials</p>';
        $data['content'] .= '<p><div style="display:inline-block;float:none;margin-right:10px"><i class="fa fa-fw fa-lg fa-openid" style="height:30px;width:30px;color#000000;"></i></div> designates that the certifier uses OpenIDConnect, an identity layer on top of the Oauth 2.0 protocol, which allows clients to verify the identity of the end user.</p>';
        $data['content'] .= '</div>';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $certifiers = $this->certifier_default();
        $certifier_roles = $this->certifier_roles();
        $data['content'] .= '<ul class="list-group">';
        foreach ($certifiers as $certifier_k => $certifier_v) {
            // Label
            $logo = '';
            if (in_array('uPort', $certifier_v['badges'])) {
                $logo = '<div style="background-color:#000000;height:30px;width:30px;display:inline-block;float:none;margin-right:10px;"><img src="' . asset('assets/uport-logo-white.svg') . '" height="30" width="30" style="margin-right:10px"></img></div>';
            }
            if (in_array('OIDC', $certifier_v['badges'])) {
                $logo = '<div style="display:inline-block;float:none;margin-right:10px;"><i class="fa fa-fw fa-lg fa-openid" style="height:30px;width:30px;color#000000;"></i></div>';
            }
            $data['content'] .= '<li class="list-group-item container-fluid"><span>' . $logo . '<b>' . $certifier_k . '</b>';
            // Info
            $data['content'] .= '<br><span style="margin-left:30px;"><b>Roles:</b></span>';
            foreach ($certifier_roles as $certifier_role_k => $certifier_role_v) {
                $data['content'] .= '<br><i class="fa fa-thumbs-o-up fa-1x fa-fw" data-toggle="tooltip" title="Role" style="margin-left:30px;margin-right:10px;"></i><span class="as-info" as-info="' . $certifier_role_v['description'] . '" style="margin-right:10px">' . $certifier_role_k . '</span>';
                if ($certifier_role_k !== 'Custom Role') {
                    if (in_array($certifier_role_k, $certifier_v['roles'])) {
                        $data['content'] .= '<i class="fa fa-check fa-lg no-edit" style="color:green;"></i>';
                    } else {
                        $data['content'] .= '<i class="fa fa-times fa-lg no-edit" style="color:red;"></i>';
                    }
                } else {
                    if (in_array($certifier_role_k, $certifier_v['roles'])) {
                        $data['content'] .= $certifier_v['custom_role'];
                    }
                }
            }
            $data['content'] .= '</span>';
            // Actions
            $data['content'] .= '<span class="pull-right">';
            if (isset($certifier_v['link'])) {
                $data['content'] .= '<a href="' . $certifier_v['link'] . '" class="btn fa-btn" target="_blank" data-toggle="tooltip" title="Jump To"><i class="fa fa-share fa-lg" style="color:blue"></i></a>';
            }
            $data['content'] .= '</span></li>';
        }
        $data['content'] .= '</ul>';
        $data['back'] = '<a href="' . route('certifier_add') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-plus"></i> Add Certifier</a>';
        return view('home', $data);
    }

    public function change_notify(Request $request, $id, $value, $type)
    {
        $data['notify'] = (int)$value;
        $status = 'No';
        if ($value == 1) {
            $status = 'Yes';
        }
        if ($type == 'authorized') {
            $query = DB::table('oauth_users')->where('username', '=', $id)->first();
            DB::table('oauth_users')->where('username', '=', $id)->update($data);
        } else {
            $query = DB::table('invitation')->where('id', '=', $id)->first();
            DB::table('invitation')->where('id', '=', $id)->update($data);
        }
        Session::put('message_action', 'Ping Me for ' . $query->first_name . ' '. $query->last_name . ' changed to ' . $status);
        return redirect()->back();
    }

    public function change_password(Request $request)
    {
        $data['name'] = Session::get('owner');
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'old_password' => 'required',
                'password' => 'required|min:4',
                'confirm_password' => 'required|min:4|same:password',
            ]);
            $query = DB::table('oauth_users')->where('username', '=', Session::get('username'))->first();
            if ($query->password == sha1($request->input('old_password'))) {
                $data1['password'] = sha1($request->input('password'));
                DB::table('oauth_users')->where('username', '=', Session::get('username'))->update($data1);
                Session::put('message_action', 'Password changed!');
                $this->directory_update_api();
                return redirect()->route('home');
            } else {
                return redirect()->back()->withErrors(['tryagain' => 'Your old password was incorrect.  Try again.']);
            }
        } else {
            return view('changepassword', $data);
        }
    }

    public function change_policy(Request $request)
    {
        if ($request->input('submit') == 'save') {
            $default_policy_types = $this->default_policy_type();
            foreach ($default_policy_types as $default_policy_type) {
                if ($request->has('consent_' . $default_policy_type)) {
                    if ($request->input('consent_' . $default_policy_type) == 'on') {
                        $data[$default_policy_type] = 1;
                    } else {
                        $data[$default_policy_type] = 0;
                    }
                } else {
                    $data[$default_policy_type] = 0;
                }
            }
            $query = DB::table('owner')->first();
            DB::table('owner')->where('id', '=', $query->id)->update($data);
            Session::put('message_action', 'Default policies saved!');
            return redirect()->route('home');
        } else {
            return redirect()->route('home');
        }
    }

    public function change_role(Request $request)
    {
        $data['role'] = $request->input('role');
        if ($request->input('type') == 'authorized') {
            DB::table('oauth_users')->where('username', '=', $request->input('id'))->update($data);
        } else {
            DB::table('invitation')->where('id', '=', $request->input('id'))->update($data);
        }
        return 'Role changed to ' . $request->input('role');
    }

    public function change_user_policy(Request $request, $name, $claim_id, $setting, $type)
    {
        $status = 'deny.';
        if ($type == 'authorized') {
            $query = DB::table('policy')->where('name', '=', $name)->get();
            $claim = DB::table('claim')->where('claim_id', '=', $claim_id)->first();
            $for = $claim->claim_value;
            if ($setting == 'true') {
                $status = 'allow.';
                if ($query->count()) {
                    foreach ($query as $row) {
                        $data = [
                            'claim_id' => $claim_id,
                            'policy_id' => $row->policy_id
                        ];
                        DB::table('claim_to_policy')->insert($data);
                    }
                }
            } else {
                if ($query->count()) {
                    foreach ($query as $row) {
                        DB::table('claim_to_policy')->where('claim_id', '=', $claim_id)->where('policy_id', '=', $row->policy_id)->delete();
                    }
                }
            }
        } else {
            $query1 = DB::table('invitation')->where('id', '=', $claim_id)->first();
            $for = $query1->email;
            $policies_arr = json_decode($query1->policies, true);
            if ($setting == 'true') {
                $status = 'allow.';
                $policies_arr[] = $name;
            } else {
                if (($key = array_search($name, $policies_arr)) !== false) {
                    unset($policies_arr[$key]);
                }
            }
            $data2['policies'] = json_encode($policies_arr);
            DB::table('invitation')->where('id', '=', $claim_id)->update($data2);
        }
        Session::put('message_action', 'Policy for ' . $name . ' for '. $for . ' changed to ' . $status);
        return redirect()->back();
    }

    public function clients(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Authorized Clients';
        $data['content'] = '<div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        $data['content'] .= '<p>Clients are outside apps that work on behalf of users to access your health information resources.  You can authorize or unauthorize them at any time.</p>';
        $data['content'] .= '</div>';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $oauth_scope_array = [
            'openid' => 'OpenID Connect',
            'uma_authorization' => 'Access Resources',
            'uma_protection' => 'Register Resources'
        ];
        $query = DB::table('oauth_clients')->where('authorized', '=', 1)->get();
        if ($query->count()) {
            $data['content'] .= '<ul class="list-group">';
            foreach ($query as $client) {
                // Label
                $data['content'] .= '<li class="list-group-item container-fluid"><span><b>' . $client->client_name . '</b>';
                // Info
                $data['content'] .= '<br><br><i class="fa fa-lg fa-key" style="margin-right:10px;" as-info="This specifies what information is available and how they are accessible to the authorized client."></i>';
                $scope_array = explode(' ', $client->scope);
                $i = 0;
                foreach ($scope_array as $scope) {
                    if (array_key_exists($scope, $oauth_scope_array)) {
                        if ($i > 0) {
                            $data['content'] .= ', ';
                        }
                        $data['content'] .= $oauth_scope_array[$scope];
                        $i++;
                    }
                }
                $data['content'] .= '</span>';
                // Actions
                $data['content'] .= '<span class="pull-right">';
                $data['content'] .= '<a href="' . route('authorize_client_disable', [$client->client_id]) . '" class="btn fa-btn" data-toggle="tooltip" title="Unauthorize"><i class="fa fa-times fa-lg" style="color:red"></i></a>';
                $data['content'] .= '</span></li>';
            }
            $data['content'] .= '</ul>';
        } else {
            $data['content'] .= 'No authorized clients.';
        }
        return view('home', $data);
    }

    public function consents_resource_server(Request $request)
    {
        if (!Session::has('current_client_id')) {
            return redirect()->route('home');
        }
        $data['name'] = Session::get('owner');
        $data['title'] = 'Resource Registration Consent';
        $data['message_action'] = Session::get('message_action');
        $data['back'] = '<a href="' . route('resources', [Session::get('current_client_id')]) . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resources</a>';
        Session::forget('message_action');
        $query = DB::table('oauth_clients')->where('client_id', '=', Session::get('current_client_id'))->first();
        $scopes_array = explode(' ', $query->scope);
        if ($query->logo_uri == '') {
            $data['content'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
        } else {
            $data['content'] = '<div><img src="' . $query->logo_uri . '" style="margin:20px;text-align: center;"></div>';
        }
        $data['content'] .= '<h3>Resource Registration Consent for ' . $query->client_name . '</h3>';
        $data['content'] .= '<p>By clicking Allow, you consent to sharing your information on ' . $query->client_name . ' according to the policies selected below. You can revoke consent or change your policies for ' . $query->client_name . ' at any time using the My Resources page.  Parties requesting access to your information will be listed on the My Clients page where their access can also be revoked or changed.   Your sharing defaults can be changed on the My Policies page.</p>';
        $data['content'] .= '<input type="hidden" name="client_id" value="' . $query->client_id . '"/>';
        $data['client'] = $query->client_name;
        $data['policies'] = $this->policy_build(true);
        $data['set'] = 'true';
        return view('rs_authorize', $data);
    }

    public function custom_policies(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'My Custom Policies';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $default_policy_types = $this->default_policy_type();
        $data['back'] = '<a href="' . URL::to('home') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resource Servers</a>';
        $query = DB::table('custom_policy')->get();
        $data['content'] = '<div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        $data['content'] .= '<p>Policies govern what specific health information resources are available for certain users.  Click on a row to customize a policy.</p>';
        $data['content'] .= '</div>';
        $data['content'] .= '<div class="list-group">';
        if ($query->count()) {
            foreach ($query as $custom_policy) {
                $data['content'] .= '<a href="' . route('custom_policy_edit', [$custom_policy->id]) .'" class="list-group-item">' . $custom_policy->name . '</a>';
            }
        } else {
            // Add default policies
            $data['content'] .= '<div class="list-group">';
            $arr[] = [
                'name' => 'No sensitive',
                'type' => 'scope-exclude',
                'parameters' => 'sens/*'
            ];
            $arr[] = [
                'name' => 'Write Notes',
                'type' => 'scope-include',
                'parameters' => 'view,edit'
            ];
            $arr[] = [
                'name' => 'Everything',
                'type' => 'all',
                'parameters' => ''
            ];
            $arr[] = [
                'name' => 'Consenter',
                'type' => 'fhir',
                'parameters' => ''
            ];
            foreach ($arr as $arr_item) {
                $custom_policy_id = DB::table('custom_policy')->insertGetId($arr_item);
                $data['content'] .= '<a href="' . route('custom_policy_edit', [$custom_policy_id]) .'" class="list-group-item">' . $arr_item['name'] . '</a>';
            }
        }
        $data['content'] .= '<a href="' . route('custom_policy_edit') .'" class="list-group-item"><i class="fa fa-btn fa-plus"></i> <b>Add custom policy</b></a>';
        $data['content'] .= '</div>';
        return view('home', $data);
    }

    public function custom_policy_edit(Request $request, $id='0')
    {
        if ($request->isMethod('post')) {
            if ($request->input('submit') == 'save') {
                if ($id == '0') {
                    $this->validate($request, [
                        'name' => 'required|unique:policy,name'
                    ]);
                }
                $message_action = 'Custom policy updated';
                $parameter_arr = $request->input('parameter', []);
                $fhir_scope_arr = $request->input('fhir_scope', []);
                $parameters = [];
                if ($request->input('type') == 'fhir') {
                    foreach ($fhir_scope_arr as $fhir_scope_row) {
                        $subpam['fhir'] = $fhir_scope_row;
                        foreach ($parameter_arr as $parameter_row) {
                            $subpam['scope'][] = $parameter_row;
                        }
                        $parameters[] = $subpam;
                    }
                } else {
                    $parameters = $parameter_arr;
                }
                $data = [
                    'name' => $request->input('name'),
                    'type' => $request->input('type'),
                    'parameters' => json_encode($parameters)
                ];
                if ($id == '0') {
                    DB::table('custom_policy')->insert($data);
                    $message_action = 'Custom policy added';
                    $old_name = $data['name'];
                } else {
                    $old_name_query = DB::table('custom_policy')->where('id', '=', $id)->first();
                    $old_name = $old_name_query->name;
                    DB::table('custom_policy')->where('id', '=', $id)->update($data);
                }
                $this->policies_build($data['name'], $data['type'], $parameters, '', $old_name);
            }
            if ($request->input('submit') == 'delete') {
                $policy_query = DB::table('custom_policy')->where('id', '=', $id)->first();
                DB::table('custom_policy')->where('id', '=', $id)->delete();
                $this->policies_build($policy_query->name, $policy_query->type, [], 'delete');
                $message_action = 'Custom policy removed';
            }
            if ($request->input('submit') == 'cancel') {
                $message_action = 'Custom policy unchanaged';
            }
            Session::put('message_action', $message_action);
            return redirect()->route('custom_policies');
        } else {
            $data['name'] = Session::get('owner');
            $data['title'] = 'Edit Custom Policy';
            if ($id == '0') {
                $data['title'] = 'Add Custom Policy';
                $type = '';
                $parameter = '';
                $data['name_value'] = '';
            } else {
                $query = DB::table('custom_policy')->where('id', '=', $id)->first();
                $type = $query->type;
                $parameters = $query->parameters;
                $parameter_array = json_decode($parameters, true);
    if (json_last_error() == 4) {
                $parameter_array = [];
            }
                $data['edit'] = 'yes';
                $data['name_value'] = $query->name;
            }
            $type_arr[] = [
                'type' => 'all',
                'desc' => 'Everything'
            ];
            $type_arr[] = [
                'type' => 'fhir',
                'desc' => 'FHIR Resource'
            ];
            $type_arr[] = [
                'type' => 'scope-include',
                'desc' => 'Include the Following Scopes'
            ];
            $type_arr[] = [
                'type' => 'scope-exclude',
                'desc' => 'Exclude the Following Scopes'
            ];
            $data['type'] = '';
            foreach ($type_arr as $type_row) {
                $data['type'] .= '<option value="' . $type_row['type'] . '"';
                if ($type == $type_row['type']) {
                    $data['type'] .= ' selected="selected"';
                }
                $data['type'] .= '>' . $type_row['desc'] . '</option>';
            }
            $parameter_arr[] = [
                'parameter' => 'view',
                'desc' => 'View'
            ];
            $parameter_arr[] = [
                'parameter' => 'edit',
                'desc' => 'Edit'
            ];
            $parameter_arr[] = [
                'parameter' => 'sens/*',
                'desc' => 'All Sensitive'
            ];
            $parameter_arr[] = [
                'parameter' => 'conf/*',
                'desc' => 'All Confidential'
            ];
            $conf_arr = $this->fhir_scopes_confidentiality();
            $sens_arr = $this->fhir_scopes_sensitivities();
            foreach ($conf_arr as $conf_row_k => $conf_row_v) {
                $parameter_arr[] = [
                    'parameter' => $conf_row_k,
                    'desc' => $conf_row_v
                ];
            }
            foreach ($sens_arr as $sens_row_k => $sens_row_v) {
                $parameter_arr[] = [
                    'parameter' => $sens_row_k,
                    'desc' => $sens_row_v
                ];
            }
            $data['parameter'] = '';
            foreach ($parameter_arr as $parameter_row) {
                $data['parameter'] .= '<option value="' . $parameter_row['parameter'] . '"';
                if ($type !== 'fhir') {
                    if (in_array($parameter_row['parameter'], $parameter_array)) {
                        $data['parameter'] .= ' selected="selected"';
                    }
                } else {
                    foreach ($parameter_array as $parameter_row1) {
                        if (in_array($parameter_row['parameter'], $parameter_row1['scope'])) {
                            $data['parameter'] .= ' selected="selected"';
                        }
                    }
                }
                $data['parameter'] .= '>' . $parameter_row['desc'] . '</option>';
            }
            $fhir_array = $this->fhir_resources();
            foreach ($fhir_array as $fhir_row_k => $fhir_row_v) {
                $fhir_arr[] = [
                    'parameter' => $fhir_row_k,
                    'desc' => $fhir_row_v['name']
                ];
            }
            $data['fhir'] = '';
            foreach ($fhir_arr as $fhir_row) {
                $data['fhir'] .= '<option value="' . $fhir_row['parameter'] . '"';
                foreach ($parameter_array as $parameter_row2) {
                    if (isset($parameter_row2['fhir'])) {
                        if ($fhir_row['parameter'] == $parameter_row2['fhir']) {
                            $data['fhir'] .= ' selected="selected"';
                        }
                    }
                }
                $data['fhir'] .= '>' . $fhir_row['desc'] . '</option>';
            }
            $data['action'] = route('custom_policy_edit', [$id]);
            return view('custom_policy', $data);
        }
    }

    public function default_policies(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['name'] = Session::get('owner');
        $query = DB::table('owner')->first();
        $data['policies'] = $this->policy_build();
        $data['content'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
        $data['content'] .= '<h3>Resource Registration Consent Default Policies</h3>';
        $data['content'] .= '<p>You can set default policies (who gets access to your resources) whenever you have a new resource server registered to this authorization server.</p>';
        return view('policies', $data);
    }

    public function directories(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Authorized Directories';
        $directory_update_response = $this->directory_update_api();
        $directory_update_response1 = '';
        if (count($directory_update_response) > 0) {
            $directory_update_response1 = '<ul>';
            foreach ($directory_update_response as $directory_update_row) {
                $directory_update_response1 .= '<li>' . $directory_update_row . '</li>';
            }
            $directory_update_response1 .= '</ul>';
        }
        $data['message_action'] = Session::get('message_action');
        $data['message_action'] .= $directory_update_response1;
        Session::forget('message_action');
        $data = array_merge($data, $this->directories_list($request));
        $data['back'] = '<a href="' . route('directory_add') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-plus"></i> Add Directory</a>';
        return view('home', $data);
    }

    public function directory_add(Request $request, $type='')
    {
        $as_url = $request->root();
        $owner = DB::table('owner')->first();
        $user = DB::table('oauth_users')->where('sub', '=', $owner->sub)->first();
        $rs = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
        $rs_arr = [];
        if ($rs->count()) {
            foreach ($rs as $rs_row) {
                $rs_arr[] = [
                    'name' => $rs_row->client_name,
                    'uri' => $rs_row->client_uri,
                    'public' => $rs_row->consent_public_publish_directory,
                    'private' => $rs_row->consent_private_publish_directory,
                    'last_activity' => $rs_row->consent_last_activity
                ];
            }
        }
        $params = [
            'as_uri' => $as_url,
            'redirect_uri' => route('directory_add', ['approve']),
            'name' => $user->username,
            'last_update' => time(),
            'rs' => $rs_arr,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email
        ];
        if (Session::has('password')) {
            $params['password'] = Session::get('password');
        }
        if ($type == 'approve') {
            if (Session::has('directory_uri')) {
                $directory = [
                    'uri' => Session::get('directory_uri'),
                    'name' => $request->input('name'),
                    'directory_id' => $request->input('directory_id')
                ];
                DB::table('directories')->insert($directory);
                if ($rs) {
                    foreach ($rs as $rs_row1) {
                        $rs_to_directory = [
                            'directory_id' => $request->input('directory_id'),
                            'client_id' => $rs_row1->client_id,
                            'consent_public_publish_directory' => $rs_row1->consent_public_publish_directory,
                            'consent_private_publish_directory' => $rs_row1->consent_private_publish_directory,
                            'consent_last_activity' => $rs_row1->consent_last_activity
                        ];
                        if (Session::has('directory_policies')) {
                            $directory_policies = Session::get('directory_policies');
                            foreach ($directory_policies as $directory_policy_k => $directory_policy_v) {
                                if ($directory_policy_k == 'public_publish_directory' || $directory_policy_k == 'private_publish_directory' || $directory_policy_k == 'last_activity') {
                                    $rs_to_directory['consent_' . $directory_policy_k] = $directory_policy_v;
                                }
                            }
                        }
                        DB::table('rs_to_directory')->insert($rs_to_directory);
                    }
                    if (Session::has('directory_policies')) {
                        Session::forget('directory_policies');
                    }
                }
                Session::forget('directory_uri');
                if (Session::has('install_picture')) {
                    return redirect()->route('picture');
                }
                Session::put('message_action', $request->input('name') . ' added');
                return redirect()->route('directories');
            } else {
                Session::put('message_action', 'Error: there was a problem with registering with the directory.');
                return redirect()->route('directories');
            }
        }
        if ($type == 'root') {
            $root_url = explode('/', $as_url);
            $root_url1 = explode('.', $root_url[2]);
            $root_domain = 'https://dir.' . $root_url1[1] . '.' . $root_url1[2];
            Session::put('directory_uri', $root_domain);
            $response = $this->directory_api($root_domain, $params);
            if ($response['status'] == 'error') {
                Session::put('message_action', $response['message']);
                return redirect()->route('directories');
            } else {
                return redirect($response['arr']['uri']);
            }
        }
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'uri' => 'required|unique:directories,uri'
            ]);
            $pre_url = rtrim($request->input('uri'), '/');
            if ($ret = parse_url($pre_url)) {
                if (!isset($ret['scheme'])) {
                    $pre_url = 'https://' . $pre_url;
                }
            }
            $response = $this->directory_api($pre_url, $params);
            if ($response['status'] == 'error') {
                return redirect()->back()->withErrors(['uri' => 'The URL provided is not valid.']);
            } else {
                // set Directory policies to each resource
                if (isset($response['arr']['policies'])) {
                    Session::put('directory_policies', $response['arr']['policies']);
                }
                return redirect($response['arr']['uri']);
            }
        } else {
            return view('directory');
            // return redirect()->route('directories');
        }
    }

    public function directory_remove(Request $request, $id, $consent=false)
    {
        $directory = DB::table('directories')->where('id', '=', $id)->first();
        $owner = DB::table('owner')->first();
        $user = DB::table('oauth_users')->where('sub', '=', $owner->sub)->first();
        $client = DB::table('oauth_clients')->where('client_uri', '=', rtrim($directory->uri, '/'))->first();
        $params['client_id'] = '0';
        if ($client) {
            $params['client_id'] = $client->client_id;
            $params['name'] = $user->username;
            DB::table('oauth_clients')->where('client_id', '=', $client->client_id)->delete();
        }
        $url = rtrim($directory->uri, '/');
        $response = $this->directory_api($url, $params, 'directory_remove', $directory->directory_id);
        if ($response['arr']['message'] == 'Directory removed' || $response['arr']['message'] == 'Error: Authorization Server not registered') {
            DB::table('directories')->where('id', '=', $id)->delete();
            $rs_to_directory = DB::table('rs_to_directory')->where('directory_id', '=', $directory->directory_id)->first();
            if ($rs_to_directory) {
                DB::table('rs_to_directory')->where('directory_id', '=', $directory->directory_id)->delete();
            }
            if ($response['arr']['message'] == 'Error: Authorization Server not registered') {
                $response['arr']['message'] .= ', so Directory now removed from Authorization Server';
            }
        }
        Session::put('message_action', $response['arr']['message']);
        if ($consent == false) {
            return redirect()->route('directories');
        } else {
            return redirect()->route('home');
        }
    }

    public function directory_update(Request $request)
    {
        $response = $this->directory_update_api();
        $response1 = '<ul>';
        if (count($response) > 0) {
            foreach ($response as $row) {
                $response1 .= '<li>' . $row . '</li>';
            }
        }
        $response1 .= '</ul>';
        Session::put('message_action', $repsonse1);
        return redirect()->route('directories');
    }

    public function fhir_edit(Request $request)
    {
        $data['username'] = $request->input('username');
        $data['password'] = encrypt($request->input('password'));
        DB::table('fhir_clients')->where('endpoint_uri', '=', $request->input('endpoint_uri'))->update($data);
        return 'Username and password saved';
    }

    public function invite_cancel(Request $request, $code, $redirect=false)
    {
        $owner = DB::table('owner')->first();
        $query = DB::table('invitation')->where('code', '=', $code)->first();
        if ($query) {
            DB::table('invitation')->where('code', '=', $code)->delete();
        }
        if ($redirect == true) {
            Session::put('message_action', 'Invitation for ' . $query->email . ' has been canceled.');
            return redirect()->back();
        } else {
            // $new_data = [
            //     'name' => $owner->org_name,
            //     'text' => '',
            //     'create' => 'yes',
            //     'complete' => 'Your request for a patient container has been canceled.<br>Thank you!'
            // ];
            // return view('patients', $new_data);
            return redirect()->route('authorize_user');
        }
    }

    public function login_authorize(Request $request)
    {
        $query = DB::table('owner')->first();
        $data['name'] = $query->firstname . ' ' . $query->lastname;
        $data['noheader'] = true;
        $scope_array = [
            'profle' => 'View your basic profile',
            'email' => 'View your email address',
            'offline_access' => 'Access offline',
            'uma_authorization' => 'Access resources'
        ];
        $scope_icon = [
            'profile' => 'fa-user',
            'email' => 'fa-envelope',
            'offline_access' => 'fa-share-alt',
            'uma_authorization' => 'fa-key'
        ];
        $client = DB::table('oauth_clients')->where('client_id', '=', Session::get('oauth_client_id'))->first();
        if ($client->logo_uri == '' || $client->logo_uri == null) {
            $data['permissions'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
        } else {
            $data['permissions'] = '<div><img src="' . $client->logo_uri . '" style="margin:20px;text-align: center;"></div>';
        }
        $data['permissions'] .= '<h2>' . $client->client_name . ' would like to:</h2>';
        $data['permissions'] .= '<ul class="list-group">';
        $scopes_array = explode(' ', $client->scope);
        foreach ($scopes_array as $scope) {
            if (array_key_exists($scope, $scope_array)) {
                $data['permissions'] .= '<li class="list-group-item"><i class="fa fa-btn ' . $scope_icon[$scope] . '"></i> ' . $scope_array[$scope] . '</li>';
            }
        }
        $data['permissions'] .= '</ul>';
        // if (Session::get('logo_uri') == '') {
        //     $data['permissions'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
        // } else {
        //     $data['permissions'] = '<div><img src="' . Session::get('logo_uri') . '" style="margin:20px;text-align: center;"></div>';
        // }
        // $data['permissions'] .= '<h2>' . Session::get('client_name') . ' would like to:</h2>';
        // $data['permissions'] .= '<ul class="list-group">';
        // $client = DB::table('oauth_clients')->where('client_id', '=', Session::get('oauth_client_id'))->first();
        // $scopes_array = explode(' ', $client->scope);
        // foreach ($scopes_array as $scope) {
        //     if (array_key_exists($scope, $scope_array)) {
        //         $data['permissions'] .= '<li class="list-group-item"><i class="fa fa-btn ' . $scope_icon[$scope] . '"></i> ' . $scope_array[$scope] . '</li>';
        //     }
        // }
        // $data['permissions'] .= '</ul>';
        return view('login_authorize', $data);
    }

    public function login_authorize_action(Request $request, $type)
    {
        if ($type == 'yes') {
            // Add user to client
            $client = DB::table('oauth_clients')->where('client_id', '=', Session::get('oauth_client_id'))->first();
            $user_array = explode(' ', $client->user_id);
            $user_array[] = Session::get('username');
            $data['user_id'] = implode(' ', $user_array);
            DB::table('oauth_clients')->where('client_id', '=', Session::get('oauth_client_id'))->update($data);
            Session::put('is_authorized', true);
        } else {
            Session::put('is_authorized', false);
        }
        return redirect()->route('authorize');
    }

    public function make_invitation(Request $request)
    {
        $owner = DB::table('owner')->first();
        $data['name'] = Session::get('owner');
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'email' => 'required|unique:users,email',
                'first_name' => 'required',
                'last_name' => 'required',
                'role' => 'required'
            ]);
            // Check if
            $access_lifetime = App::make('oauth2')->getConfig('access_lifetime');
            $code = $this->gen_secret();
            $data1 = [
                'email' => $request->input('email'),
                'expires' => date('Y-m-d H:i:s', time() + $access_lifetime),
                'code' => $code,
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'role' => $request->input('role'),
                'custom_policies' => $request->input('custom_policy')
            ];
            if ($request->has('client_id')) {
                $data1['client_ids'] = implode(',', $request->input('client_id'));
            }
            if ($request->has('policies')) {
                $data1['policies'] = json_encode($request->input('policies'));
            }
            DB::table('invitation')->insert($data1);
            // Send email to invitee
            $url = URL::to('accept_invitation') . '/' . $code;
            $query0 = DB::table('oauth_rp')->where('type', '=', 'google')->first();
            $data2['message_data'] = 'You are invited to the Trustee Authorization Server for ' . $owner->firstname . ' ' . $owner->lastname . '.<br>';
            $data2['message_data'] .= 'Go to ' . $url . ' to get registered.';
            $title = 'Invitation to ' . $owner->firstname . ' ' . $owner->lastname  . "'s Authorization Server";
            $to = $request->input('email');
            $this->send_mail('auth.emails.generic', $data2, $title, $to);
            $data3['name'] = Session::get('owner');
            $data3['title'] = 'Invitation Code';
            $data3['content'] = '<p>Invitation sent to ' . $request->input('first_name') . ' ' . $request->input('last_name') . ' (' . $to . ')</p>';
            $data3['content'] .= '<p>Alternatively, show the recently invited guest your QR code:</p><div style="text-align: center;">';
            $data3['content'] .= QrCode::size(300)->generate($url);
            $data3['content'] .= '</div>';
            return view('home', $data3);
        } else {
            if ($owner->login_direct == 0) {
                $query = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
                if ($query->count()) {
                    $data['rs'] = '<ul class="list-group checked-list-box">';
                    $data['rs'] .= '<li class="list-group-item"><input type="checkbox" id="all_resources" style="margin:10px;"/>All Resources</li>';
                    foreach ($query as $client) {
                        $data['rs'] .= '<li class="list-group-item"><input type="checkbox" name="client_id[]" class="client_ids" value="' . $client->client_id . '" style="margin:10px;"/><img src="' . $client->logo_uri . '" style="max-height: 30px;width: auto;"></img><span style="margin:10px">' . $client->client_name . '</span></li>';
                    }
                    $data['rs'] .= '</ul>';
                }
            }
            $user_policies = $this->user_policies();
            $data['user_policies'] = '<ul class="list-group checked-list-box">';
            foreach ($user_policies as $user_policy) {
                $data['user_policies'] .= '<li class="list-group-item"><input type="checkbox" name="policies[]" value="' . $user_policy['name'] . '" style="margin:10px;"/><span style="margin:10px">' . $user_policy['name'] . '</span></li>';
            }
            $data['user_policies'] .= '</ul>';
            $data['roles'] = $this->roles_build();
            $custom_policies = DB::table('custom_policy')->get();
            $data['custom_policy'] = '<option value="">None</option>';
            if ($custom_policies->count()) {
                foreach ($custom_policies as $custom_policy) {
                    $data['custom_policy'] .= '<option value="' . $custom_policy->name . '"';
                    $data['custom_policy'] .= '>' . $custom_policy->name . '</option>';
                }
            }
            return view('invite', $data);
        }
    }

    public function my_info(Request $request)
    {
        $query = DB::table('oauth_users')->where('username', '=', Session::get('username'))->first();
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['title'] = 'My Information';
        $data['content'] = '<ul class="list-group">';
        $data['content'] .= '<li class="list-group-item"><b>First Name:</b> ' . $query->first_name . '</li>';
        $data['content'] .= '<li class="list-group-item"><b>Last Name:</b> ' . $query->last_name . '</li>';
        $data['content'] .= '<li class="list-group-item"><b>Email:</b> ' . $query->email;
        $data['content'] .= '<span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy my_info" hie-val="' . $query->email . '" title="Copy" style="cursor:pointer;"></i></li>';
        $data['content'] .= '<li class="list-group-item"><b>URL:</b> ' . URL::to('/');
        $data['content'] .= '<span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy my_info" hie-val="' . URL::to('/') . '" title="Copy" style="cursor:pointer;"></i></li>';
        $owner_query = DB::table('owner')->first();
        if ($owner_query->sub == $query->sub) {
            $data['content'] .= '<li class="list-group-item"><b>Date of Birth:</b> ' . date('m/d/Y', strtotime($owner_query->DOB)) . '</li>';
            $data['content'] .= '<li class="list-group-item"><b>Mobile Number:</b> ' . $owner_query->mobile . '</li>';
            if (!empty(env('SYNCTHING_HOST'))) {
                $status = $this->syncthing_api('status');
                $status_arr = json_decode($status, true);
                $data['content'] .= '<li class="list-group-item"><b>Syncthing Device ID:</b> ' . $status_arr['myID'];
                $data['content'] .= '<span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy my_info" hie-val="' . $status_arr['myID'] . '" title="Copy" style="cursor:pointer;"></i></li>';
            }
        }
        if (!empty($query->npi)) {
            $data['content'] .= '<li class="list-group-item"><b>NPI:</b> ' . $query->npi . '</li>';
        }
        if (!empty($query->picture)) {
            $img_src = asset(str_replace(storage_path('app/public'), 'storage', $query->picture));
            $data['content'] .= '<li class="list-group-item"><b>My Photo</b><br><img src="' . $img_src . '" style="width: 100% !important;height: auto !important;"><br><br><a href="' . route('picture') . '" class="btn btn-primary">Change</a></li>';
            Session::put('my_info', 'y');
        }
        $data['content'] .= '</ul>';
        $data['back'] = '<a href="' . route('my_info_edit') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-pencil"></i> Edit</a>';
        return view('home', $data);
    }

    public function my_info_edit(Request $request)
    {
        $message = '';
        $owner_query = DB::table('owner')->first();
        $query = DB::table('oauth_users')->where('username', '=', Session::get('username'))->first();
        if ($request->isMethod('post')) {
            if ($owner_query->sub == $query->sub) {
                $this->validate($request, [
                    'email' => 'required',
                    'first_name' => 'required',
                    'last_name' => 'required',
                    'date_of_birth' => 'required'
                ]);
            } else {
                $this->validate($request, [
                    'email' => 'required',
                    'first_name' => 'required',
                    'last_name' => 'required'
                ]);
            }
            $data1 = [
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'email' => $request->input('email')
            ];
            DB::table('oauth_users')->where('username', '=', Session::get('username'))->update($data1);
            $data2['email'] = $request->input('email');
            DB::table('users')->where('name', '=', Session::get('username'))->update($data2);
            if ($owner_query->sub == $query->sub) {
                $owner_data = [
                    'lastname' => $request->input('last_name'),
                    'firstname' => $request->input('first_name'),
                    'DOB' => date('Y-m-d', strtotime($request->input('date_of_birth'))),
                    'email' => $request->input('email'),
                    'mobile' => $request->input('mobile')
                ];
                DB::table('owner')->where('id', '=', '1')->update($owner_data);
                $pnosh = DB::table('oauth_clients')->where('client_name', 'LIKE', "%Patient NOSH for%")->first();
                if ($pnosh) {
                    // Synchronize contact info with pNOSH
                    $url = $pnosh->client_uri . '/as_sync';
                    $ch = curl_init();
                    $sync_data = [
                        'old_email' => $owner_query->email,
                        'client_id' => $pnosh->client_id,
                        'client_secret' => $pnosh->client_secret,
                        'email' => $request->input('email'),
                        'sms' => $request->input('mobile'),
                        'lastname' => $request->input('last_name'),
                        'firstname' => $request->input('first_name'),
                        'DOB' => date('Y-m-d H:i:s', strtotime($request->input('date_of_birth')))
                    ];
                    $post = http_build_query($sync_data);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0);
                    $result = curl_exec($ch);
                    $message = '<br>' . $result;
                    $pnosh_update['client_name'] = 'Patient NOSH for ' .  $request->input('first_name') . ' ' . $request->input('last_name');
                    DB::table('oauth_clients')->where('client_id', '=', $pnosh->client_id)->update($pnosh_update);
                    $response = $this->directory_update_api();
                    if (count($response) > 0) {
                        $message .= '<ul>';
                        foreach ($response as $row) {
                            $message .= '<li>' . $row . '</li>';
                        }
                        $message .= '</ul>';
                    }
                }
                Session::put('owner', $request->input('first_name') . ' ' . $request->input('last_name'));
            }
            Session::put('message_action', 'Information Updated.' . $message);
            Session::put('full_name', $request->input('first_name') . ' ' . $request->input('last_name'));
            Session::put('email', $request->input('email'));
            return redirect()->route('my_info');
        } else {
            $data = [
                'first_name' => $query->first_name,
                'last_name' => $query->last_name,
                'email' => $query->email
            ];

            if ($owner_query->sub == $query->sub) {
                $data['date_of_birth'] = date('Y-m-d', strtotime($owner_query->DOB));
                $data['mobile'] = $owner_query->mobile;
            }
            return view('edit', $data);
        }
    }

    public function policy_user_add(Request $request, $policy_id)
    {
        $claim = DB::table('claim')->where('claim_value', '=', $request->input('user'))->first();
        $user = DB::table('oauth_users')->where('email', '=', $request->input('user'))->first();
        $policy = DB::table('policy')->where('policy_id', '=', $policy_id)->first();
        if (!$claim) {
            $claim_data = [
                'name' => 'email',
                'claim_value' => $request->input('user')
            ];
            $claim_id = DB::table('claim')->insertGetId($claim_data);
        } else {
            $claim_id = $claim->claim_id;
        }
        $query = DB::table('claim_to_policy')->where('claim_id', '=', $claim_id)->where('policy_id', '=', $policy_id)->first();
        if (!$query) {
            $data = [
                'claim_id' => $claim_id,
                'policy_id' => $policy_id
            ];
            DB::table('claim_to_policy')->insert($data);
            Session::put('message_action', $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ') was added to the ' . $policy->name . ' policy.');
        } else {
            Session::put('message_action', $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ') already added to the ' . $policy->name . ' policy!');
        }
        return redirect()->back();
    }

    public function policy_user_remove(Request $request, $claim_id, $policy_id)
    {
        $claim = DB::table('claim')->where('claim_id', '=', $claim_id)->first();
        $user = DB::table('oauth_users')->where($claim->name, '=', $claim->claim_value)->first();
        $policy = DB::table('policy')->where('policy_id', '=', $policy_id)->first();
        DB::table('claim_to_policy')->where('claim_id', '=', $claim_id)->where('policy_id', '=', $policy_id)->delete();
        Session::put('message_action', $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ') was removed from the ' . $policy->name . ' policy.');
        return redirect()->back();
    }

    public function proxy_add(Request $request, $sub)
    {
        $query = DB::table('oauth_users')->where('sub', '=', $sub)->first();
        $data = [
            'lastname' => $query->last_name,
            'firstname' => $query->first_name,
            'sub' => $sub
        ];
        DB::table('owner')->insert($data);
        Session::put('message_action', 'You just added ' . $query->first_name . ' ' . $query->last_name . ' as a proxy for you');
        return redirect()->route('users');
    }

    public function proxy_remove(Request $request, $sub)
    {
        $owner = DB::table('owner')->first();
        $query = DB::table('oauth_users')->where('sub', '=', $sub)->first();
        if ($sub !== $owner->sub) {
            DB::table('owner')->where('sub', '=', $sub)->delete();
            Session::put('message_action', 'You just removed ' . $query->first_name . ' ' . $query->last_name . ' as a proxy for you');
        } else {
            Session::put('message_action', 'You cannot remove yourself as the owner.');
        }
        return redirect()->route('users');
    }

    public function resend_invitation(Request $request, $id)
    {
        $owner = DB::table('owner')->first();
        $invite = DB::table('invitation')->where('id', '=', $id)->first();
        $url = URL::to('accept_invitation') . '/' . $invite->code;
        $access_lifetime = App::make('oauth2')->getConfig('access_lifetime');
        $data['expires'] = date('Y-m-d H:i:s', time() + $access_lifetime);
        DB::table('invitation')->where('id', '=', $id)->update($data);
        $data2['message_data'] = 'You are invited to the Trustee Authorization Server for ' . $owner->firstname . ' ' . $owner->lastname . '.<br>';
        $data2['message_data'] .= 'Go to ' . $url . ' to get registered.';
        $title = 'Invitation to ' . $owner->firstname . ' ' . $owner->lastname  . "'s Authorization Server";
        $to = $request->input('email');
        $this->send_mail('auth.emails.generic', $data2, $title, $to);
        Session::put('message_action', 'Invitation email resent');
        return redirect()->back();
    }

    public function resources(Request $request, $id)
    {
        $data['name'] = Session::get('owner');
        $client = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
        $data['title'] = 'My Resources from ' . $client->client_name;
        $data['content'] = 'No resources registered yet.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $default_policy_types = $this->default_policy_type();
        $fhir_resources = $this->fhir_resources();
        $data['back'] = '<a href="' . route('resource_servers') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resource Servers</a>';
        $query = DB::table('resource_set')->where('client_id', '=', $id)->get();
        if ($query->count()) {
            $count1 = 0;
            foreach ($default_policy_types as $default_policy_type) {
                $consent = 'consent_' . $default_policy_type;
                if ($client->{$consent} == 1) {
                    $count1++;
                }
            }
            $count_label1 = 'policies';
            if ($count1 == 1) {
                $count_label1 = 'policy';
            }
            $data['content'] = '<a href ="' . route('consents_resource_server') . '" class="btn btn-primary" role="button" style="margin:15px"><span style="margin:20px;">Default Group Policies</span><span class="badge">' . $count1 . ' ' . $count_label1 . '</span></a>';
            $data['content'] .= '<div class="list-group">';
            foreach ($query as $resource) {
                $query1 = DB::table("policy")->where('resource_set_id', '=', $resource->resource_set_id)->get();
                $count = $query1->count();
                $count_label = 'individual policies';
                if ($count == 1) {
                    $count_label = 'individual policy';
                }
                $icon = '';
                if (!empty($resource->icon_uri)) {
                    $icon = '<img src="' . $resource->icon_uri . '" height="30" width="30">';
                }
                $scopes = DB::table('resource_set_scopes')->where('resource_set_id', '=', $resource->resource_set_id)->get();
                if ($scopes->count()) {
                    foreach ($scopes as $scope) {
                        if (filter_var($scope->scope, FILTER_VALIDATE_URL)) {
                            $url_arr = parse_url($scope->scope);
                            $path_arr = explode('/', $url_arr['path']);
                            foreach ($path_arr as $path_row) {
                                if (isset($fhir_resources[$path_row])) {
                                    $icon = '<i class="fa ' . $fhir_resources[$path_row]['icon'] . ' fa-fw fa-2x"></i>';
                                }
                            }
    }
                    }
                }
                $data['content'] .= '<a href="' . route('resource_view', [$resource->resource_set_id ]) . '" class="list-group-item">' . $icon . '<span style="margin:10px;">' . $resource->name . '</span><span class="badge">' . $count . ' ' . $count_label . '</span></a>';
            }
            $data['content'] .= '</div>';
        }
        Session::put('back', $request->fullUrl());
        Session::put('current_client_id', $id);
        return view('home', $data);
    }

    public function resource_servers(Request $request)
    {
        if (Session::get('is_owner') == 'no') {
            return redirect()->route('welcome');
        }
        $data['name'] = Session::get('owner');
        $data['title'] = 'My Resource Servers';
        $data['blockchain_count'] = '0';
        $data['blockchain_table'] = 'None';
        $data['message_action'] = Session::get('message_action');
        $data['back'] = '<div class="btn-group">';
        $data['back'] .= '<button type="button" class="btn btn-sm dropdown-toggle" data-toggle="dropdown"><i class="fa fa-plus fa-fw fa-btn"></i><span class="caret"></span></button>';
        $data['back'] .= '<ul class="dropdown-menu dropdown-menu-right">';
        $data['back'] .= '<li><a href="' .  url('/') . '/nosh/fhir_connect/list/as" target="_blank"><i class="fa fa-plus fa-fw fa-btn"></i>Connect to Hospital</a></li>';
        $data['back'] .= '<li><a href="' .  url('/') . '/nosh/cms_bluebutton/as" target="_blank"><i class="fa fa-plus fa-fw fa-btn"></i>Connect to Medicare</a></li>';
        $data['back'] .= '</ul></div>';
        Session::forget('message_action');
        $data = array_merge($data, $this->resources_list());
        return view('home', $data);
    }

    public function resource_view(Request $request, $id)
    {
        if (!Session::has('current_client_id')) {
            return redirect()->route('home');
        }
        $data['name'] = Session::get('owner');
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $uma_scope_array = [
            'view' => 'View',
            'edit' => 'Edit'
        ];
        $default_policy_type = $this->default_policy_type();
        $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
        $data['title'] = 'Policies for ' . $query->name;
        $data['content'] = 'No policies registered for this resource.';
        $data['back'] = '<a href="' . route('resources', [Session::get('current_client_id')]) . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resources</a>';
        $query1 = DB::table("policy")->where('resource_set_id', '=', $id)->get();
        if ($query1->count()) {
            $data['content'] = '<ul class="list-group">';
            foreach ($query1 as $policy) {
                // Label
                $data['content'] .= '<li class="list-group-item container-fluid"><span><b>' . $policy->name . '</b>';
                // Get claim
                $claim_to_policies = DB::table('claim_to_policy')->where('policy_id', '=', $policy->policy_id)->get();
                $policy_scopes = DB::table('policy_scopes')->where('policy_id', '=', $policy->policy_id)->get();
                // Info
                $data['content'] .= '<br><br><span style="margin-left:30px;"><b>Users:</b></span>';
                foreach ($claim_to_policies as $claim_to_policy) {
                    $claim = DB::table('claim')->where('claim_id', '=', $claim_to_policy->claim_id)->first();
                    if (!in_array($claim->claim_value, $default_policy_type)) {
                        $user = DB::table('oauth_users')->where($claim->name, '=', $claim->claim_value)->first();
                        $data['content'] .= '<br><span style="margin-left:30px;margin-right:10px">' . $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ')' . '</span>';
                        $data['content'] .= '<a href="' . route('policy_user_remove', [$claim_to_policy->claim_id, $policy->policy_id]) . '"><i class="fa fa-times fa-lg" style="color:red;"></i></a>';
                    }
                }
                $data['content'] .= '<br><br><form action="' . route('policy_user_add', [$policy->policy_id]) . '" method="POST" class="form-inline" style="margin-left:30px;">' . csrf_field() . '<div class="form-group mb-2"><select class="form-control input-sm" style="width:200px;margin-right:10px" name="user">' . $this->users_build() . '</select></div>';
                $data['content'] .= '<button type="submit" class="btn btn-primary" style="margin-left:10px">Add User</button></form>';
                $data['content'] .= '<br><br><span style="margin-left:30px;"><b>Permissions:</b></span>';
                foreach ($policy_scopes as $scope) {
                    if (isset($uma_scope_array[$scope->scope])) {
                        $data['content'] .= '<br><span style="margin-left:30px;">' . $uma_scope_array[$scope->scope] . '</span>';
                    }
                }
                // Actions
                $data['content'] .= '<span class="pull-right">';
                // link to edit policy
                $data['content'] .= '</span></li>';
            }
            $data['content'] .= '</ul>';
        }
        return view('home', $data);
    }

    public function setup_mail(Request $request)
    {
        $query = DB::table('owner')->first();
        if (Session::get('is_owner') == 'yes' || $query == false || Session::get('install') == 'yes' || env('DOCKER') == '0') {
            if ($request->isMethod('post')) {
                $this->validate($request, [
                    'mail_type' => 'required'
                ]);
                $mail_arr = [
                    'gmail' => [
                        'MAIL_DRIVER' => 'smtp',
                        'MAIL_HOST' => 'smtp.gmail.com',
                        'MAIL_PORT' => 465,
                        'MAIL_ENCRYPTION' => 'ssl',
                        'MAIL_USERNAME' => $request->input('mail_username'),
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => $request->input('google_client_id'),
                        'GOOGLE_SECRET' => $request->input('google_client_secret'),
                        'GOOGLE_REDIRECT_URI' => URL::to('account/google')
                    ],
                    'mailgun' => [
                        'MAIL_DRIVER' => 'mailgun',
                        'MAILGUN_DOMAIN' => $request->input('mailgun_domain'),
                        'MAILGUN_SECRET' => $request->input('mailgun_secret'),
                        'MAIL_HOST' => '',
                        'MAIL_PORT' => '',
                        'MAIL_ENCRYPTION' => '',
                        'MAIL_USERNAME' => '',
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                    'sparkpost' => [
                        'MAIL_DRIVER' => 'sparkpost',
                        'SPARKPOST_SECRET' => $request->input('sparkpost_secret'),
                        'MAIL_HOST' => '',
                        'MAIL_PORT' => '',
                        'MAIL_ENCRYPTION' => '',
                        'MAIL_USERNAME' => '',
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                    'ses' => [
                        'MAIL_DRIVER' => 'ses',
                        'SES_KEY' => $request->input('ses_key'),
                        'SES_SECRET' => $request->input('ses_secret'),
                        'MAIL_HOST' => '',
                        'MAIL_PORT' => '',
                        'MAIL_ENCRYPTION' => '',
                        'MAIL_USERNAME' => '',
                        'MAIL_PASSWORD' => '',
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ],
                    'unique' => [
                        'MAIL_DRIVER' => 'smtp',
                        'MAIL_HOST' => $request->input('mail_host'),
                        'MAIL_PORT' => $request->input('mail_port'),
                        'MAIL_ENCRYPTION' => $request->input('mail_encryption'),
                        'MAIL_USERNAME' => $request->input('mail_username'),
                        'MAIL_PASSWORD' => $request->input('mail_password'),
                        'GOOGLE_KEY' => '',
                        'GOOGLE_SECRET' => '',
                        'GOOGLE_REDIRECT_URI' => ''
                    ]
                ];
                $this->changeEnv($mail_arr[$request->input('mail_type')]);
                if ($request->input('mail_type') == 'gmail') {
                    $google_data = [
                        'type' => 'google',
                        'client_id' => $request->input('google_client_id'),
                        'client_secret' => $request->input('google_client_secret'),
                        'redirect_uri' => URL::to('account/google'),
                        'smtp_username' => $request->input('mail_username')
                    ];
                    DB::table('oauth_rp')->insert($google_data);
                    return redirect()->route('installgoogle');
                } else {
                    return redirect()->route('setup_mail_test');
                }
            } else {
                $data2['noheader'] = true;
                $data2['mail_type'] = '';
                $data2['mail_host'] = env('MAIL_HOST');
                $data2['mail_port'] = env('MAIL_PORT');
                $data2['mail_encryption'] = env('MAIL_ENCRYPTION');
                $data2['mail_username'] = env('MAIL_USERNAME');
                $data2['mail_password'] = env('MAIL_PASSWORD');
                $data2['google_client_id'] = env('GOOGLE_KEY');
                $data2['google_client_secret'] = env('GOOGLE_SECRET');
                $data2['mail_username'] = env('MAIL_USERNAME');
                $data2['mailgun_domain'] = env('MAILGUN_DOMAIN');
                $data2['mailgun_secret'] = env('MAILGUN_SECRET');
                $data2['mail_type'] == 'sparkpost';
                $data2['sparkpost_secret'] = env('SPARKPOST_SECRET');
                $data2['ses_key'] = env('SES_KEY');
                $data2['ses_secret'] = env('SES_SECRET');
                if (env('MAIL_DRIVER') == 'smtp') {
                    if (env('MAIL_HOST') == 'smtp.gmail.com') {
                        $data2['mail_type'] = 'gmail';
                    } else {
                        $data2['mail_type'] = 'unique';
                    }
                } else {
                    $data2['mail_type'] = env('MAIL_DRIVER');
                }
                $data2['message_action'] = Session::get('message_action');
                Session::forget('message_action');
                return view('setup_mail', $data2);
            }
        } else {
            return redirect()->route('home');
        }
    }

    public function setup_mail_test(Request $request)
    {
        $data_message['message_data'] = 'This is a test';
        $query = DB::table('owner')->first();
        $message_action = 'Check to see in your registered e-mail account if you have recieved it.  If not, please come back to the E-mail Service page and try again.';
        try {
            $this->send_mail('auth.emails.generic', $data_message, 'Test E-mail', $query->email);
        } catch(\Exception $e){
            $message_action = 'Error - There is an error in your configuration.  Please try again.';
            Session::put('message_action', $message_action);
            return redirect()->route('setup_mail');
        }
        Session::put('message_action', $message_action);
        return redirect()->route('home');
    }

    public function syncthing(Request $request)
    {
        $config = $this->syncthing_api('config_get');
        if ($config) {
            $arr = json_decode($config, true);
            $status = $this->syncthing_api('status');
            if ($status) {
                $status_arr = json_decode($status, true);
            }
        } else {
            $arr['error'] = 'Connection problem';
        }
        $data['name'] = Session::get('owner');
        $data['title'] = 'Connected Backups';
        $data['content'] = '<div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        $data['content'] .= '<p>Below are the list of servers that hold your backups for the authorization server and NOSH</p>';
        $data['content'] .= '</div>';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        if (!isset($arr['error'])) {
            $data['content'] .= '<ul class="list-group">';
            $i = 0;
            foreach ($arr['devices'] as $device) {
                if ($device['deviceID'] !== $status_arr['myID']) {
                    // Label
                    $logo = '<div style="display:inline-block;float:none;margin-right:10px;"><i class="fa fa-fw fa-lg fa-exchange" style="height:30px;width:30px;color#000000;"></i></div>';
                    $data['content'] .= '<li class="list-group-item container-fluid"><span>' . $logo . '<b>' . $device['name'] . '</b>';
                    // Info
                    $data['content'] .= '<br><span style="margin-left:30px;"><b>ID: </b>' . $device['deviceID'] . '</span>';
                    // Actions
                    $data['content'] .= '<span class="pull-right">';
                    $data['content'] .= '<a href="' . route('syncthing_remove', [$i]) . '" class="btn fa-btn uma-delete" data-toggle="tooltip" title="Remove"><i class="fa fa-times fa-lg" style="color:red"></i></a>';
                    $data['content'] .= '</span></li>';
                }
                $i++;
            }
            $data['content'] .= '</ul>';
        } else {
            $data['content'] .= $arr['error'];
        }
        $data['back'] = '<a href="' . route('syncthing_add') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-plus"></i> Add Backup</a>';
        return view('home', $data);
    }

    public function syncthing_add(Request $request)
    {
        if (!empty(env('SYNCTHING_HOST'))) {
            $data['name'] = Session::get('owner');
            $data['title'] = 'Add Trustee Mouse';
            if ($request->isMethod('post')) {
                $this->validate($request, [
                    'deviceID' => 'required'
                ]);
                $config = $this->syncthing_api('config_get');
                if ($config) {
                    $arr = json_decode($config, true);
                    $new_device = [
                        'deviceID' => $request->input('deviceID'),
                        'name' => $request->input('name'),
                        'addresses' => [
                            'dynamic'
                        ],
                        'compression' => 'metadata',
                        'certName' => '',
                        'introducer' => false,
                        'skipIntroductionRemovals' => false,
                        'introducedBy' => '',
                        'paused' => false,
                        'allowedNetworks' => [],
                        'autoAcceptFolders' => false,
                        'maxSendKbps' => 0,
                        'maxRecvKbps' => 0,
                        'ignoredFolders' => [],
                        'pendingFolders' => [],
                        'maxRequestKiB' => 0
                    ];
                    $arr['devices'][] = $new_device;
                    $new_device_folder = [
                        'deviceID' => $request->input('deviceID'),
                        'introducedBy' => ''
                    ];
                    $arr['folders'][0]['devices'][] = $new_device_folder;
                    $arr['folders'][0]['versioning']['params'] = (object) null;
                    $post_body = json_encode($arr);
                    $set = $this->syncthing_api('config_set', $post_body);
                }
                return redirect()->route('syncthing');
            } else {
                $data['deviceID'] = $request->input('deviceID');
                $data['name'] = $request->input('name');
                return view('syncthing', $data);
            }
        } else {
            return redirect()->route('welcome');
        }
    }

    public function syncthing_remove(Request $request, $id)
    {
        if (!empty(env('SYNCTHING_HOST'))) {
            $config = $this->syncthing_api('config_get');
            if ($config) {
                $arr = json_decode($config, true);
                $deviceID = $arr['devices'][$id]['deviceID'];
                foreach ($arr['folders']['devices'] as $row_k => $row_v) {
                    if ($row_v['deviceID'] == $deviceID) {
                        unset($arr['folders']['devices'][$row_k]);
                    }
                }
                unset($arr['devices'][$id]);
                $arr['folders'][0]['versioning']['params'] = (object) null;
                $post_body = json_encode($arr);
                $set = $this->syncthing_api('config_set', $post_body);
            }
            return redirect()->route('syncthing');
        } else {
            return redirect()->route('welcome');
        }
    }

    public function users(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Authorized Users';
        $data['content'] = 'No authorized users.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data = array_merge($data, $this->users_list());
        return view('home', $data);
    }





    public function rs_authorize_action(Request $request, $type='')
    {
        $client = DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->first();
        if ($request->input('submit') == 'allow') {
            $default_policy_types = $this->default_policy_type();
            $types = [];
            foreach ($default_policy_types as $default_policy_type) {
                $consent = 'consent_' . $default_policy_type;
                $data[$consent] = 0;
                if ($request->input($consent) == 'on') {
                    $data[$consent] = 1;
                    $types[] = $default_policy_type;
                }
            }
            $data['authorized'] = 1;
            DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data);
            $this->group_policy($request->input('client_id'), $types, 'update');
            if (Session::get('oauth_response_type') == 'code') {
                $user_array = explode(' ', $client->user_id);
                $user_array[] = Session::get('username');
                $data['user_id'] = implode(' ', $user_array);
                DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data);
                Session::put('is_authorized', 'true');
            }
            if ($type == '') {
                Session::put('message_action', 'You just authorized a resource server named ' . $client->client_name);
            } else {
                Session::put('message_action', 'You updated a resource server named ' . $client->client_name);
            }
        } elseif ($request->input('submit') == 'deny') {
            if ($type == '') {
                Session::put('message_action', 'You just unauthorized a resource server named ' . $client->client_name);
                if (Session::get('oauth_response_type') == 'code') {
                    Session::put('is_authorized', 'false');
                } else {
                    $data1['authorized'] = 0;
                    DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data1);
                    $this->group_policy($request->input('client_id'), $types, 'delete');
                }
            } else {
                $route = Session::get('back');
                Session::forget('back');
                return redirect($route);
            }
        } else {
            $data2['authorized'] = 0;
            DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data2);
            $this->group_policy($request->input('client_id'), $types, 'delete');
        }
        if (Session::get('oauth_response_type') == 'code') {
            return redirect()->route('authorize');
        } else {
            if ($type == '') {
                return redirect()->route('resources', ['id' => Session::get('current_client_id')]);
            } else {
                $route = Session::get('back');
                Session::forget('back');
                return redirect($route);
            }
        }
    }

    public function change_permission(Request $request, $id, $action, $scope)
    {
        if ($action == 'add') {
            $data = [
                'policy_id' => $id,
                'scope' => $scope
            ];
            $message_action = 'Added permission to ' . $scope . ' resource';
            DB::table('policy_scopes')->insert($data);
        }
        if ($action == 'remove') {
            DB::table('policy_scopes')->where('policy_id', '=', $id)->where('scope', '=', $scope)->delete();
        }
        Session::put('message_action', $message_action);
        return redirect()->back();
    }

    public function change_permission_old(Request $request, $id)
    {
        $data['name'] = Session::get('owner');
        $uma_scope_array = [
            'view' => 'view',
            'edit' => 'edit'
        ];
        $query = DB::table("policy")->where('policy_id', '=', $id)->first();
        $query1 = DB::table('resource_set')->where('resource_set_id', '=', $query->resource_set_id)->first();
        $query2 = DB::table('claim_to_policy')->where('policy_id', '=', $id)->first();
        if ($query2) {
            $query3 = DB::table('claim')->where('claim_id', '=', $query2->claim_id)->first();
            $query4 = DB::table('oauth_users')->where('email', '=', $query3->claim_value)->first();
            if ($query4) {
                $user = $query4->first_name . ' ' . $query4->last_name . ' (' . $query3->claim_value . ')';
            } else {
                $user = $query3->claim_value;
            }
            $query4 = DB::table('policy_scopes')->where('policy_id', '=', $id)->get();
            $permissions = '';
            $i = 0;
            foreach ($query4 as $scope) {
                if (array_key_exists($scope->scope, $uma_scope_array)) {
                    if ($i > 0) {
                        $permissions .= ' and ';
                    }
                    $permissions .= $uma_scope_array[$scope->scope];
                    $i++;
                }
            }
        }
        $data['title'] = 'Change Permissions';
        if ($permissions == '') {
            $data['content'] = '<div class="col-md-6 col-md-offset-3"><p>' . $user . ' is currently NOT allowed to view or edit ' . $query1->name . '</p>';
            $data['content'] .=  '<a href="' . URL::to('change_permission_add_view') . '/' . $id . '" class="btn btn-success btn-block" role="button">Add Permission to View</a>';
            $data['content'] .=  '<a href="' . URL::to('change_permission_add_edit') . '/' . $id . '" class="btn btn-success btn-block" role="button">Add Permission to View and Edit</a>';
        } else {
            $data['content'] = '<div class="col-md-6 col-md-offset-3"><p>' . $user . ' is currently allowed to ' . $permissions . ' ' . $query1->name . '</p>';
            if ($permissions == 'view') {
                $data['content'] .=  '<a href="' . URL::to('change_permission_add_edit') . '/' . $id . '" class="btn btn-success btn-block" role="button">Add Permission to Edit</a>';
            } else {
                $data['content'] .=  '<a href="' . URL::to('change_permission_remove_edit') . '/' . $id . '" class="btn btn-warning btn-block" role="button">Remove Permission to Edit</a>';
            }
        }
        $data['content'] .=  '<a href="' . URL::to('change_permission_delete') . '/' . $id . '" class="btn btn-danger btn-block" role="button" id="remove_permissions_button">Remove All Permissions</a>';
        $data['content'] .=  '<a href="' . URL::to('resource_view') . '/' . $query->resource_set_id . '" class="btn btn-primary btn-block" role="button">Go Back</a></div>';
        return view('home', $data);
    }

    public function change_permission_add_edit(Request $request, $id)
    {
        $data = [
            'policy_id' => $id,
            'scope' => 'edit'
        ];
        DB::table('policy_scopes')->insert($data);
        $query = DB::table("policy")->where('policy_id', '=', $id)->first();
        $url = URL::to('resource_view') . '/' . $query->resource_set_id;
        Session::put('message_action', 'Added permission to edit resource');
        return redirect($url);
    }

    public function change_permission_remove_edit(Request $request, $id)
    {
        DB::table('policy_scopes')->where('policy_id', '=', $id)->where('scope', '=', 'edit')->delete();
        $query = DB::table("policy")->where('policy_id', '=', $id)->first();
        $url = URL::to('resource_view') . '/' . $query->resource_set_id;
        Session::put('message_action', 'Removed permission to edit resource');
        return redirect($url);
    }

    public function change_permission_delete(Request $request, $id)
    {
        DB::table('policy_scopes')->where('policy_id', '=', $id)->delete();
        DB::table('claim_to_policy')->where('policy_id', '=', $id)->delete();
        $query = DB::table("policy")->where('policy_id', '=', $id)->first();
        $url = URL::to('resource_view') . '/' . $query->resource_set_id;
        DB::table('policy')->where('policy_id', '=', $id)->delete();
        Session::put('message_action', 'Removed all permissions to access the resource');
        return redirect($url);
    }

    public function consent_table(Request $request)
    {
        if (Session::get('is_owner') == 'no') {
            return redirect()->route('welcome');
        }
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $this->default_user_policies_create();
        $query = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
        $policy_labels = [
            'public_publish_directory' => [
                'label' => 'UMA-Direct',
                'info' => 'Any party that has access to a Directory that you participate in can see where this resource is found.'
            ],
            'last_activity' => [
                'label' => 'Show Last<br>Activity',
                'info' => 'Timestap of the most recent activity of this resource server, published to the Directory.'
            ],
            // 'private_publish_directory' => [
            //     'label' => 'Private<br>in Directory',
            //     'info' => 'Only previously authorized users that has access to a Directory that you participate in can see where this resource is found.'
            // ],
            // 'any_npi' => [
            //     'label' => 'Verfied<br>Clnicians<br>Only',
            //     'info' => 'By setting this as a default, you allow any healthcare provider with a National Provider Identifier (NPI), known or unknown at any given time to access and edit your protected health information.'
            // ],
            // 'ask_me' => [
            //     'label' => 'Ask<br>Me',
            //     'info' => 'Ask Me'
            // ],
            // 'root_support' => [
            //     'label' => 'Root<br>Support',
            //     'info' => 'Support from your Trustee Directory in case you have difficulties with your authorization server.'
            // ],
            // 'patient_user' => [
            //     'label' => 'Patient',
            //     'info' => 'It must be you!'
            // ],
        ];
        $policy_arr = [];
        $smart_on_fhir = [];
        $pnosh = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->where('client_name', 'LIKE', "%Patient NOSH for%")->first();
        if ($pnosh) {
            $pnosh_url = $pnosh->client_uri;
            $url = $pnosh->client_uri . '/smart_on_fhir_list';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
            $smart_result = curl_exec($ch);
            curl_close ($ch);
            $smart_on_fhir = json_decode($smart_result, true);
            // $url1 = $pnosh_url . '/transactions';
            // $ch1 = curl_init();
            // curl_setopt($ch1,CURLOPT_URL, $url1);
            // curl_setopt($ch1,CURLOPT_FAILONERROR,1);
            // curl_setopt($ch1,CURLOPT_FOLLOWLOCATION,1);
            // curl_setopt($ch1,CURLOPT_RETURNTRANSFER,1);
            // curl_setopt($ch1,CURLOPT_TIMEOUT, 60);
            // curl_setopt($ch1,CURLOPT_CONNECTTIMEOUT ,0);
            // $blockchain = curl_exec($ch1);
            // $httpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
            // curl_close ($ch1);
            // if ($httpCode !== 404 && $httpCode !== 0) {
            //     $blockchain_arr = json_decode($blockchain, true);
            //     $data['blockchain_count'] = $blockchain_arr['count'];
            //     if ($blockchain_arr['count'] !== 0) {
            //         $data['blockchain_table'] = '<table class="table table-striped"><thead><tr><th>Date</th><th>Provider</th><th>Transaction Receipt</th></thead><tbody>';
            //         foreach ($blockchain_arr['transactions'] as $blockchain_row) {
            //             $data['blockchain_table'] .= '<tr><td>' . date('Y-m-d', $blockchain_row['date']) . '</td><td>' . $blockchain_row['provider'] . '</td><td><a href="https://rinkeby.etherscan.io/tx/' . $blockchain_row['transaction'] . '" target="_blank">' . $blockchain_row['transaction'] . '</a></td></tr>';
            //         }
            //         $data['blockchain_table'] .= '</tbody></table>';
            //         $data['blockchain_table'] .= '<strong>Top 5 Provider Users</strong>';
            //         $data['blockchain_table'] .= '<table class="table table-striped"><thead><tr><th>Provider</th><th>Number of Transactions</th></thead><tbody>';
            //         foreach ($blockchain_arr['providers'] as $blockchain_row1) {
            //             $data['blockchain_table'] .= '<tr><td>' . $blockchain_row1['provider'] . '</td><td>' . $blockchain_row1['count'] . '</td></tr>';
            //         }
            //         $data['blockchain_table'] .= '</tbody></table>';
            //     }
            // }
        }
        $directories = DB::table('directories')->get();
        $data['title'] = 'Consent Table';
        $data['content'] = '<div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        $data['content'] .= '<p>Click on a <i class="fa fa-check fa-lg" style="color:green;"></i> or <i class="fa fa-times fa-lg" style="color:red;"></i> to change the policy.  Click on a <strong>policy name</strong> for for information about the policy.</p>';
        $data['content'] .= '<p>If you are using a smartphone or narrow screen, scroll to the left to see the entire consent table</p>';
        // $data['content'] .= '<br><img src="https://avatars3.githubusercontent.com/u/7401080?v=4&s=200" style="max-height: 30px;width: auto;"> designates a SMART-on-FHIR resource which has the following limitations:<ul>';
        // $data['content'] .= '<li><strong>No Refresh Tokens</strong></li><li><strong>No Dynamic Client Registration</strong></li><li><strong>and No User-Managed Access - therefore you cannot change access polices for this type of resource</strong></li>,';
        $data['content'] .= '</div>';
        $data['content'] .= '<div class="table-responsive"><table class="table table-striped table-fixed">';
        $main_header_html = '<thead style="background-color: #eaeaea;"><tr><th style="color:blue;"><strong>Consent Category</strong></th><th style="text-align:center;" class="as-info" as-info="Setting this will notify of any access by an authorized user of this resource"><strong>Ping<br>Me</strong></th><th style="text-align:center;"><strong>Role</strong></th><th style="text-align:center;" colspan="main_header_colspan"><strong>Policies</strong></th></tr></thead>';
        $data['content'] .= $main_header_html;
        $data['content'] .= '<tbody><tr><th></th><th></th><th></th>';
        if ($directories) {
            foreach ($directories as $directory) {
                $data['content'] .= '<th colspan="2">Directory - ' . $directory->name . '</th>';
            }
        }
        $data['content'] .= '<th colspan="directory_header_colspan"></th></tr>';
        $data['content'] .= '<tr><th style="color:blue;"><strong>Health Record <a href="' . url('/') . '/nosh/fhir_connect/list/as" target="_blank" class="btn btn-success btn-xs" style="margin-left:10px;">Connect to Hospital</a> <a href="' . url('/') . '/nosh/cms_bluebutton/as" target"_blank" class="btn btn-success btn-xs">Connect to Medicare</a></th><th></th><th></th>';
        $column_empty = '';
        $header_empty = '';
        $hr_column = '';
        $hr_column_header = '';
        $fhir_column = '';
        $fhir_column_header = '';
        $invited_column = '';
        $invited_column_header = '';
        $certifier_column = '';
        $certifier_column_header = '';
        $user_policies = $this->user_policies();
        $certifier_roles = $this->certifier_roles();
        $custom_polices = $this->query_custom_polices();
        $counts_arr = [
            'hr' => 0,
            'invited' => count($user_policies),
            'certifier' => count($certifier_roles)
        ];
        if ($directories) {
            foreach ($directories as $directory) {
                foreach ($policy_labels as $policy_label_k => $policy_label_v) {
                    $data['content'] .= '<th style="text-align:center;"><div class="as-info" as-info="' . $policy_label_v['info'] . '"><span>' . $policy_label_v['label'] . '</span></div></th>';
                    $policy_arr[] = $policy_label_k;
                    $fhir_column .= '<td style="text-align:center;">N/A</td>';
                    $counts_arr['hr']++;
                }
            }
        }
        // Build empty columns
        arsort($counts_arr);
        $max_count_arr = max($counts_arr);
        for ($i=0; $i <= $max_count_arr; $i++) {
            $column_empty .= '<td></td>';
            $header_empty .= '<th></th>';
        }
        foreach ($counts_arr as $counts_row_k => $counts_row_v) {
            $diff = $max_count_arr - $counts_row_v;
            if ($diff !== 0) {
                for ($x = 0; $x <= $diff; $x++) {
                    ${$counts_row_k . '_column'} .= '<td></td>';
                    ${$counts_row_k . '_column_header'} .= '<th></th>';
                }
            }
        }
        $data['content'] .= $hr_column_header . '</tr>';
        $main_header_colspan = $max_count_arr + 1;
        $main_header_html = str_replace('main_header_colspan', $main_header_colspan, $main_header_html);
        $data['content'] = str_replace('main_header_colspan', $main_header_colspan, $data['content']);
        $directory_header_colspan = $max_count_arr - $counts_arr['hr'] + 1;
        $data['content'] = str_replace('directory_header_colspan', $directory_header_colspan, $data['content']);
        // $data['content'] .= '<th><div class="as-info" as-info="Last time when the resource server was accessed by any client."><span>Last Accessed</span></div></th>';
        if ($query->count() || ! empty($smart_on_fhir)) {
            if ($query->count()) {
                foreach ($query as $client) {
                    $client_name_arr = explode(' ', $client->client_name);
                    if ($client_name_arr[0] . $client_name_arr[1] !== 'Directory-') {
                        $data['content'] .= '<tr><td><div class="row"><div class="col-xs-10" style="display:inline-block;float:none;"><a href="'. route('consent_edit', [$client->client_id]) . '">' . $client->client_name . '</a>';
                        if ($client_name_arr[0] . $client_name_arr[1] == 'PatientNOSH') {
                            $data['content'] .= '<br><span class="label label-success pnosh_link" nosh-link="' . $client->client_uri . '/patient">Go There</span></div>';
                        } else {
                            $data['content'] .= '</div>';
                        }
                        if ($client_name_arr[0] . $client_name_arr[1] == 'PatientNOSH') {
                            $data['content'] .=  '<div class="col-xs-2" style="display:inline-block;float:none;"><img src="' . asset('assets/UMA2-logo.png') . '" style="max-height: 40px;width: auto;"></img></div>';
                        }
                        $data['content'] .= '</div></td>';
                        $data['content'] .= '<td style="text-align:center;">N/A</td>'; // Ping Me column
                        $data['content'] .= '<td style="text-align:center;">Health Record</td>'; // Roles column
                        // if ($client_name_arr[0] . $client_name_arr[1] == 'PatientNOSH' || $client_name_arr[0] . $client_name_arr[1] == 'Directory-') {
                        //     $data['content'] .= '<td style="text-align:center;"><img src="' . asset('assets/UMA2-logo.png') . '" style="max-height: 50px;width: auto;"></img></td>';
                        //     if ($client_name_arr[0] . $client_name_arr[1] == 'Directory-') {
                        //         $data['content'] .= '<td style="text-align:center;"><i class="fa fa-check fa-lg no-edit" style="color:green;"></i></td>';
                        //     } else {
                        //         $data['content'] .= '<td></td>';
                        //     }
                        // } else {
                        //     $data['content'] .= '<td style="text-align:center;">N/A</td><td></td>';
                        //     // $data['content'] .= '<td style="text-align:center;"><i class="fa fa-times fa-lg no-edit" style="color:red;"></i></td><td></td>';
                        // }
                        if ($directories) {
                            foreach ($directories as $directory1) {
                                $rs_to_directory = DB::table('rs_to_directory')->where('directory_id', '=', $directory1->directory_id)->where('client_id', '=', $client->client_id)->first();
                                foreach ($policy_arr as $default_policy_type) {
                                    if ($client_name_arr[0] . $client_name_arr[1] == 'Directory-') {
                                        $data['content'] .= '<td></td>';
                                    } else {
                                        $data[$default_policy_type] = '';
                                        $consent = 'consent_' . $default_policy_type;
                                        if (isset($rs_to_directory->{$consent})) {
                                            if ($rs_to_directory->{$consent} == 1) {
                                                $data['content'] .= '<td style="text-align:center;"><a href="' . route('consent_edit', [$client->client_id, $rs_to_directory->{$consent}, $default_policy_type, $directory1->directory_id]) . '"><i class="fa fa-check fa-lg" style="color:green;"></i>';
                                                // if ($default_policy_type == 'last_activity' && $client->last_access !== null) {
                                                //     $data['content'] .= date('Y-m-d H:i:s', $client->last_access);
                                                // }
                                                $data['content'] .= '</a></td>';
                                            } else {
                                                $data['content'] .= '<td style="text-align:center;"><a href="' . route('consent_edit', [$client->client_id, $rs_to_directory->{$consent}, $default_policy_type, $directory1->directory_id]) . '"><i class="fa fa-times fa-lg" style="color:red;"></i></a></td>';
                                            }
                                        } else  {
                                            if ($default_policy_type == 'patient_user') {
                                                $data['content'] .= '<td><i class="fa fa-check fa-lg no-edit" style="color:green;"></i></td>';
                                            } elseif ($default_policy_type == 'root_support') {
                                                $data['content'] .= '<td><i class="fa fa-times fa-lg no-edit" style="color:red;"></i></td>';
                                            } else {
                                                $data['content'] .= '<td></td>';
                                            }
                                        }
                                        // $column_empty .= '<td></td>';
                                        // $fhir_column .= '<td style="text-align:center;>N/A</td>';
                                    }
                                }
                            }
                        }
                        // if ($client->last_access !== null) {
                        //     $data['content'] .= '<td><a href="' . route('consent_edit', [$client->client_id, $client->consent_last_activity, 'last_activity']) . '"><i class="fa fa-check fa-lg" style="color:green;"></i> ' . date('Y-m-d H:i:s', $client->last_access) . '</a></td>';
                        // } else {
                        //     $data['content'] .= '<td><a href="' . route('consent_edit', [$client->client_id, $client->consent_last_activity, 'last_activity']) . '"><i class="fa fa-times fa-lg" style="color:red;"></i></a></td>';
                        // }
                        $data['content'] .= $hr_column;
                        $data['content'] .= '</tr>';
                    }
                }
            }
            if (! empty($smart_on_fhir)) {
                foreach ($smart_on_fhir as $smart_row) {
                    $copy_link = '<i class="fa fa-key fa-lg pnosh_copy_set" hie-val="' . $smart_row['endpoint_uri_raw'] . '" title="Settings" style="cursor:pointer;"></i>';
                    $fhir_db = DB::table('fhir_clients')->where('endpoint_uri', '=', $smart_row['endpoint_uri_raw'])->first();
                    if ($fhir_db) {
                        if ($fhir_db->username !== null && $fhir_db->username !== '') {
                            $copy_link .= '<span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy" hie-val="' . $fhir_db->username . '" title="Copy username" style="cursor:pointer;"></i><span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy" hie-val="' . decrypt($fhir_db->password) . '" title="Copy password" style="cursor:pointer;"></i>';
                        }
                    } else {
                        $fhir_data = [
                            'name' => $smart_row['org_name'],
                            'endpoint_uri' => $smart_row['endpoint_uri_raw'],
                        ];
                        DB::table('fhir_clients')->insert($fhir_data);
                    }
                    $data['content'] .= '<tr><td><a href="' . $smart_row['endpoint_uri'] . '" target="_blank"><img src="https://avatars3.githubusercontent.com/u/7401080?v=4&s=200" style="max-height: 30px;width: auto;"><span style="margin:10px">' . $smart_row['org_name'] . '</span><span class="pull-right">' . $copy_link . '</span></a></td><td style="text-align:center;">N/A</td><td style="text-align:center;">Health Record</td>' . $fhir_column . $hr_column . '</tr>';
                }
            }
        }
        // $data['content'] .= '<tr><td><a href="' . url('/') . '/nosh/fhir_connect" target="_blank">Connect to your hospital EHR Account</a></td><td></td><td></td>' . $column_empty . '</tr>';
        // $data['content'] .= '<tr><td><a href="' . url('/') . '/nosh/cms_bluebutton" target"_blank">Connect to your Medicare Benefits Account</a></td><td></td><td></td>' . $column_empty . '</tr>';
        // $data['content'] .= '<tr><td><a href="#" class="as-info" as-info="Coming Soon!">Connect to additional resources</a></td><td></td><td></td>' . $column_empty . '</tr>';

        // Invited row
        $data['content'] .= '<tr><th style="color:blue;"><strong>Invited Users</strong> <a href="' . route('make_invitation') . '" class="btn btn-success btn-xs" style="margin-left:10px;">Invite Someone</a></th><th></th><th></th>';
        foreach ($user_policies as $user_policy) {
            $user_policy_text = str_replace(' ', '<br>', $user_policy['name']);
            $data['content'] .= '<th style="text-align:center;" class="as-info" as-info="' . $user_policy['description'] . '"><strong>' . $user_policy_text . '</strong></th>';
        }
        $data['content'] .= '<th style="text-align:center;"><strong>Custom<br>Policy</strong></th>' . $invited_column_header . '</tr>';
        // $data['content'] .= '<th style="text-align:center;"><strong>Read<br>Only</strong></th><th style="text-align:center;"><strong>Allergies<br>and<br>Medications</strong></th><th style="text-align:center;"><strong>Care<br>Team<br>List</strong></th><th style="text-align:center;"><strong>Custom<br>Policy</strong></th></tr>';
        $authorized_users = DB::table('oauth_users')->where('password', '!=', 'Pending')->get();
        $owner = DB::table('owner')->first();
        $proxies = DB::table('owner')->where('sub', '!=', $owner->sub)->get();
        $proxy_arr = [];
        if ($proxies) {
            foreach ($proxies as $proxy_row) {
                $proxy_arr[] = $proxy_row->sub;
            }
        }
        if ($authorized_users->count()) {
            foreach ($authorized_users as $authorized_user) {
                $role = $authorized_user->role;
                if ($authorized_user->role === null) {
                    if ($owner->sub == $authorized_user->sub || in_array($authorized_user->sub, $proxy_arr)) {
                        $role_data['role'] = 'Admin';
                        DB::table('oauth_users')->where('username', '=', $authorized_user->username)->update($role_data);
                        $role = 'Admin';
                    }
                }
                if ($authorized_user->notify === null) {
                    if ($owner->sub == $authorized_user->sub) {
                        $notify = 'N/A';
                        $notify_data['notify'] = 0;
                    } else {
                        $notify = '<a href="' . route('change_notify', [$authorized_user->username, 0, 'authorized']) . '"><i class="fa fa-check fa-lg" style="color:green;"></i></a>';
                        $notify_data['notify'] = 1;
                    }
                    DB::table('oauth_users')->where('username', '=', $authorized_user->username)->update($notify_data);
                } else {
                    if ($authorized_user->notify == 0) {
                        $notify = '<a href="' . route('change_notify', [$authorized_user->username, 1, 'authorized']) . '"><i class="fa fa-times fa-lg" style="color:red;"></i></a>';
                    } else {
                        $notify = '<a href="' . route('change_notify', [$authorized_user->username, 0, 'authorized']) . '"><i class="fa fa-check fa-lg" style="color:green;"></i></a>';
                    }
                }
                if ($owner->sub !== $authorized_user->sub) {
                    $data['content'] .= '<tr><td><a href="' . route('users') .'">' . $authorized_user->first_name . ' ' . $authorized_user->last_name . ' (' . $authorized_user->email . ')</a>';
                    if (in_array($authorized_user->sub, $proxy_arr)) {
                        $data['content'] .= '<span class="label label-info" style="margin:10px;">PROXY</span>';
                    }
                    $data['content'] .= '</td>';
                    $data['content'] .= '<td style="text-align:center;">' . $notify . '</td>';
                    $data['content'] .= '<td><select id="' . $authorized_user->username . '" class="form-control input-sm hie_user_role" hie_type="authorized">' . $this->roles_build($role) . '</select></td>';
                    $claim_id = DB::table('claim')->where('claim_value', '=', $authorized_user->email)->first();
                    $claim_policy_query = DB::table('claim_to_policy')->where('claim_id', '=', $claim_id->claim_id)->get();
                    foreach ($user_policies as $user_policy_row) {
                        $user_policy_status = false;
                        if ($claim_policy_query->count()) {
                            foreach ($claim_policy_query as $claim_policy_row) {
                                $policy_query = DB::table('policy')->where('policy_id', '=', $claim_policy_row->policy_id)->first();
                                if ($policy_query) {
                                    if ($policy_query->name == $user_policy_row['name']) {
                                        $user_policy_status = true;
                                    }
                                }
                            }
                        }
                        if ($user_policy_status == true) {
                            $data['content'] .= '<td style="text-align:center;"><a href="' . route('change_user_policy', [$user_policy_row['name'], $claim_id->claim_id, 'false', 'authorized']) . '"><i class="fa fa-check fa-lg" style="color:green;"></i></a></td>';
                        } else {
                            $data['content'] .= '<td style="text-align:center;"><a href="' . route('change_user_policy', [$user_policy_row['name'], $claim_id->claim_id, 'true', 'authorized']) . '"><i class="fa fa-times fa-lg" style="color:red;"></i></a></td>';
                        }
                    }
                    // Custom policies
                    $custom_policy = [];
                    foreach ($custom_polices as $custom_policy_row) {
                        if ($claim_policy_query) {
                            foreach ($claim_policy_query as $claim_policy_row1) {
                                $policy_query1 = DB::table('policy')->where('policy_id', '=', $claim_policy_row1->policy_id)->first();
                                if ($policy_query1) {
                                    if ($policy_query1->name == $custom_policy_row['name']) {
                                        $custom_policy[] = $custom_policy_row['name'];
                                    }
                                }
                            }
                        }
                    }
                    $data['content'] .= '<td><select id="' . $authorized_user->username . '_custom_policy" class="form-control input-sm hie_custom_policy" hie_type="authorized" hie_claim_id="' . $claim_id->claim_id . '">' . $this->custom_policy_build($custom_policy) . '</select></td>';
                }
            }
        }
        $invited_users = DB::table('invitation')->get();
        if ($invited_users->count()) {
            foreach ($invited_users as $invited_user) {
                $invite_link = '<a href="' . route('invite_cancel', [$invited_user->code, true]) . '" data-toggle="tooltip" title="Cancel Invite"><i class="fa fa-btn fa-lg fa-times"></i></a><a href="' . route('resend_invitation', [$invited_user->id]) . '" data-toggle="tooltip" title="Resend E-mail Notification"><i class="fa fa-btn fa-lg fa-retweet"></i></a>';
                if ($invited_user->notify === null) {
                    $notify1 = '<a href="' . route('change_notify', [$invited_user->id, 0, 'invite']) . '"><i class="fa fa-check fa-lg" style="color:green;"></i></a>';
                    $notify_data1['notify'] = 1;
                    DB::table('invitation')->where('id', '=', $invited_user->id)->update($notify_data1);
                } else {
                    if ($invited_user->notify == 0) {
                        $notify1 = '<a href="' . route('change_notify', [$invited_user->id, 1, 'invite']) . '"><i class="fa fa-times fa-lg" style="color:red;"></i></a>';
                    } else {
                        $notify1 = '<a href="' . route('change_notify', [$invited_user->id, 0, 'invite']) . '"><i class="fa fa-check fa-lg" style="color:green;"></i></a>';
                    }
                }
                $data['content'] .= '<tr><td><span><a href="' . route('authorize_user') .'">' . $invited_user->first_name . ' ' . $invited_user->last_name . ' (' . $invited_user->email . ')</a></span> <span class="label label-success">Pending</span><span class="pull-right">' . $invite_link . '</span></td>';
                $data['content'] .= '<td style="text-align:center;">' . $notify1 . '</td>';
                $data['content'] .= '<td><select id="' . $invited_user->id . '" class="form-control input-sm hie_user_role" hie_type="invite">' . $this->roles_build($invited_user->role) . '</select></td>';
                $invited_polices_arr = json_decode($invited_user->policies, true);
                foreach ($user_policies as $user_policy_row1) {
                    $invited_policies_content = '<td style="text-align:center;"><a href="' . route('change_user_policy', [$user_policy_row1['name'], $invited_user->id, 'true', 'invite']) . '"><i class="fa fa-times fa-lg" style="color:red;"></i></a></td>';
                    if (!empty($invited_polices_arr)) {
                        if (in_array($user_policy_row1['name'], $invited_polices_arr)) {
                            $invited_policies_content = '<td style="text-align:center;"><a href="' . route('change_user_policy', [$user_policy_row1['name'], $invited_user->id, 'false', 'invite']) . '"><i class="fa fa-check fa-lg" style="color:green;"></i></a></td>';
                        }
                    }
                    $data['content'] .= $invited_policies_content;
                }
                $invited_custom_policy = [];
                if ($invited_user->custom_policies !== null) {
                    $invited_custom_policy[] = $invited_user->custom_policies;
                }
                $data['content'] .= '<td><select id="' . $invited_user->id . '_custom_policy" class="form-control input-sm hie_custom_policy" hie_type="invited" hie_claim_id="' . $invited_user->id . '">' . $this->custom_policy_build($invited_custom_policy) . '</select></td>';
            }
        }

        // Certfier row
        $data['content'] .= '<tr><th style="color:blue;"><strong>Certifier</strong> <a href="' . route('certifier_add') . '" class="btn btn-success btn-xs" style="margin-left:10px;">Add Trusted Certifier</a></th><th></th><th></th>';
        foreach ($certifier_roles as $certifier_role_k => $certifier_role_v) {
            $certifier_role_text = str_replace(' ', '<br>', $certifier_role_k);
            $data['content'] .= '<th style="text-align:center;" class="as-info" as-info="' . $certifier_role_v['description'] . '"><strong>' . $certifier_role_text . '</strong></th>';
        }
        $data['content'] .= $certifier_column_header . '<th></th></tr>';
        $certifiers = $this->certifier_default();
        foreach ($certifiers as $certifier_k => $certifier_v) {
            $data['content'] .= '<tr><td><div class="row"><div class="col-xs-9" style="display:inline-block;float:none;">' . $certifier_k . '</div><div class="col-xs-3" style="display:inline-block;float:none;">';
            if (in_array('uPort', $certifier_v['badges'])) {
                $data['content'] .= '<div style="background-color:#000000;height:30px;width:30px;display:inline-block;float:none;"><img src="' . asset('assets/uport-logo-white.svg') . '" height="30" width="30" style="margin-right:5px"></img></div>';
            }
            if (in_array('OIDC', $certifier_v['badges'])) {
                $data['content'] .= '<div style="display:inline-block;float:none;"><i class="fa fa-fw fa-lg fa-openid" style="height:30px;width:30px;color#000000;"></i></div>';
            }
            $data['content'] .= '</div></td>';
            $data['content'] .= '<td>N/A</td><td>Certifier</td>';
            foreach ($certifier_roles as $certifier_role1_k => $certifier_roles1_v) {
                if ($certifier_role1_k !== 'Custom Role') {
                    if (in_array($certifier_role1_k, $certifier_v['roles'])) {
                        $data['content'] .= '<td style="text-align:center;"><i class="fa fa-check fa-lg no-edit" style="color:green;"></i></td>';
                    } else {
                        $data['content'] .= '<td style="text-align:center;"><i class="fa fa-times fa-lg no-edit" style="color:red;"></i></td>';
                    }
                } else {
                    if (in_array($certifier_role1_k, $certifier_v['roles'])) {
                        $data['content'] .= '<td style="text-align;center;">' . $certifier_v['custom_role'] . '</td>';
                    } else {
                        $data['content'] .= '<td></td>';
                    }
                }
            }
            // Custom certifier policy
            $data['content'] .= '<td></td>';
        }

        // Directory row
        $data['content'] .= '<tr><th style="color:blue;"><strong>Directory</strong> <a href="' . route('directory_add') . '" class="btn btn-success btn-xs" style="margin-left:10px;">Connect to a Directory</a></th><th></th><th></th>' . $header_empty . '</tr>';
        if ($directories) {
            foreach ($directories as $directory1) {
                $data['content'] .= '<tr><td><div class="row"><div class="col-xs-9" style="display:inline-block;float:none;"><a href="' . route('directories') .'">' . $directory->name . '</a></div><div class="col-xs-3" style="display:inline-block;float:none;"><div style="display:inline-block;float:none;"><img src="' . asset('assets/UMA2-logo.png') . '" style="max-height: 40px;width: auto;"></img></div><div style="display:inline-block;float:none;"><a href="' . route('directory_remove', [$directory->id, true]) . '" data-toggle="tooltip" title="Remove from Directory"><i class="fa fa-btn fa-lg fa-times" style="margin:10px;"></i></a></div></div></div></td><td></td><td>Directory</td>' . $column_empty . '</tr>';
            }
        }
        $data['content'] .= '</tbody></table></div>';

        // Legend
        $data['content'] .= '<div class="alert alert-success alert-dismissible"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>';
        $data['content'] .= '<strong>Legend</strong>';
        $data['content'] .= '<br><br><img src="' . asset('assets/UMA2-logo.png') . '" style="max-height: 40px;width: auto;"></img> designates a resource that uses the User-Managed Access 2.0 protocol for protecting your health data.  Trustee is a User-Managed Access 2.0 authorization server.';
        // $data['content'] .= '<br><br><img src="https://avatars3.githubusercontent.com/u/7401080?v=4&s=200" style="max-height: 30px;width: auto;"> designates a SMART-on-FHIR resource which has the following limitations:<ul>';
        // $data['content'] .= '<li><strong>No Refresh Tokens</strong></li><li><strong>No Dynamic Client Registration</strong></li><li><strong>and No User-Managed Access - therefore you cannot change access polices for this type of resource</strong></li></ul>';
        $data['content'] .= '<br><br><div style="background-color:#000000;height:30px;width:30px;display:inline-block;float:none;"><img src="' . asset('assets/uport-logo-white.svg') . '" height="30" width="30" style="margin-right:5px"></img></div> designates that the certifier uses uPort, an open identity system that features a self-sovereign wallet and verified credentials';
        $data['content'] .= '<br><br><div style="display:inline-block;float:none;"><i class="fa fa-fw fa-lg fa-openid" style="height:30px;width:30px;color#000000;"></i></div> designates that the certifier uses OpenIDConnect, an identity layer on top of the Oauth 2.0 protocol, which allows clients to verify the identity of the end user.';
        $data['content'] .= '</div>';
        Session::put('back', $request->fullUrl());
        return view('home', $data);
    }

    public function consent_edit(Request $request, $id, $toggle='', $policy='', $directory='')
    {
        if ($toggle == '') {
            Session::put('current_client_id', $id);
            return redirect()->route('consents_resource_server');
        }
        $types = [];
        if ($toggle == 0 || $toggle == null) {
            $data['consent_' .$policy] = 1;
            $types[] = $policy;
        } else {
            $data['consent_' .$policy] = 0;
        }
        DB::table('oauth_clients')->where('client_id', '=', $id)->update($data);
        $this->group_policy($id, $types, 'update');
        if ($directory !== '') {
            DB::table('rs_to_directory')->where('directory_id', '=', $directory)->where('client_id', '=', $id)->update($data);
            $this->directory_update_api();
        }
        return redirect()->back();
    }












}
