<?php
namespace App\Http\Controllers;

use App;
use App\Http\Controllers\Controller;
use App\Libraries\OpenIDConnectClient;
use App\User;
use Artisan;
use Auth;
use DB;
use File;
use Google_Client;
use Hash;
use Illuminate\Http\Request;
use OAuth2\HttpFoundationBridge\Request as BridgeRequest;
use OAuth2\HttpFoundationBridge\Response as BridgeResponse;
use Response;
use Socialite;
use URL;

class OauthController extends Controller
{
	/**
	* Base funtions
	*
	*/

	public function github_all()
	{
		$client = new \Github\Client(
			new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
		);
		$client = new \Github\HttpClient\CachedHttpClient();
		$client->setCache(
			new \Github\HttpClient\Cache\FilesystemCache('/tmp/github-api-cache')
		);
		$client = new \Github\Client($client);
		$result = $client->api('repo')->commits()->all('shihjay2', 'hieofone-as', array('sha' => 'master'));
		return $result;
	}

	public function github_single($sha)
	{
		$client = new \Github\Client(
			new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
		);
		$client = new \Github\HttpClient\CachedHttpClient();
		$client->setCache(
			new \Github\HttpClient\Cache\FilesystemCache('/tmp/github-api-cache')
		);
		$client = new \Github\Client($client);
		$result = $commit = $client->api('repo')->commits()->show('shihjay2', 'hieofone-as', $sha);
		return $result;
	}

	/**
	* Installation
	*
	*/

	public function install(Request $request)
	{
		// Check if already installed, if so, go back to home page
		$query = DB::table('owner')->first();
		// if ($query) {
		if (! $query) {
			// Tag version number for baseline prior to updating system in the future
			if (!File::exists(__DIR__."/../../../.version")) {
			  // First time after install
			  $result = $this->github_all();
			  File::put(__DIR__."/../../../.version", $result[0]['sha']);
			}
			// Is this from a submit request or not
			if ($request->isMethod('post')) {
				$this->validate($request, [
					'username' => 'required',
					'email' => 'required',
					'password' => 'required|min:7',
					'confirm_password' => 'required|min:7|same:password',
					'first_name' => 'required',
					'last_name' => 'required',
					'date_of_birth' => 'required',
					'google_client_id' => 'required',
					'google_client_secret' => 'required',
					'smtp_username' => 'required'
				]);
				// Register user
				$user_data = [
					'username' => $request->input('username'),
					'password' => sha1($request->input('password')),
					//'password' => substr_replace(Hash::make($request->input('password')),"$2a",0,3),
					'first_name' => $request->input('first_name'),
					'last_name' => $request->input('last_name'),
					'sub' => $this->gen_uuid(),
					'email' => $request->input('email')
				];
				DB::table('oauth_users')->insert($user_data);
				$user_data1 = [
					'name' => $request->input('username'),
					'email' => $request->input('email')
				];
				DB::table('users')->insert($user_data1);
				// Register owner
				$clientId = $this->gen_uuid();
				$clientSecret = $this->gen_secret();
				$owner_data = [
					'lastname' => $request->input('last_name'),
					'firstname' => $request->input('first_name'),
					'DOB' => date('Y-m-d', strtotime($request->input('date_of_birth'))),
					'email' => $request->input('email'),
					'mobile' => $request->input('mobile'),
					'client_id' => $clientId
				];
				DB::table('owner')->insert($owner_data);
				// Register oauth for Google and Twitter
				$google_data = [
					'type' => 'google',
					'client_id' => $request->input('google_client_id'),
					'client_secret' => $request->input('google_client_secret'),
					'redirect_uri' => URL::to('account/google'),
					'smtp_username' => $request->input('smtp_username')
				];
				DB::table('oauth_rp')->insert($google_data);
				if ($request->input('twitter_client_id') !== '') {
					$twitter_data = [
						'type' => 'twitter',
						'client_id' => $request->input('twitter_client_id'),
						'client_secret' => $request->input('twitter_client_secret'),
						'redirect_uri' => URL::to('account/twitter')
					];
					DB::table('oauth_rp')->insert($twitter_data);
				}
				// Register server as its own client
				$grant_types = 'client_credentials password authorization_code implicit jwt-bearer refresh_token';
				$scopes = 'openid profile email address phone offline_access';
				$data = [
					'client_id' => $clientId,
					'client_secret' => $clientSecret,
					'grant_types' => $grant_types,
					'scope' => $scopes,
					'user_id' => $request->input('username'),
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
				// Go register with Google to get refresh token for email setup
				return redirect()->route('installgoogle');
			} else {
			$data2 = [
				'noheader' => true
			];
			return view('install', $data2);
			}
		}
		return redirect()->route('home');
	}

	/**
	* Login and logout functions
	*
	*/

	public function login(Request $request)
	{
		if ($request->isMethod('post')) {
			$this->validate($request, [
				'username' => 'required',
				'password' => 'required'
			]);
			// Check if there was an old request from the ouath_authorize function, else assume login is coming from server itself
			if ($request->session()->get('response_type') == 'code') {
				$client_id = $request->session()->get('client_id');
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
			$bridgedResponse = new BridgeResponse();
			$bridgedResponse = App::make('oauth2')->grantAccessToken($bridgedRequest, $bridgedResponse);
			// Confirm if client is authorized
			$authorized = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
			if (isset($bridgedResponse['access_token']) && $authorized) {
				// Update to include JWT for introspection in the future if needed
				$jwt_data = [
					'jwt' => $bridgedResponse['access_token']
				];
				DB::table('oauth_access_tokens')->where('access_token', '=', substr($bridgedResponse['access_token'], 0, 255))->update($jwt_data);
				// Access token granted, authorize login!
				$owner_query = DB::table('owner')->first();
				session([
					'access_token' => $bridgedResponse['access_token'],
					'client_id' => $client_id,
					'owner' => $owner_query->firstname . ' ' . $owner_query->lastname,
					'username' => $request->username,
					'client_name' => $authorized->client_name,
					'logo_uri' => $authorized->logo_uri
				]);
				$user1 = DB::table('users')->where('name', '=', $request->username)->first();
				Auth::loginUsingId($user1->id);
				if ($request->session()->get('response_type') == 'code') {
					// This call is from authorization endpoint.  Check if user is associated with client
					$user_array = explode(' ', $authorized->user_id);
					if (in_array($request->username, $user_array)) {
						// Go back to authorize route
						$request->session()->put('is_authorized', true);
						return redirect()->route('authorize');
					} else {
						// Get user permission
						return redirect()->route('login_authorize');
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
				if ($request->old('response_type') == 'code') {
					$data['nooauth'] = true;
				}
				$data['google'] = DB::table('oauth_rp')->where('type', '=', 'google')->first();
				$data['twitter'] = DB::table('oauth_rp')->where('type', '=', 'twitter')->first();
				return view('auth.login', $data);
			} else {
				// Not installed yet
				$data2 = [
					'noheader' => true
				];
				return view('install', $data2);
			}
		}
	}

	public function logout(Request $request)
	{
		$request->session()->flush();
		Auth::logout();
		return redirect()->route('welcome');
	}

	public function oauth_login(Request $request)
	{
		$code = $request->input('code');
		return $code;
	}

	/**
	* Update system through GitHub
	*
	*/

	public function update_system()
	{
		$current_version = File::get(__DIR__."/../../../.version");
		$result = $this->github_all();
		if ($current_version != $result[0]['sha']) {
			$arr = array();
			foreach($result as $row) {
				$arr[] = $row['sha'];
				if ($current_version == $row['sha']) {
					break;
				}
			}
			$arr2 = array_reverse($arr);
			foreach($arr2 as $sha) {
				$result1 = $this->github_single($sha);
				if (isset($result1['files'])) {
					foreach($result1['files'] as $row1) {
						$filename = __DIR__."/../../../" . $row1['filename'];
						if ($row1['status'] == 'added' || $row1['status'] == 'modified') {
							$github_url = str_replace(' ', '%20', $row1['raw_url']);
							$file = file_get_contents($github_url);
							$parts = explode('/', $row1['filename']);
							array_pop($parts);
							$dir = implode('/', $parts);
							if (!is_dir(__DIR__."/../../../" . $dir)) {
								if ($parts[0] == 'public') {
									mkdir(__DIR__."/../../../" . $dir, 0777, true);
								} else {
									mkdir(__DIR__."/../../../" . $dir, 0755, true);
								}
							}
							file_put_contents($filename, $file);
						}
						if ($row1['status'] == 'removed') {
							if (file_exists($filename)) {
								unlink($filename);
							}
						}
					}
				}
			}
			Artisan::call('migrate');
			File::put(__DIR__."/../../../.version", $result[0]['sha']);
			echo "System Updated with version " . $result[0]['sha'] . " from " . $current_version;
		} else {
			echo "No update needed";
		}
	}

	/**
	* Client registration page if they are given a QR code by the owner of this authorization server
	*
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
			return redirect()->route('home');
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

	public function google(Request $request)
	{
		$query0 = DB::table('oauth_rp')->where('type', '=', 'google')->first();
		config(['services.google.client_id' => $query0->client_id]);
		config(['services.google.client_secret' => $query0->client_secret]);
		config(['services.google.redirect' => $query0->redirect_uri]);
		$user = Socialite::driver('google')->user();
		session(['email' => $user->getEmail()]);
		if ($request->session()->has('permission_ticket') && $request->session()->has('redirect_uri') && $request->session()->has('client_id') && $request->session()->has('email')) {
			// If generated from rqp_claims endpoint, do this
			return redirect()->route('rqp_claims');
		} else {
			// Login user
			$this->oauth_authenticate($user->getEmail());
			return redirect()->route('home');
		}
	}

	public function twitter_redirect()
	{
		$query0 = DB::table('oauth_rp')->where('type', '=', 'twitter')->first();
		config(['services.twitter.client_id' => $query0->client_id]);
		config(['services.twitter.client_secret' => $query0->client_secret]);
		config(['services.twitter.redirect' => $query0->redirect_uri]);
		return Socialite::driver('twitter')->redirect();
	}

	public function twitter(Request $request)
	{
		$query0 = DB::table('oauth_rp')->where('type', '=', 'twitter')->first();
		config(['services.twitter.client_id' => $query0->client_id]);
		config(['services.twitter.client_secret' => $query0->client_secret]);
		config(['services.twitter.redirect' => $query0->redirect_uri]);
		$user = Socialize::driver('twitter')->user();
		session(['email' => $user->getEmail()]);
		if ($request->session()->has('permission_ticket') && $request->session()->has('redirect_uri') && $request->session()->has('client_id') && $request->session()->has('email')) {
			// If generated from rqp_claims endpoint, do this
			return redirect()->route('rqp_claims');
		} else {
			$this->oauth_authenticate($user->getEmail());
			return redirect()->route('home');
		}
	}

	public function mdnosh(Request $request)
	{
		// Check if dynamically registered
		$query0 = DB::table('oauth_rp')->where('type', '=', 'mdnosh')->first();
		if ($query0) {
			// Registered
			$client = [
				'client_id' => $query0->client_id,
				'client_secret' => $query0->client_secret
			];
		} else {
			$client = $this->mdnosh_register_client();
		}
		$open_id_url = 'https://noshchartingsystem.com/openid-connect-server-webapp/';
		$url = route('mdnosh');
		$oidc = new OpenIDConnectClient($open_id_url, $client['client_id'], $client['client_secret']);
		$oidc->setRedirectURL($url);
		$oidc->addScope('openid');
		$oidc->addScope('email');
		$oidc->addScope('profile');
		$oidc->authenticate();
		// $firstname = $oidc->requestUserInfo('given_name');
		// $lastname = $oidc->requestUserInfo('family_name');
		// $email = $oidc->requestUserInfo('email');
		// $npi = $oidc->requestUserInfo('npi');
		$access_token = $oidc->getAccessToken();
		session(['email' => $oidc->requestUserInfo('email')]);
		if ($request->session()->has('permission_ticket') && $request->session()->has('redirect_uri') && $request->session()->has('client_id') && $request->session()->has('email')) {
			// If generated from rqp_claims endpoint, do this
			return redirect()->route('rqp_claims');
		} else {
			$this->oauth_authenticate($user->getEmail());
			return redirect()->route('home');
		}
	}

	public function mdnosh_register_client()
	{
		$user = DB::table('owner')->where('id', '=', '1')->first();
		$dob = date('m/d/Y', strtotime($user->DOB));
		$client_name = 'HIE of One Authorization Server for ' . $user->firstname . ' ' . $user->lastname . ' (DOB: ' . $dob . ')';
		$open_id_url = 'https://noshchartingsystem.com/openid-connect-server-webapp/';
		$url = route('mdnosh');
		$oidc = new OpenIDConnectClient($open_id_url);
		$oidc->setClientName($client_name);
		$oidc->setRedirectURL($url);
		$oidc->register();
		$client_id = $oidc->getClientID();
		$client_secret = $oidc->getClientSecret();
		$data = [
			'type' => 'mdnosh',
			'client_id' => $client_id,
			'client_secret' => $client_secret,
			'redirect_uri' => $url
		];
		DB::table('oauth_rp')->insert($data);
		return $data;
	}

	public function oauth_authenticate($email)
	{
		$user = User::where('email', '=', $email)->first();
		//$query = DB::table('oauth_users')->where('email', '=', $email)->first();
		if ($user) {
			Auth::login($user);
		}
		return true;
	}

	public function oauth_register($email)
	{

		return $response;
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
			$request->merge([
				'response_type' => $request->session()->get('response_type'),
				'redirect_uri' => $request->session()->get('redirect_uri'),
				'client_id' => $request->session()->get('client_id'),
				'nonce' => $request->session()->get('nonce'),
				'state' => $request->session()->get('state'),
				'scope' => $request->session()->get('scope')
			]);
			$bridgedRequest = BridgeRequest::createFromRequest($request);
			$bridgedResponse = new BridgeResponse();
			$bridgedResponse = App::make('oauth2')->handleAuthorizeRequest($bridgedRequest, $bridgedResponse, $request->session()->get('is_authorized'));
			return $bridgedResponse;
		} else {
			// Do client check
			$query = DB::table('oauth_clients')->where('client_id', '=', $request->input('client_id'))->first();
			if ($query) {
				if ($query->authorized == 1) {
					// Validate request
					$bridgedRequest = BridgeRequest::createFromRequest($request);
					$bridgedResponse = new BridgeResponse();
					$bridgedResponse = App::make('oauth2')->validateAuthorizeRequest($bridgedRequest, $bridgedResponse);
					if ($bridgedResponse == true) {
						// Save request input to session prior to going to login route
						session([
							'response_type' => $request->input('response_type'),
							'redirect_uri' => $request->input('redirect_uri'),
							'client_id' => $request->input('client_id'),
							'nonce' => $request->input('nonce'),
							'state' => $request->input('state'),
							'scope' => $request->input('scope')
						]);
						return redirect()->route('login');
					} else {
						return response('invalid_request', 400);
					}
				} else {
					return response('unauthorized_client', 400);
				}
			} else {
				return response('unauthorized_client', 400);
			}
		}
	}

	/**
	* Userinfo endpoint
	*
	* @return Response
	*/

	public function userinfo(Request $request)
	{
		$bridgedRequest = BridgeRequest::createFromRequest($request);
		$bridgedResponse = new BridgeResponse();
		// Fix for Laravel
		$bridgedRequest->request = new \Symfony\Component\HttpFoundation\ParameterBag();
		$rawHeaders = getallheaders();
		if (isset($rawHeaders["Authorization"])) {
		  $authorizationHeader = $rawHeaders["Authorization"];
		  $bridgedRequest->headers->add([ 'Authorization' => $authorizationHeader]);
		}
		if (App::make('oauth2')->verifyResourceRequest($bridgedRequest, $bridgedResponse)) {
			$token = App::make('oauth2')->getAccessTokenData($bridgedRequest);
			// Grab user details
			$query = DB::table('oauth_users')->where('username', '=', $token['user_id'])->first();
			return Response::json(array(
				'sub' => $token['user_id'],
				'name' => $query->first_name . ' ' . $query->last_name,
				'given_name' => $query->first_name,
				'family_name' => $query->last_name,
				'email' => $query->email,
				'picture' => $query->picture,
				'npi' => $query->npi,
				'client'  => $token['client_id'],
				'expires' => $token['expires']
			));
		} else {
			return Response::json(array('error' => 'Unauthorized'), $bridgedResponse->getStatusCode());
		}
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
		$query = DB::table('oauth_access_tokens')->where('jtw', '=', $token)->first();
		if ($query) {
			$expires = strtotime($query->expires);
			if ($expires > time()) {
				$return['active'] = true;
			}
		}
		return $return;

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
		$query = DB::table('oauth_users')->where('username', '=', $resource)->first();
		if ($query) {
			$response = [
				'subject' => $request->input('resource'),
				'links' => [
					'rel' => $rel,
					'href' => URL::to('/')
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
					} else {
						$username = $request->input('username');
					}
					// Add user
					$data = [
						'username' => $username,
						'first_name' => $query->first_name,
						'last_name' => $query->last_name,
						'email' => $query->email
					];
					DB::table('oauth_users')->insert($data);
					$data1 = [
						'email' => $query->email,
						'name' => $username
					];
					DB::table('users')->insert($data1);
					DB::table('invitation')->where('code', '=', $id)->delete();
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

	public function test1(Request $request)
	{
		$url = "https://noshchartingsystem.com/nosh/fhir/Patient/4534";
		$url_array = explode('?', $url);
		$url = $url_array[0];
		// Trim any trailing Slashes
		$url = rtrim($url, '/');
		// Check if end fragment of URL is an integer and strip it out
		$path = parse_url($url, PHP_URL_PATH);
		$pathFragments = explode('/', $path);
		$end = end($pathFragments);
		if (is_numeric($end)) {
			$pathFragments1 = explode('/', $url);
			$sliced = array_slice($pathFragments1, 0, -1);
			$url = implode('/', $sliced);
		}
		echo $url;


		// if ($request->isMethod('post')) {
		// 	return $request->all();
		// 	// if ($request->input('consent_login_md_nosh') == 'on') {
		// 	// 	echo 'yes';
		// 	// } else {
		// 	// 	echo 'no';
		// 	// }
		// } else {
		// 	$data['permissions'] = 'Test information';
		// 	return view('rs_authorize', $data);
		// }

		//return redirect('https://www.google.com/search?q=shuts+down');
		//return response()->view('auth');
	}
}
