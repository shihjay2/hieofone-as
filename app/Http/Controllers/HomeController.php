<?php

namespace App\Http\Controllers;

use App;
use App\Http\Requests;
use DB;
use Form;
use Illuminate\Http\Request;
use QrCode;
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
		$data['name'] = $request->session()->get('owner');
		$data['title'] = 'My Resource Services';
		$data['content'] = 'No resource services yet.';
		$data['message_action'] = $request->session()->get('message_action');
		$request->session()->forget('message_action');
		$query = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
		if ($query) {
			$data['content'] = '<div class="list-group">';
			foreach ($query as $client) {
				$data['content'] .= '<a href="' . URL::to('resources') . '/' . $client->client_id . '" class="list-group-item"><img src="' . $client->logo_uri . '" height="30" width="30"><span style="margin:10px">' . $client->client_name . '</span></a>';
			}
			$data['content'] .= '</div>';
		}
		return view('home', $data);
	}

	/**
	 * Show the registered resources.
	 *
	 * @return \Illuminate\Http\Response
	 */
	public function resources(Request $request, $id)
	{
		$data['name'] = $request->session()->get('owner');
		$client = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
		$data['title'] = 'My Resources for ' . $client->client_name;
		$data['content'] = 'No resources registered yet.';
		$data['message_action'] = $request->session()->get('message_action');
		$request->session()->forget('message_action');
		$data['back'] = '<a href="' . URL::to('home') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resource Services</a>';
		$query = DB::table('resource_set')->where('client_id', '=', $id)->get();
		if ($query) {
			$data['content'] = '<div class="list-group">';
			foreach ($query as $resource) {
				$data['content'] .= '<a href="' . URL::to('resource_view') . '/' . $resource->resource_set_id . '" class="list-group-item"><img src="' . $resource->icon_uri . '" height="20" width="20"><span style="margin:10px;">' . $resource->name . '</span></a>';
			}
			$data['content'] .= '</div>';
		}
		$request->session()->put('current_client_id', $id);
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
		$data['name'] = $request->session()->get('owner');
		$data['message_action'] = $request->session()->get('message_action');
		$request->session()->forget('message_action');
		$uma_scope_array = [
			'view' => 'View',
			'edit' => 'Edit'
		];
		$query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
		$data['title'] = 'Permissions for ' . $query->name;
		$data['content'] = 'No policies registered for this resource.';
		$data['back'] = '<a href="' . URL::to('resources') . '/' . $request->session()->get('current_client_id') . '" class="btn btn-default" role="button"><i class="fa fa-btn fa-chevron-left"></i> My Resources</a>';
		$query1 = DB::table("policy")->where('resource_set_id', '=', $id)->get();
		if ($query1) {
			$data['content'] = '<table class="table table-striped"><thead><tr><th>User</th><th>Permissions</th><th></th></thead><tbody>';
			$row_array = [];
			$i = 0;
			foreach ($query1 as $policy) {
				// Get claim
				$query2 = DB::table('claim_to_policy')->where('policy_id', '=', $policy->policy_id)->first();
				if ($query2) {
					$query3 = DB::table('claim')->where('claim_id', '=', $query2->claim_id)->first();
					$query4 = DB::table('oauth_users')->where('email', '=', $query3->claim_value)->first();
					if ($query4) {
						$user = $query4->first_name . ' ' . $query4->last_name . ' (' . $query3->claim_value . ')';
					} else {
						$user = $query3->claim_value;
					}
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
			$data['content'] .= '</tbody></table>';
		}
		return view('home', $data);
	}

	public function change_permission(Request $request, $id)
	{
		$data['name'] = $request->session()->get('owner');
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
		$request->session()->put('message_action', 'Added permission to edit resource');
		return redirect($url);
	}

	public function change_permission_remove_edit(Request $request, $id)
	{
		DB::table('policy_scopes')->where('policy_id', '=', $id)->where('scope', '=', 'edit')->delete();
		$query = DB::table("policy")->where('policy_id', '=', $id)->first();
		$url = URL::to('resource_view') . '/' . $query->resource_set_id;
		$request->session()->put('message_action', 'Removed permission to edit resource');
		return redirect($url);
	}

	public function change_permission_delete(Request $request, $id)
	{
		DB::table('policy_scopes')->where('policy_id', '=', $id)->delete();
		DB::table('claim_to_policy')->where('policy_id', '=', $id)->delete();
		$query = DB::table("policy")->where('policy_id', '=', $id)->first();
		$url = URL::to('resource_view') . '/' . $query->resource_set_id;
		DB::table('policy')->where('policy_id', '=', $id)->delete();
		$request->session()->put('message_action', 'Removed all permissions to access the resource');
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
		$data['name'] = $request->session()->get('owner');
		$data['title'] = 'Authorized Clients';
		$data['content'] = 'No authorized clients.';
		$data['message_action'] = $request->session()->get('message_action');
		$request->session()->forget('message_action');
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
				$data['content'] .= '</td><td><a href="' . URL::to('authorize_client_disable') . '/' . $client->client_id . '" class="btn btn-primary" role="button">Unauthorize</a></td></tr>';
			}
		}
		return view('home', $data);
	}

	public function authorize_resource_server(Request $request)
	{
		$data['name'] = $request->session()->get('owner');
		$data['title'] = 'Resource Registration Consent';
		$data['content'] = 'No resource servers pending authorization.';
		$data['message_action'] = $request->session()->get('message_action');
		$request->session()->forget('message_action');
		$query = DB::table('oauth_clients')->where('authorized', '=', '0')->first();
		if ($query) {
			$scopes_array = explode(' ', $query->scope);
			if ($query->logo_uri == '') {
				$data['content'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
			} else {
				$data['content'] = '<div><img src="' . $query->logo_uri . '" style="margin:20px;text-align: center;"></div>';
			}
			$data['content'] .= '<h3>Your information at ' . $query->client_name . ' will be shared</h3>';
			$data['content'] .= '<p>By clicking Allow, you consent to sharing your information on ' . $query->client_name . ' according to the policies selected below. You can revoke consent or change your policies for ' . $query->client_name . ' at any time using the My Resources page. Requesting parties that are subject to your polices will be listed on the Clients page where you can enhance or limit their access privileges.</p>';
			$data['content'] .= '<input type="hidden" name="client_id" value="' . $query->client_id . '"/>';
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
			$data['consent_login_google'] = 0;
			if ($request->input('consent_login_direct') == 'on') {
				$data['consent_login_direct'] = 1;
			}
			if ($request->input('consent_login_md_nosh') == 'on') {
				$data['consent_login_md_nosh'] = 1;
			}
			if ($request->input('consent_login_google') == 'on') {
				$data['consent_login_google'] = 1;
			}
			$data['authorized'] = 1;
			DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->update($data);
			$client = DB::table('oauth_clients')->where('client_id', '=', $request->session()->get('client_id'))->first();
			$user_array = explode(' ', $client->user_id);
			$user_array[] = $request->session()->get('username');
			$data['user_id'] = implode(' ', $user_array);
			DB::table('oauth_clients')->where('client_id', '=', $request->session()->get('client_id'))->update($data);
			$request->session()->put('message_action', 'Authorized resource server named ' . $client->client_name);
			if ($request->session()->get('response_type') == 'code') {
				$request->session()->put('is_authorized', 'true');
			}
		} else {
			$request->session()->put('message_action', 'Unauthorized resource server named ' . $client->client_name);
			if ($request->session()->get('response_type') == 'code') {
				$request->session()->put('is_authorized', 'false');
			}
		}
		if ($request->session()->get('response_type') == 'code') {
			return redirect()->route('authorize');
		} else {
			return redirect()->route('home');
		}
	}

	public function authorize_client(Request $request)
	{
		$data['name'] = $request->session()->get('owner');
		$data['title'] = 'Clients Pending Authorization';
		$data['content'] = 'No clients pending authorization.';
		$data['message_action'] = $request->session()->get('message_action');
		$request->session()->forget('message_action');
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
				$data['content'] .= '</td><td><a href="' . URL::to('authorize_client_action') . '/' . $client->client_id . '" class="btn btn-primary" role="button">Authorize</a>';
				$data['content'] .= ' <a href="' . URL::to('authorize_client_disable') . '/' . $client->client_id . '" class="btn btn-primary" role="button">Deny</a></td></tr>';
			}
		}
		return view('home', $data);
	}

	public function authorize_client_action(Request $request, $id)
	{
		$data = [
			'authorized' => 1
		];
		DB::table('oauth_clients')->where('client_id', '=', $id)->update($data);
		$query = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
		$request->session()->put('message_action', 'Authorized Client named ' . $query->client_name);
		return redirect()->route('authorize_client');
	}

	public function authorize_client_disable(Request $request, $id)
	{
		$query = DB::table('oauth_clients')->where('client_id', '=', $id)->first();
		$request->session()->put('message_action', 'Unauthorized Client named ' . $query->client_name);
		DB::table('oauth_clients')->where('client_id', '=', $id)->delete();
		return redirect()->route('authorize_client');
	}

	public function make_invitation(Request $request)
	{
		$data['name'] = $request->session()->get('owner');
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
			DB::table('invitation')->insert($data1);
			// Send email to invitee
			$url = URL::to('accept_invitation') . '/' . $code;
			$query0 = DB::table('oauth_rp')->where('type', '=', 'google')->first();
			$owner = DB::table('owner')->first();
			$data2['message_data'] = 'You are invited to the HIE of One Authorization Server for ' . $owner->firstname . ' ' . $owner->lastname . '.<br>';
			$data2['message_data'] .= 'Go to ' . $url . ' to get registered.';
			$title = 'Invitation to ' . $owner->firstname . ' ' . $owner->lastname  . "'s Authorization Server";
			$to = $request->input('email');
			$this->send_mail('auth.emails.generic', $data2, $title, $to);
			$data3['name'] = $request->session()->get('owner');
			$data3['title'] = 'Invitation Code';
			$data3['content'] = '<p>Invitation sent to ' . $request->input('first_name') . ' ' . $request->input('last_name') . ' (' . $to . ')</p>';
			$data3['content'] .= '<p>Alternatively, show the recently invited guest your QR code:</p><div style="text-align: center;">';
			$data3['content'] .= QrCode::size(300)->generate($url);
			$data3['content'] .= '</div>';
			return view('home', $data3);
		} else {
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
		if ($request->session()->get('logo_uri') == '') {
			$data['permissions'] = '<div><i class="fa fa-child fa-5x" aria-hidden="true" style="margin:20px;text-align: center;"></i></div>';
		} else {
			$data['permissions'] = '<div><img src="' . $request->session()->get('logo_uri') . '" style="margin:20px;text-align: center;"></div>';
		}
		$data['permissions'] .= '<h2>' . $request->session()->get('client_name') . ' would like to:</h2>';
		$data['permissions'] .= '<ul class="list-group">';
		$client = DB::table('oauth_clients')->where('client_id', '=', $request->session()->get('client_id'))->first();
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
			$client = DB::table('oauth_clients')->where('client_id', '=', $request->session()->get('client_id'))->first();
			$user_array = explode(' ', $client->user_id);
			$user_array[] = $request->session()->get('username');
			$data['user_id'] = implode(' ', $user_array);
			DB::table('oauth_clients')->where('client_id', '=', $request->session()->get('client_id'))->update($data);
			$request->session()->put('is_authorized', true);
		} else {
			$request->session()->put('is_authorized', false);
		}
		return redirect()->route('authorize');
	}

	public function change_password(Request $request)
	{
		$data['name'] = $request->session()->get('owner');
		if ($request->isMethod('post')) {
			$this->validate($request, [
				'old_password' => 'required',
				'password' => 'required|min:7',
				'confirm_password' => 'required|min:7|same:password',
			]);
			$query = DB::table('oauth_users')->where('username', '=', $request->session()->get('username'))->first();
			if ($query->password == sha1($request->input('old_password'))) {
				$data1['password'] = sha1($request->input('password'));
				DB::table('oauth_users')->where('username', '=', $request->session()->get('username'))->update($data1);
				$request->session()->put('message_action', 'Password changed!');
				return redirect()->route('home');
			} else {
				return redirect()->back()->withErrors(['tryagain' => 'Your old password was incorrect.  Try again.']);
			}
		} else {
			return view('changepassword', $data);
		}
	}
}
