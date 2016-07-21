<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

App::singleton('oauth2', function() {
	$storage = new OAuth2\Storage\Pdo(DB::connection()->getPdo());
	// specify your audience (typically, the URI of the oauth server)
	$issuer = env('URI', false);
	$audience = 'https://' . $issuer;
	$config['use_openid_connect'] = true;
	$config['issuer'] = $issuer;
	$config['allow_implicit'] = true;
	$config['use_jwt_access_tokens'] = true;
	// create server
	$server = new OAuth2\Server($storage, $config);
	$publicKey  = File::get(__DIR__."/../../.pubkey.pem");
	$privateKey = File::get(__DIR__."/../../.privkey.pem");
	// create storage for OpenID Connect
	$keyStorage = new OAuth2\Storage\Memory(array('keys' => array(
		'public_key'  => $publicKey,
		'private_key' => $privateKey
	)));
	$server->addStorage($keyStorage, 'public_key');
	// set grant types
	$server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
	$server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
	$server->addGrantType(new OAuth2\GrantType\AuthorizationCode($storage));
	$server->addGrantType(new OAuth2\GrantType\RefreshToken($storage));
	$server->addGrantType(new OAuth2\GrantType\JwtBearer($storage, $audience));
	return $server;
});

// Core pages
Route::any('install', array('as' => 'install', 'uses' => 'OauthController@install'));
Route::any('login', array('as' => 'login', 'uses' => 'OauthController@login'));
Route::any('logout', array('as' => 'logout', 'uses' => 'OauthController@logout'));
Route::get('home', array('as' => 'home', 'uses' => 'HomeController@index'));
Route::get('login_authorize', array('as' => 'login_authorize', 'uses' => 'HomeController@login_authorize'));
Route::get('login_authorize_action/{type}', array('as' => 'login_authorize_action', 'uses' => 'HomeController@login_authorize_action'));
Route::any('client_register', array('as' => 'client_register', 'uses' => 'OauthController@client_register'));
Route::any('oauth_login', array('as' => 'oauth_login', 'uses' => 'OauthController@oauth_login'));
Route::get('clients', array('as' => 'clients', 'uses' => 'HomeController@clients'));
Route::get('resource_view/{id}', array('as' => 'resource_view', 'uses' => 'HomeController@resource_view'));
Route::get('change_permission/{id}', array('as' => 'change_permission', 'uses' => 'HomeController@change_permission'));
Route::get('change_permission_add_edit/{id}', array('as' => 'change_permission_add_edit', 'uses' => 'HomeController@change_permission_add_edit'));
Route::get('change_permission_remove_edit/{id}', array('as' => 'change_permission_remove_edit', 'uses' => 'HomeController@change_permission_remove_edit'));
Route::get('change_permission_delete/{id}', array('as' => 'change_permission_delete', 'uses' => 'HomeController@change_permission_delete'));
Route::get('authorize_client', array('as' => 'authorize_client', 'uses' => 'HomeController@authorize_client'));
Route::get('authorize_client_action/{id}', array('as' => 'authorize_client_action', 'uses' => 'HomeController@authorize_client_action'));
Route::get('authorize_client_disable/{id}', array('as' => 'authorize_client_disable', 'uses' => 'HomeController@authorize_client_disable'));
Route::any('make_invitation', array('as' => 'make_invitation', 'uses' => 'HomeController@make_invitation'));
Route::any('accept_invitation/{id}', array('as' => 'accept_invitation', 'uses' => 'OauthController@accept_invitation'));
Route::any('process_invitation', array('as' => 'process_invitation', 'uses' => 'HomeController@process_invitation'));

Route::post('token', array('as' => 'token', function() {
	$bridgedRequest = OAuth2\HttpFoundationBridge\Request::createFromRequest(Request::instance());
	$bridgedResponse = new OAuth2\HttpFoundationBridge\Response();
	$bridgedResponse = App::make('oauth2')->handleTokenRequest($bridgedRequest, $bridgedResponse);
	return $bridgedResponse;
}));

Route::get('authorize', array('as' => 'authorize', 'uses' => 'OauthController@oauth_authorize'));

Route::get('userinfo', array('as' => 'userinfo', 'uses' => 'OauthController@userinfo'));

// Dynamic client registration
Route::post('register', array('as' => 'register', 'uses' => 'UmaController@register'));

// Requesting party claims endpoint
Route::get('rqp_claims', array('as' => 'rqp_claims', 'uses' => 'UmaController@rqp_claims'));

// Following routes need token authentiation
Route::group(['middleware' => 'token'], function() {
	// Resource set
	Route::resource('resource_set', 'ResourceSetController');

	// Policy
	Route::resource('policy', 'PolicyController');

	// Permission request
	Route::post('permission', array('as' => 'permission', 'uses' => 'UmaController@permission'));

	// Requesting party token request
	Route::post('authz_request', array('as' => 'authz_request', 'uses' => 'UmaController@authz_request'));

	// introspection
	Route::post('introspect', array('as'=> 'introspect', 'uses' => 'OauthController@introspect'));
});

// OpenID Connect relying party routes
Route::get('google', array('as' => 'google', 'uses' => 'OauthController@google_redirect'));
Route::get('account/google', array('as' => 'account/google', 'uses' => 'OauthController@google'));
Route::get('twitter', array('as' => 'twitter', 'uses' => 'OauthController@twitter_redirect'));
Route::get('account/twitter', array('as' => 'account/twitter', 'uses' => 'OauthController@twitter'));
Route::get('mdnosh', array('as' => 'mdnosh', 'uses' => 'OauthController@mdnosh'));
Route::get('installgoogle', array('as' => 'installgoogle', 'uses' => 'OauthController@installgoogle'));

// Configuration endpoints
Route::get('.well-known/openid-configuration', array('as' => 'openid-configuration', function() {
	$scopes = DB::table('oauth_scopes')->get();
	$config = [
		'grant_types_supported' => [
			'authorization_code',
			'client_credentials',
			'user_credentials',
			'implicit',
			'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'urn:ietf:params:oauth:grant_type:redelegate'
		],
		'authorization_endpoint' => URL::to('authorize'),
		'token_endpoint' => URL::to('token'),
		'userinfo_endpoint' => URL::to('userinfo'),
		'registration_endpoint' => URL::to('register'),
		'introspection_endpoint' => URL::to('introspection'),
		'scopes_supported' => $scopes,
		'issuer' => URL::to('welcome')
	];
	return $config;
}));

Route::get('.well-known/uma-configuration', function() {
	$config = [
		'pat_grant_types_supported' => [
			'authorization_code',
			'client_credentials',
			'user_credentials',
			'implicit',
			'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'urn:ietf:params:oauth:grant_type:redelegate'
		],
		'aat_grant_types_supported' => [
			'authorization_code',
			'client_credentials',
			'user_credentials',
			'implicit',
			'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'urn:ietf:params:oauth:grant_type:redelegate'
		],
		'authorization_endpoint' => URL::to('authorize'),
		'token_endpoint' => URL::to('token'),
		'userinfo_endpoint' => URL::to('userinfo'),
		'dynamic_client_endpoint' => URL::to('register'),
		'permission_registration_endpoint' => URL::to('permission'),
		'requesting_party_claims_endpoint' => URL::to('rqp_claims'),
		'resource_set_registration_endpoint' => URL::to('resource_set'),
		'policy_endpoint' => URL::to('policy'),
		'rpt_endpoint' => URL::to('authz_request'),
		'introspection_endpoint' => URL::to('introspect'),
		'aat_profiles_supported' => [
			'bearer'
		],
		'rpt_profiles_supported' => [
			'bearer'
		]
	];
	return $config;
});

// Webfinger
Route::get('.well-known/webfinger', array('as' => 'webfinger', 'uses' => 'OauthController@webfinger'));

// Update system call
Route::get('update_system', array('as' => 'update_system', 'uses' => 'OauthController@update_system'));

// test
Route::any('test1', array('as' => 'test1', 'uses' => 'OauthController@test1'));

Route::get('/', array('as' => 'welcome', function () {
	$query = DB::table('owner')->first();
	if ($query) {
		$data = [
			'name' => $query->firstname . ' ' . $query->lastname
		];
		return view('welcome', $data);
	} else {
		return redirect()->route('install');
	}
}));
