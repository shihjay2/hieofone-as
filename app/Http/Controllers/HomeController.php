<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Form;
use Illuminate\Http\Request;
use QrCode;
use Session;
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
        } else {
            $data['name'] = Session::get('owner');
            $data['title'] = 'My Resource Services';
            $data['content'] = 'No resource services yet.';
            $data['blockchain_count'] = '0';
            $data['blockchain_table'] = 'None';
            // $mdnosh = DB::table('oauth_clients')->where('client_name', 'LIKE', '%mdNOSH%')->first();
            // if (! $mdnosh) {
            //     $data['mdnosh'] = true;
            // }
            $smart_on_fhir = [];
            $pnosh = DB::table('oauth_clients')->where('client_name', 'LIKE', "%Patient NOSH for%")->first();
            if (! $pnosh) {
                $url0 = URL::to('/') . '/nosh';
                $ch0 = curl_init();
                curl_setopt($ch0,CURLOPT_URL, $url0);
                curl_setopt($ch0,CURLOPT_FAILONERROR,1);
                curl_setopt($ch0,CURLOPT_FOLLOWLOCATION,1);
                curl_setopt($ch0,CURLOPT_RETURNTRANSFER,1);
                curl_setopt($ch0,CURLOPT_TIMEOUT, 60);
                curl_setopt($ch0,CURLOPT_CONNECTTIMEOUT ,0);
                $httpCode0 = curl_getinfo($ch0, CURLINFO_HTTP_CODE);
                curl_close ($ch0);
                if ($httpCode0 !== 404 && $httpCode0 !== 0) {
                    $data['pnosh'] = true;
                    $data['pnosh_url'] = $url0;
                    $owner = DB::table('owner')->first();
                    setcookie('pnosh_firstname', $owner->firstname);
                    setcookie('pnosh_lastname', $owner->lastname);
                    setcookie('pnosh_dob', date("m/d/Y", strtotime($owner->DOB)));
                    setcookie('pnosh_email', $owner->email);
                    setcookie('pnosh_username', Session::get('username'));
                }
            } else {
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
                $smart_on_fhir = json_decode($result, true);
                $url1 = $pnosh_url . '/transactions';
                $ch1 = curl_init();
                curl_setopt($ch1,CURLOPT_URL, $url1);
                curl_setopt($ch1,CURLOPT_FAILONERROR,1);
                curl_setopt($ch1,CURLOPT_FOLLOWLOCATION,1);
                curl_setopt($ch1,CURLOPT_RETURNTRANSFER,1);
                curl_setopt($ch1,CURLOPT_TIMEOUT, 60);
                curl_setopt($ch1,CURLOPT_CONNECTTIMEOUT ,0);
                $blockchain = curl_exec($ch1);
                $httpCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
                curl_close ($ch1);
                if ($httpCode !== 404 && $httpCode !== 0) {
                    $blockchain_arr = json_decode($blockchain, true);
                    $data['blockchain_count'] = $blockchain_arr['count'];
                    if ($blockchain_arr['count'] !== 0) {
                        $data['blockchain_table'] = '<table class="table table-striped"><thead><tr><th>Date</th><th>Provider</th><th>Transaction Receipt</th></thead><tbody>';
                        foreach ($blockchain_arr['transactions'] as $blockchain_row) {
                            $data['blockchain_table'] .= '<tr><td>' . date('Y-m-d', $blockchain_row['date']) . '</td><td>' . $blockchain_row['provider'] . '</td><td><a href="https://rinkeby.etherscan.io/tx/' . $blockchain_row['transaction'] . '" target="_blank">' . $blockchain_row['transaction'] . '</a></td></tr>';
                        }
                        $data['blockchain_table'] .= '</tbody></table>';
                        $data['blockchain_table'] .= '<strong>Top 5 Provider Users</strong>';
                        $data['blockchain_table'] .= '<table class="table table-striped"><thead><tr><th>Provider</th><th>Number of Transactions</th></thead><tbody>';
                        foreach ($blockchain_arr['providers'] as $blockchain_row1) {
                            $data['blockchain_table'] .= '<tr><td>' . $blockchain_row1['provider'] . '</td><td>' . $blockchain_row1['count'] . '</td></tr>';
                        }
                        $data['blockchain_table'] .= '</tbody></table>';
                    }
                }
            }
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $query = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
            if ($query || ! empty($smart_on_fhir)) {
                $data['content'] = '<div class="list-group">';
                if ($query) {
                    foreach ($query as $client) {
                        $link = '';
                        if ($pnosh) {
                            if ($client->client_id == $pnosh->client_id) {
                                $link = '<span class="label label-success pnosh_link" nosh-link="' . $pnosh_url . '/uma_auth">Go There</span>';
                            }
                        }
                        $data['content'] .= '<a href="' . URL::to('resources') . '/' . $client->client_id . '" class="list-group-item"><img src="' . $client->logo_uri . '" style="max-height: 30px;width: auto;"><span style="margin:10px">' . $client->client_name . '</span>' . $link . '</a>';
                    }
                }
                if (! empty($smart_on_fhir)) {
                    foreach ($smart_on_fhir as $smart_row) {
                        $copy_link = '<i class="fa fa-cog fa-lg pnosh_copy_set" hie-val="' . $smart_row['endpoint_uri_raw'] . '" title="Settings" style="cursor:pointer;"></i>';
                        $fhir_db = DB::table('fhir_clients')->where('endpoint_uri', '=', $smart_row['endpoint_uri_raw'])->first();
                        if ($fhir_db) {
                            if ($fhir_db->username !== null && $fhir_db->username !== '') {
                                $copy_link .= '<span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy" hie-val="' . $fhir_db->username . '" title="Copy username" style="cursor:pointer;"></i><span style="margin:10px"></span><i class="fa fa-key fa-lg pnosh_copy" hie-val="' . decrypt($fhir_db->password) . '" title="Copy password" style="cursor:pointer;"></i>';
                            }
                        } else {
                            $fhir_data = [
                                'name' => $smart_row['org_name'],
                                'endpoint_uri' => $smart_row['endpoint_uri_raw'],
                            ];
                            DB::table('fhir_clients')->insert($fhir_data);
                        }
                        $data['content'] .= '<a href="' . $smart_row['endpoint_uri'] . '" class="list-group-item list-group-item-success container-fluid" target="_blank"><img src="https://avatars3.githubusercontent.com/u/7401080?v=4&s=200" style="max-height: 30px;width: auto;"><span style="margin:10px">SMART-on-FHIR Resource (no refresh token): ' . $smart_row['org_name'] . '</span><span class="pull-right">' . $copy_link . '</span></a>';

                    }
                }
                $data['content'] .= '</div>';
            }
            return view('home', $data);
        }
    }

    /**
     * Show the registered resources.
     *
     * @return \Illuminate\Http\Response
     */
    public function resources(Request $request, $id)
    {
        $data['name'] = Session::get('owner');
        $client = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
        $data['title'] = 'My Resources from ' . $client->client_name;
        $data['content'] = 'No resources registered yet.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $default_policy_types = $this->default_policy_type();
        $data['back'] = '<a href="' . URL::to('home') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resource Services</a>';
        $query = DB::table('resource_set')->where('client_id', '=', $id)->get();
        if ($query) {
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
            $data['content'] = '<a href ="' . URL::to('consents_resource_server') . '" class="btn btn-primary" role="button" style="margin:15px"><span style="margin:20px;">Default Group Policies</span><span class="badge">' . $count1 . ' ' . $count_label1 . '</span></a>';
            $data['content'] .= '<div class="list-group">';
            foreach ($query as $resource) {
                $count = 0;
                $query1 = DB::table("policy")->where('resource_set_id', '=', $id)->get();
                foreach ($query1 as $policy) {
                    $query2 = DB::table('claim_to_policy')->where('policy_id', '=', $policy->policy_id)->first();
                    if ($query2) {
                        $query3 = DB::table('claim')->where('claim_id', '=', $query2->claim_id)->first();
                        if (!in_array($query3->claim_value, $default_policy_types)) {
                            $count++;
                        }
                    }
                }
                $count_label = 'individual policies';
                if ($count == 1) {
                    $count_label = 'individual policy';
                }
                $data['content'] .= '<a href="' . URL::to('resource_view') . '/' . $resource->resource_set_id . '" class="list-group-item"><img src="' . $resource->icon_uri . '" height="30" width="30"><span style="margin:10px;">' . $resource->name . '</span><span class="badge">' . $count . ' ' . $count_label . '</span></a>';
            }
            $data['content'] .= '</div>';
        }
        Session::put('back', $request->fullUrl());
        Session::put('current_client_id', $id);
        return view('home', $data);
    }

    /**
     * Show permissions for the resource.
     *
     * @param  int  $id - resource_set_id
     * @return \Illuminate\Http\Response
     *
     */
    public function resource_view(Request $request, $id)
    {
        $data['name'] = Session::get('owner');
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $uma_scope_array = [
            'view' => 'View',
            'edit' => 'Edit'
        ];
        $default_policy_type = $this->default_policy_type();
        $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
        $data['title'] = 'Permissions for ' . $query->name;
        $data['content'] = 'No policies registered for this resource.';
        $data['back'] = '<a href="' . URL::to('resources') . '/' . Session::get('current_client_id') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resources</a>';
        $query1 = DB::table("policy")->where('resource_set_id', '=', $id)->get();
        if ($query1) {
            $data['content'] = '<table class="table table-striped"><thead><tr><th>User</th><th>Permissions</th><th></th></thead><tbody>';
            foreach ($query1 as $policy) {
                // Get claim
                $query2 = DB::table('claim_to_policy')->where('policy_id', '=', $policy->policy_id)->first();
                if ($query2) {
                    $query3 = DB::table('claim')->where('claim_id', '=', $query2->claim_id)->first();
                    if (!in_array($query3->claim_value, $default_policy_type)) {
                        $user = $query3->name . ' (' . $query3->claim_value . ')';
                        $data['content'] .= '<tr><td>' . $user . '</td><td>';
                        $query4 = DB::table('policy_scopes')->where('policy_id', '=', $policy->policy_id)->get();
                        $i = 0;
                        foreach ($query4 as $scope) {
                            if (array_key_exists($scope->scope, $uma_scope_array)) {
                                if ($i > 0) {
                                    $data['content'] .= ', ';
                                }
                                $data['content'] .= $uma_scope_array[$scope->scope];
                                $i++;
                            }
                        }
                        $data['content'] .= '</td><td><a href="' . URL::to('change_permission') . '/' . $policy->policy_id . '" class="btn btn-primary" role="button">Change</a></td></tr>';
                    }
                }
            }
            $data['content'] .= '</tbody></table>';
        }
        return view('home', $data);
    }

    public function change_permission(Request $request, $id)
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
        $data['content'] = '<div class="col-md-6 col-md-offset-3"><p>' . $user . ' is currently allowed to ' . $permissions . ' ' . $query1->name . '</p>';
        if ($permissions == 'view') {
            $data['content'] .=  '<a href="' . URL::to('change_permission_add_edit') . '/' . $id . '" class="btn btn-success btn-block" role="button">Add Permission to Edit</a>';
        } else {
            $data['content'] .=  '<a href="' . URL::to('change_permission_remove_edit') . '/' . $id . '" class="btn btn-warning btn-block" role="button">Remove Permission to Edit</a>';
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

    /**
     * Client authorization pages.
     *
     * @param  int  $id - client_id
     * @return \Illuminate\Http\Response
     *
     */
    public function clients(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Authorized Clients';
        $data['content'] = 'No authorized clients.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $oauth_scope_array = [
            'openid' => 'OpenID Connect',
            'uma_authorization' => 'Access Resources',
            'uma_protection' => 'Register Resources'
        ];
        $query = DB::table('oauth_clients')->where('authorized', '=', 1)->get();
        if ($query) {
            $data['content'] = '<p>Clients are outside apps that work on behalf of users to access your resources.  You can authorize or unauthorized them at any time.</p><table class="table table-striped"><thead><tr><th>Client Name</th><th>Permissions</th><th></th></thead><tbody>';
            foreach ($query as $client) {
                $data['content'] .= '<tr><td>' . $client->client_name . '</td><td>';
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
                $data['content'] .= '</td><td><a href="' . route('authorize_client_disable', [$client->client_id]) . '" class="btn btn-primary" role="button">Unauthorize</a></td></tr>';
            }
        }
        return view('home', $data);
    }

    public function consents_resource_server(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Resource Registration Consent';
        $data['message_action'] = Session::get('message_action');
        $data['back'] = '<a href="' . URL::to('resources') . '/' . Session::get('current_client_id') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resources</a>';
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
        $default_policy_types = $this->default_policy_type();
        foreach ($default_policy_types as $default_policy_type) {
            $data[$default_policy_type] = '';
            $consent = 'consent_' . $default_policy_type;
            if ($query->{$consent} == 1) {
                $data[$default_policy_type] = 'checked';
            }
        }
        $data['set'] = 'true';
        return view('rs_authorize', $data);
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
            $query1 = DB::table('owner')->first();
            $default_policy_types = $this->default_policy_type();
            foreach ($default_policy_types as $default_policy_type) {
                $data[$default_policy_type] = '';
                if ($query1->{$default_policy_type} == 1) {
                    $data[$default_policy_type] = 'checked';
                }
            }
            return view('rs_authorize', $data);
        } else {
            return redirect()->route('home');
        }
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
        } else {
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

    public function authorize_client(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Clients Pending Authorization';
        $data['content'] = 'No clients pending authorization.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $oauth_scope_array = [
            'openid' => 'OpenID Connect',
            'uma_authorization' => 'Access Resources',
            'uma_protection' => 'Register Resources'
        ];
        $query = DB::table('oauth_clients')->where('authorized', '=', 0)->get();
        if ($query) {
            $data['content'] = '<p>Clients are outside apps that work on behalf of users to access your resources.  You can authorize or unauthorized them at any time.</p><table class="table table-striped"><thead><tr><th>Client Name</th><th>Permissions Requested</th><th></th></thead><tbody>';
            foreach ($query as $client) {
                $data['content'] .= '<tr><td>' . $client->client_name . '</td><td>';
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
                $data['content'] .= '</td><td><a href="' . route('authorize_client_action', [$client->client_id]) . '" class="btn btn-primary" role="button">Authorize</a>';
                $data['content'] .= ' <a href="' . route('authorize_client_disable', [$client->client_id]) . '" class="btn btn-primary" role="button">Deny</a></td></tr>';
            }
        }
        return view('home', $data);
    }

    public function authorize_client_action(Request $request, $id)
    {
        $data['authorized'] = 1;
        DB::table('oauth_clients')->where('client_id', '=', $id)->update($data);
        $query = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
        Session::put('message_action', 'You just authorized a client named ' . $query->client_name);
        return redirect()->route('authorize_client');
    }

    public function authorize_client_disable(Request $request, $id)
    {
        $query = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
        Session::put('message_action', 'You just unauthorized a client named ' . $query->client_name);
        DB::table('oauth_clients')->where('client_id', '=', $id)->delete();
        return redirect()->route('authorize_client');
    }

    public function users(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Authorized Users';
        $data['content'] = 'No authorized users.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $oauth_scope_array = [
            'openid' => 'OpenID Connect',
            'uma_authorization' => 'Access Resources',
            'uma_protection' => 'Register Resources'
        ];
        $query = DB::table('oauth_users')->where('password', '!=', 'Pending')->get();
        $owner = DB::table('owner')->first();
        $proxies = DB::table('owner')->where('sub', '!=', $owner->sub)->get();
        $proxy_arr = [];
        if ($proxies) {
            foreach ($proxies as $proxy_row) {
                $proxy_arr[] = $proxy_row->sub;
            }
        }
        if ($query) {
            $data['content'] = '<p>Users have access to your resources.  You can authorize or unauthorized them at any time.</p><table class="table table-striped"><thead><tr><th>Name</th><th>Email</th><th>NPI</th><th></th><th></th></thead><tbody>';
            foreach ($query as $user) {
                $data['content'] .= '<tr><td>' . $user->first_name . ' ' . $user->last_name . '</td><td>' . $user->email . '</td><td>';
                if ($user->npi !== null && $user->npi !== '') {
                    $data['content'] .= $user->npi;
                }
                $data['content'] .= '</td><td><a href="' . route('authorize_user_disable', [$user->username]) . '" class="btn btn-primary" role="button">Unauthorize</a></td>';
                if ($user->sub == $owner->sub) {
                    $data['content'] .= '<td></td>';
                } else {
                    if (Session::get('sub') == $owner->sub) {
                        if (in_array($user->sub, $proxy_arr)) {
                            $data['content'] .= '<td><a href="' . route('proxy_remove', [$user->sub]) . '" class="btn btn-danger" role="button">Remove As Proxy</a></td>';
                        } else {
                            $data['content'] .= '<td><a href="' . route('proxy_add', [$user->sub]) . '" class="btn btn-success" role="button">Add As Proxy</a></td>';
                        }
                    } else {
                        $data['content'] .= '<td></td>';
                    }
                }
                $data['content'] .= '</tr>';
            }
        }
        return view('home', $data);
    }

    public function authorize_user(Request $request)
    {
        $data['name'] = Session::get('owner');
        $data['title'] = 'Users Pending Authorization';
        $data['content'] = 'No users pending authorization.';
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $oauth_scope_array = [
            'openid' => 'OpenID Connect',
            'uma_authorization' => 'Access Resources',
            'uma_protection' => 'Register Resources'
        ];
        $query = DB::table('oauth_users')->where('password', '=', 'Pending')->get();
        if ($query) {
            $data['content'] = '<p>Users have access to your resources.  You can authorize or unauthorized them at any time.</p><table class="table table-striped"><thead><tr><th>Name</th><th>Email</th><th>NPI</th><th></th></thead><tbody>';
            foreach ($query as $user) {
                $data['content'] .= '<tr><td>' . $user->first_name . ' ' . $user->last_name . '</td><td>';
                if ($user->email !== null && $user->email !== '') {
                    $data['content'] .= $user->email;
                }
                $data['content'] .= '</td><td>';
                if ($user->npi !== null && $user->npi !== '') {
                    $data['content'] .= $user->npi;
                }
                $data['content'] .= '</td><td>';
                $data['content'] .= '</td><td><a href="' . route('authorize_user_action', [$user->username]) . '" class="btn btn-primary" role="button">Authorize</a>';
                $data['content'] .= ' <a href="' . route('authorize_user_disable', [$user->username]) . '" class="btn btn-primary" role="button">Deny</a></td></tr>';
            }
        }
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
        if ($sub !== $owner->sub) {
            DB::table('owner')->where('sub', '=', $sub)->delete();
            Session::put('message_action', 'You just removed ' . $query->first_name . ' ' . $query->last_name . ' as a proxy for you');
        } else {
            Session::put('message_action', 'You cannot remove yourself as the owner.');
        }
        return redirect()->route('users');
    }

    public function make_invitation(Request $request)
    {
        $owner = DB::table('owner')->first();
        $data['name'] = Session::get('owner');
        if ($request->isMethod('post')) {
            $this->validate($request, [
                'email' => 'required|unique:users,email',
                'first_name' => 'required',
                'last_name' => 'required'
            ]);
            // Check if
            $access_lifetime = App::make('oauth2')->getConfig('access_lifetime');
            $code = $this->gen_secret();
            $data1 = [
                'email' => $request->input('email'),
                'expires' => date('Y-m-d H:i:s', time() + $access_lifetime),
                'code' => $code,
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name')
            ];
            if ($request->has('client_id')) {
                $data1['client_ids'] = implode(',', $request->input('client_id'));
            }
            DB::table('invitation')->insert($data1);
            // Send email to invitee
            $url = URL::to('accept_invitation') . '/' . $code;
            $query0 = DB::table('oauth_rp')->where('type', '=', 'google')->first();
            $data2['message_data'] = 'You are invited to the HIE of One Authorization Server for ' . $owner->firstname . ' ' . $owner->lastname . '.<br>';
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
                if ($query) {
                    $data['rs'] = '<ul class="list-group checked-list-box">';
                    $data['rs'] .= '<li class="list-group-item"><input type="checkbox" id="all_resources" style="margin:10px;"/>All Resources</li>';
                    foreach ($query as $client) {
                        $data['rs'] .= '<li class="list-group-item"><input type="checkbox" name="client_id[]" class="client_ids" value="' . $client->client_id . '" style="margin:10px;"/><img src="' . $client->logo_uri . '" style="max-height: 30px;width: auto;"><span style="margin:10px">' . $client->client_name . '</span></li>';
                    }
                    $data['rs'] .= '</ul>';
                }
            }
            return view('invite', $data);
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
                return redirect()->route('home');
            } else {
                return redirect()->back()->withErrors(['tryagain' => 'Your old password was incorrect.  Try again.']);
            }
        } else {
            return view('changepassword', $data);
        }
    }

    public function my_info(Request $request)
    {
        $query = DB::table('oauth_users')->where('username', '=', Session::get('username'))->first();
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['title'] = 'My Information';
        $data['content'] = '<ul class="list-group">';
        $data['content'] .= '<li class="list-group-item">First Name: ' . $query->first_name . '</li>';
        $data['content'] .= '<li class="list-group-item">Last Name: ' . $query->last_name . '</li>';
        $data['content'] .= '<li class="list-group-item">Email: ' . $query->email;
        $data['content'] .= '<span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy my_info" hie-val="' . $query->email . '" title="Copy" style="cursor:pointer;"></i></li>';
        $data['content'] .= '<li class="list-group-item">URL: ' . URL::to('/');
        $data['content'] .= '<span style="margin:10px"></span><i class="fa fa-clone fa-lg pnosh_copy my_info" hie-val="' . URL::to('/') . '" title="Copy" style="cursor:pointer;"></i></li>';
        $owner_query = DB::table('owner')->first();
        if ($owner_query->sub == $query->sub) {
            $data['content'] .= '<li class="list-group-item">Date of Birth: ' . date('m/d/Y', strtotime($owner_query->DOB)) . '</li>';
            $data['content'] .= '<li class="list-group-item">Mobile Number: ' . $owner_query->mobile . '</li>';
        }
        if ($query->npi !== null && $query->npi !== '') {
            $data['content'] .= '<li class="list-group-item">NPI: ' . $query->npi . '</li>';
        }
        $data['content'] .= '</ul>';
        $data['back'] = '<a href="' . URL::to('my_info_edit') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-pencil"></i> Edit</a>';
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
                if ($owner_query->email !== $request->input('email') || $owner_query->mobile !== $request->input('mobile')) {
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
                            'sms' => $request->input('mobile')
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
                    }
                }
            }
            Session::put('message_action', 'Information Updated.' . $message);
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

    public function default_policies(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['name'] = Session::get('owner');
        $query = DB::table('owner')->first();
        $default_policy_types = $this->default_policy_type();
        foreach ($default_policy_types as $default_policy_type) {
            $data[$default_policy_type] = '';
            if ($query->{$default_policy_type} == 1) {
                $data[$default_policy_type] = 'checked';
            }
        }
        $data['content'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
        $data['content'] .= '<h3>Resource Registration Consent Default Policies</h3>';
        $data['content'] .= '<p>You can set default policies (who gets access to your resources) whenever you have a new resource server registered to this authorization server.</p>';
        return view('policies', $data);
    }

    public function change_policy(Request $request)
    {
        if ($request->input('submit') == 'save') {
            $default_policy_types = $this->default_policy_type();
            foreach ($default_policy_types as $default_policy_type) {
                if ($request->has($default_policy_type)) {
                    if ($request->input($default_policy_type) == 'on') {
                        $data[$default_policy_type] = 1;
                    } else {
                        $data[$default_policy_type] = 0;
                    }
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

    public function fhir_edit(Request $request)
    {
        $data['username'] = $request->input('username');
        $data['password'] = encrypt($request->input('password'));
        DB::table('fhir_clients')->where('endpoint_uri', '=', $request->input('endpoint_uri'))->update($data);
        return 'Username and password saved';
    }

    public function directories(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        Session::forget('message_action');
        $data['title'] = 'Authorized Directories';
        $root_url = explode('/', $request->root());
        // Assume root domain for directories starts with subdomain of dir and AS is another subdomain with common domain.
        $root_url1 = explode('.', $root_url[2]);
        if (isset($root_url1[1])) {
            $root_domain = 'https://dir.' . $root_url1[1];
        } else {
            $root_domain = 'https://dir.' . $root_url1[0];
        }
        $root_domain_registered = false;
        $data['content'] = '<p>Directories are servers that share the location of your authorization server. Users such as authorized physicians can use a directory service to easily access your authorization server and patient health record.  Directories can also associate your authorization server to communities of other patients with similar goals or attributes.  Directories do not gather or share your health information in any way.  You can authorize or unauthorized them at any time.</p><table class="table table-striped"><thead><tr><th>Directory Name</th><th>Permissions</th><th></th></thead><tbody>';
        $query = DB::table('directories')->get();
        if ($query) {
            foreach ($query as $directory) {
                $data['content'] .= '<tr><td>' . $directory->name . '</td>';
                $data['content'] .= '<td><a href="' . route('directory_remove', [$directory->id]) . '" class="btn btn-primary" role="button">Unauthorize</a></td></tr>';
                if ($directory->uri == $root_domain) {
                    $root_domain_registered = true;
                }
            }
        }
        if ($root_domain_registered == false) {
            // check if root domain exists
            $url = $root_domain . '/check';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
            $domain_name = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close ($ch);
            if ($httpCode !== 404 && $httpCode !== 0) {
                $data['content'] .= '<tr><td>Suggested directory: ' . $domain_name . '</td>';
                $data['content'] .= '<td><a href="' . route('directory_add', ['root']) . '" class="btn btn-success btn-block" role="button">Add</a></td></tr>';
            }
        }
        $data['content'] .= '</tbody></table>';
        $data['back'] = '<a href="' . URL::to('directory_add') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-plus"></i> Add Directory</a>';
        return view('home', $data);
    }

    public function directory_add(Request $request, $type='')
    {
        $as_url = $request->root();
        $owner = DB::table('owner')->first();
        $rs = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
        $rs_arr = [];
        if ($rs) {
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
            'as_uri' => $as_url,
            'redirect_uri' => route('directory_add', ['approve']),
            'name' => $owner->firstname . ' ' . $owner->lastname,
            'last_update' => time(),
            'rs' => $rs_arr
        ];
        if ($type == 'approve') {
            if (Session::has('directory_uri')) {
                $directory = [
                    'uri' => Session::get('directory_uri'),
                    'name' => $request->input('name'),
                    'directory_id' => $request->input('directory_id')
                ];
                DB::table('directories')->insert($directory);
                Session::forget('directory_uri');
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
            $response = $this->directory_api($pre_url, $params);
            if ($response['status'] == 'error') {
                return redirect()->back()->withErrors(['uri' => 'The URL provided is not valid.']);
            } else {
                return redirect($response['arr']['uri']);
            }
        } else {
            return view('directory');
            // return redirect()->route('directories');
        }
    }

    public function directory_remove(Request $request, $id)
    {
        $directory = DB::table('directories')->where('id', '=', $id)->first();
        $client = DB::table('oauth_clients')->where('client_uri', '=', rtrim($directory->uri, '/'))->first();
        $params['client_id'] = '0';
        if ($client) {
            $params['client_id'] = $client->client_id;
            DB::table('oauth_clients')->where('client_id', '=', $client_id)->delete();
        }
        $url = rtrim($directory->uri, '/');
        $response = $this->directory_api($url, $params, 'directory_update', $directory->directory_id);
        if ($response['arr']['message'] == 'Directory removed') {
            DB::table('directories')->where('id', '=', $id)->delete();
        }
        Session::put('message_action', $response['arr']['message']);
        return redirect()->route('directories');
    }

    public function directory_update(Request $request)
    {
        $as_url = $request->root();
        $owner = DB::table('owner')->first();
        $rs = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
        $rs_arr = [];
        if ($rs) {
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
            'as_uri' => $as_url,
            'name' => $owner->firstname . ' ' . $owner->lastname,
            'last_update' => time(),
            'rs' => $rs_arr
        ];
        $query = DB::table('directories')->get();
        $response1 = '<ul>';
        if ($query) {
            foreach ($query as $directory) {
                $url = rtrim($directory->uri, '/');
                $response = $this->directory_api($url, $params, 'directory_update', $directory->directory_id);
                $response1 = $directory->name . ': ' . $response['arr']['message'];
            }
        }
        $response1 .= '</ul'>
        Session::put('message_action', $repsonse1);
        return redirect()->route('directories');
    }

    public function consent_table(Request $request)
    {
        $data['message_action'] = Session::get('message_action');
        $query = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
        $policy_labels = [
            'public_publish_directory' => 'Public in HIE of One Directory',
            'private_publish_directory' => 'Private in HIE of One Directory',
            'any_npi' => 'Verfied Clnicians Only',
            'ask_me' => 'Ask Me'
        ];
        $policy_arr = [];
        $data['title'] = 'Consent Table';
        $data['content'] = '<table class="table table-striped"><thead><tr><th><div><span>Resource</span></div></th>';
        foreach ($policy_labels as $policy_label_k => $policy_label_v) {
            $data['content'] .= '<th><div><span>' . $policy_label_v . '</span></div></th>';
            $policy_arr[] = $policy_label_k;
        }
        $data['content'] .= '</tr></thead><tbody><tr>';
        if ($query) {
            foreach ($query as $client) {
                $data['content'] .= '<tr><td><a href="'. route('consent_edit', [$client->client_id]) . '">' . $client->client_name . '</a></td>';
                foreach ($policy_arr as $default_policy_type) {
                    $data[$default_policy_type] = '';
                    $consent = 'consent_' . $default_policy_type;
                    if (isset($client->{$consent})) {
                        if ($client->{$consent} == 1) {
                            $data['content'] .= '<td><i class="fa fa-check fa-lg" style="color:green;"></i></td>';
                        } else {
                            $data['content'] .= '<td><i class="fa fa-times fa-lg" style="color:red;"></i></td>';
                        }
                    } else  {
                        $data['content'] .= '<td></td>';
                    }
                }
                $data['content'] .= '</tr>';
            }
        }
        $data['content'] .= '</tbody></table>';
        Session::put('back', $request->fullUrl());
        return view('home', $data);
    }

    public function consent_edit(Request $request, $id)
    {
        Session::put('current_client_id', $id);
        return redirect()->route('consents_resource_server');
    }

    public function setup_mail(Request $request)
    {
        $query = DB::table('owner')->first();
        if (Session::get('is_owner') == 'yes' || $query == false || Session::get('install') == 'yes') {
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
        $data_message['item'] = 'This is a test';
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
}
