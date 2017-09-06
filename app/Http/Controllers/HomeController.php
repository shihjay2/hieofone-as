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
            $mdnosh = DB::table('oauth_clients')->where('client_name', 'LIKE', '%mdNOSH%')->first();
            if (! $mdnosh) {
                $data['mdnosh'] = true;
            }
            $pnosh = DB::table('oauth_clients')->where('client_name', 'LIKE', "%Patient NOSH for%")->first();
            if (! $pnosh) {
                $data['pnosh'] = true;
                $data['pnosh_url'] = URL::to('/') . '/nosh';
                $owner = DB::table('owner')->first();
                setcookie('pnosh_firstname', $owner->firstname);
                setcookie('pnosh_lastname', $owner->lastname);
                setcookie('pnosh_dob', date("m/d/Y", strtotime($owner->DOB)));
                setcookie('pnosh_email', $owner->email);
                setcookie('pnosh_username', Session::get('username'));
            }
            $data['message_action'] = Session::get('message_action');
            Session::forget('message_action');
            $query = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
            $url = URL::to('/') . '/nosh/smart_on_fhir_list';
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
            if ($query || count($smart_on_fhir) > 0) {
                $data['content'] = '<div class="list-group">';
                if ($query) {
                    foreach ($query as $client) {
                        $link = '';
                        if ($pnosh) {
                            if ($client->client_id == $pnosh->client_id) {
                                $link = '<span class="label label-success pnosh_link" nosh-link="' . URL::to('/') . '/nosh/uma_auth">Go There</span>';
                            }
                        }
                        $data['content'] .= '<a href="' . URL::to('resources') . '/' . $client->client_id . '" class="list-group-item"><img src="' . $client->logo_uri . '" style="max-height: 30px;width: auto;"><span style="margin:10px">' . $client->client_name . '</span>' . $link . '</a>';
                    }
                }
                if (count($smart_on_fhir) > 0) {
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
            // Get blockchain stats
            $data['blockchain_count'] = '0';
            $data['blockchain_table'] = 'None';
            $url = URL::to('/') . '/nosh/transactions';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
            $blockchain = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close ($ch);
            if ($httpCode !== 404) {
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
        $default_policy_type = [
            'login_direct',
            'login_md_nosh',
            'any_npi',
            'login_google'
        ];
        $data['back'] = '<a href="' . URL::to('home') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resource Services</a>';
        $query = DB::table('resource_set')->where('client_id', '=', $id)->get();
        if ($query) {
            $count1 = 0;
            if ($client->consent_login_direct == 1) {
                $count1++;
            }
            if ($client->consent_login_md_nosh == 1) {
                $count1++;
            }
            if ($client->consent_any_npi == 1) {
                $count1++;
            }
            if ($client->consent_login_google == 1) {
                $count1++;
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
                        if (!in_array($query3->claim_value, $default_policy_type)) {
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
        $default_policy_type = [
            'login_direct',
            'login_md_nosh',
            'any_npi',
            'login_google'
        ];
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
        $data['login_direct'] = '';
        $data['login_md_nosh'] = '';
        $data['any_npi'] = '';
        $data['login_google'] = '';
        if ($query->consent_login_direct == 1) {
            $data['login_direct'] = 'checked';
        }
        if ($query->consent_login_md_nosh == 1) {
            $data['login_md_nosh'] = 'checked';
        }
        if ($query->consent_any_npi == 1) {
            $data['any_npi'] = 'checked';
        }
        if ($query->consent_login_google == 1) {
            $data['login_google'] = 'checked';
        }
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
            $data['login_direct'] = '';
            $data['login_md_nosh'] = '';
            $data['any_npi'] = '';
            $data['login_google'] = '';
            if ($query1->login_direct == 1) {
                $data['login_direct'] = 'checked';
            }
            if ($query1->login_md_nosh == 1) {
                $data['login_md_nosh'] = 'checked';
            }
            if ($query1->any_npi == 1) {
                $data['any_npi'] = 'checked';
            }
            if ($query1->login_google == 1) {
                $data['login_google'] = 'checked';
            }
            return view('rs_authorize', $data);
        } else {
            return redirect()->route('home');
        }
    }

    public function rs_authorize_action(Request $request)
    {
        if ($request->input('submit') == 'allow') {
            $data['consent_login_direct'] = 0;
            $data['consent_login_md_nosh'] = 0;
            $data['consent_any_npi'] = 0;
            $data['consent_login_google'] = 0;
            $types = [];
            if ($request->input('consent_login_direct') == 'on') {
                $data['consent_login_direct'] = 1;
                $types[] = 'login_direct';
            }
            if ($request->input('consent_login_md_nosh') == 'on') {
                $data['consent_login_md_nosh'] = 1;
                $types[] = 'login_md_nosh';
            }
            if ($request->input('consent_any_npi') == 'on') {
                $data['consent_any_npi'] = 1;
                $types[] = 'any_npi';
            }
            if ($request->input('consent_login_google') == 'on') {
                $data['consent_login_google'] = 1;
                $types[] = 'login_google';
            }
            $data['authorized'] = 1;
            DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data);
            $client = DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->first();
            $this->group_policy($request->input('client_id'), $types, 'update');
            if (Session::get('oauth_response_type') == 'code') {
                $user_array = explode(' ', $client->user_id);
                $user_array[] = Session::get('username');
                $data['user_id'] = implode(' ', $user_array);
                DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data);
                Session::put('is_authorized', 'true');
            }
            Session::put('message_action', 'You just authorized a resource server named ' . $client->client_name);
        } else {
            Session::put('message_action', 'You just unauthorized a resource server named ' . $client->client_name);
            if (Session::get('oauth_response_type') == 'code') {
                Session::put('is_authorized', 'false');
            } else {
                $data1['authorized'] = 0;
                DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data1);
                $this->group_policy($request->input('client_id'), $types, 'delete');
            }
        }
        if (Session::get('oauth_response_type') == 'code') {
            return redirect()->route('authorize');
        } else {
            return redirect()->route('resources', ['id' => Session::get('current_client_id')]);
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
        if (Session::get('logo_uri') == '') {
            $data['permissions'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
        } else {
            $data['permissions'] = '<div><img src="' . Session::get('logo_uri') . '" style="margin:20px;text-align: center;"></div>';
        }
        $data['permissions'] .= '<h2>' . Session::get('client_name') . ' would like to:</h2>';
        $data['permissions'] .= '<ul class="list-group">';
        $client = DB::table('oauth_clients')->where('client_id', '=', Session::get('oauth_client_id'))->first();
        $scopes_array = explode(' ', $client->scope);
        foreach ($scopes_array as $scope) {
            if (array_key_exists($scope, $scope_array)) {
                $data['permissions'] .= '<li class="list-group-item"><i class="fa fa-btn ' . $scope_icon[$scope] . '"></i> ' . $scope_array[$scope] . '</li>';
            }
        }
        $data['permissions'] .= '</ul>';
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
        $data['content'] .= '<li class="list-group-item">Email: ' . $query->email . '</li>';
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
                    $pnosh_uri = URL::to('/') . '/nosh';
                    $pnosh = DB::table('oauth_clients')->where('client_uri', '=', $pnosh_uri)->first();
                    if ($pnosh) {
                        // Synchronize contact info with pNOSH
                        $url = URL::to('/') . '/nosh/as_sync';
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
        $data['login_direct'] = '';
        $data['login_md_nosh'] = '';
        $data['any_npi'] = '';
        $data['login_google'] = '';
        if ($query->login_direct == 1) {
            $data['login_direct'] = 'checked';
        }
        if ($query->login_md_nosh == 1) {
            $data['login_md_nosh'] = 'checked';
        }
        if ($query->any_npi == 1) {
            $data['any_npi'] = 'checked';
        }
        if ($query->login_google == 1) {
            $data['login_google'] = 'checked';
        }
        if ($query->login_uport == 1) {
            $data['login_uport'] = 'checked';
        }
        $data['content'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
        $data['content'] .= '<h3>Resource Registration Consent Default Policies</h3>';
        $data['content'] .= '<p>You can set default policies (who gets access to your resources) whenever you have a new resource server registered to this authorization server.</p>';
        return view('policies', $data);
    }

    public function change_policy(Request $request)
    {
        if ($request->input('submit') == 'save') {
            if ($request->input('login_direct') == 'on') {
                $data['login_direct'] = 1;
            } else {
                $data['login_direct'] = 0;
            }
            if ($request->input('login_md_nosh') == 'on') {
                $data['login_md_nosh'] = 1;
            } else {
                $data['login_md_nosh'] = 0;
            }
            if ($request->input('any_npi') == 'on') {
                $data['any_npi'] = 1;
            } else {
                $data['any_npi'] = 0;
            }
            if ($request->input('login_google') == 'on') {
                $data['login_google'] = 1;
            } else {
                $data['login_google'] = 0;
            }
            if ($request->input('login_uport') == 'on') {
                $data['login_uport'] = 1;
            } else {
                $data['login_uport'] = 0;
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
}
