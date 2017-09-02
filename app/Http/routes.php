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

App::singleton('oauth2', function () {
    $storage = new OAuth2\Storage\Pdo(DB::connection()->getPdo());
    // specify your audience (typically, the URI of the oauth server)
    // $issuer = env('URI', false);
    $issuer = URL::to('/');
    $audience = 'https://' . $issuer;
    $config['use_openid_connect'] = true;
    $config['issuer'] = $issuer;
    $config['allow_implicit'] = true;
    $config['use_jwt_access_tokens'] = true;
    $config['refresh_token_lifetime'] = 0;
    $refresh_config['always_issue_new_refresh_token'] = false;
    $refresh_config['unset_refresh_token_after_use'] = false;
    // create server
    $server = new OAuth2\Server($storage, $config);
    $publicKey  = File::get(base_path() . "/.pubkey.pem");
    $privateKey = File::get(base_path() . "/.privkey.pem");
    // create storage for OpenID Connect
    $keyStorage = new OAuth2\Storage\Memory(['keys' => [
        'public_key'  => $publicKey,
        'private_key' => $privateKey
    ]]);
    $server->addStorage($keyStorage, 'public_key');
    // set grant types
    $server->addGrantType(new OAuth2\GrantType\ClientCredentials($storage));
    $server->addGrantType(new OAuth2\GrantType\UserCredentials($storage));
    $server->addGrantType(new OAuth2\OpenID\GrantType\AuthorizationCode($storage));
    $server->addGrantType(new OAuth2\GrantType\RefreshToken($storage, $refresh_config));
    $server->addGrantType(new OAuth2\GrantType\JwtBearer($storage, $audience));
    return $server;
});

// Core pages
Route::any('install', ['as' => 'install', 'uses' => 'OauthController@install']);
Route::get('/', ['as' => 'welcome', 'uses' => 'OauthController@welcome']);
Route::any('login', ['as' => 'login', 'uses' => 'OauthController@login']);
Route::any('logout', ['as' => 'logout', 'uses' => 'OauthController@logout']);
Route::post('login_uport', ['as' => 'login_uport', 'middleware' => 'csrf', 'uses' => 'OauthController@login_uport']);
Route::any('uport_user_add', ['as' => 'uport_user_add', 'uses' => 'OauthController@uport_user_add']);
Route::any('remote_logout', ['as' => 'remote_logout', 'uses' => 'OauthController@remote_logout']);
Route::get('home', ['as' => 'home', 'uses' => 'HomeController@index']);
Route::get('resources/{id}', ['as' => 'resources', 'uses' => 'HomeController@resources']);
Route::get('login_authorize', ['as' => 'login_authorize', 'uses' => 'HomeController@login_authorize']);
Route::get('login_authorize_action/{type}', ['as' => 'login_authorize_action', 'uses' => 'HomeController@login_authorize_action']);
Route::any('client_register', ['as' => 'client_register', 'uses' => 'OauthController@client_register']);
Route::any('oauth_login', ['as' => 'oauth_login', 'uses' => 'OauthController@oauth_login']);
Route::get('clients', ['as' => 'clients', 'uses' => 'HomeController@clients']);
Route::get('users', ['as' => 'users', 'uses' => 'HomeController@users']);
Route::get('resource_view/{id}', ['as' => 'resource_view', 'uses' => 'HomeController@resource_view']);
Route::get('change_permission/{id}', ['as' => 'change_permission', 'uses' => 'HomeController@change_permission']);
Route::get('change_permission_add_edit/{id}', ['as' => 'change_permission_add_edit', 'uses' => 'HomeController@change_permission_add_edit']);
Route::get('change_permission_remove_edit/{id}', ['as' => 'change_permission_remove_edit', 'uses' => 'HomeController@change_permission_remove_edit']);
Route::get('change_permission_delete/{id}', ['as' => 'change_permission_delete', 'uses' => 'HomeController@change_permission_delete']);
Route::get('consents_resource_server', ['as' => 'consents_resource_server', 'uses' => 'HomeController@consents_resource_server']);
Route::get('authorize_resource_server', ['as' => 'authorize_resource_server', 'uses' => 'HomeController@authorize_resource_server']);
Route::post('rs_authorize_action', ['as' => 'rs_authorize_action', 'uses' => 'HomeController@rs_authorize_action']);
Route::get('authorize_client', ['as' => 'authorize_client', 'uses' => 'HomeController@authorize_client']);
Route::get('authorize_client_action/{id}', ['as' => 'authorize_client_action', 'uses' => 'HomeController@authorize_client_action']);
Route::get('authorize_client_disable/{id}', ['as' => 'authorize_client_disable', 'uses' => 'HomeController@authorize_client_disable']);
Route::get('authorize_user', ['as' => 'authorize_user', 'uses' => 'HomeController@authorize_user']);
Route::get('authorize_user_action/{id}', ['as' => 'authorize_user_action', 'uses' => 'HomeController@authorize_user_action']);
Route::get('authorize_user_disable/{id}', ['as' => 'authorize_user_disable', 'uses' => 'HomeController@authorize_user_disable']);
Route::get('proxy_add/{sub}', ['as' => 'proxy_add', 'uses' => 'HomeController@proxy_add']);
Route::get('proxy_remove/{sub}', ['as' => 'proxy_remove', 'uses' => 'HomeController@proxy_remove']);
Route::any('make_invitation', ['as' => 'make_invitation', 'uses' => 'HomeController@make_invitation']);
Route::any('accept_invitation/{id}', ['as' => 'accept_invitation', 'uses' => 'OauthController@accept_invitation']);
Route::any('process_invitation', ['as' => 'process_invitation', 'uses' => 'HomeController@process_invitation']);
Route::any('password_email', ['as' => 'password_email', 'uses' => 'OauthController@password_email']);
Route::any('password_reset/{id}', ['as' => 'password_reset', 'uses' => 'OauthController@password_reset']);
Route::any('change_password', ['as' => 'change_password', 'uses' => 'HomeController@change_password']);
Route::get('my_info', ['as' => 'my_info', 'uses' => 'HomeController@my_info']);
Route::any('my_info_edit', ['as' => 'my_info_edit', 'uses' => 'HomeController@my_info_edit']);
Route::get('default_policies', ['as' => 'default_policies', 'uses' => 'HomeController@default_policies']);
Route::post('change_policy', ['as' => 'change_policy', 'uses' => 'HomeController@change_policy']);
Route::post('fhir_edit', ['as' => 'fhir_edit', 'middleware' => 'csrf', 'uses' => 'HomeController@fhir_edit']);
Route::post('pnosh_sync', ['as' => 'pnosh_sync', 'uses' => 'OauthController@pnosh_sync']);
Route::any('reset_demo', ['as' => 'reset_demo', 'uses' => 'OauthController@reset_demo']);
Route::any('invite_demo', ['as' => 'invite_demo', 'uses' => 'OauthController@invite_demo']);
Route::get('check_demo', ['as' => 'check_demo', 'uses' => 'OauthController@check_demo']);
Route::get('check_demo_self', ['as' => 'check_demo_self', 'middleware' => 'csrf', 'uses' => 'OauthController@check_demo_self']);

Route::post('token', ['as' => 'token', function () {
    $bridgedRequest = OAuth2\HttpFoundationBridge\Request::createFromRequest(Request::instance());
    $bridgedResponse = new OAuth2\HttpFoundationBridge\Response();
    $bridgedResponse = App::make('oauth2')->handleTokenRequest($bridgedRequest, $bridgedResponse);
    return $bridgedResponse;
}]);

Route::get('authorize', ['as' => 'authorize', 'uses' => 'OauthController@oauth_authorize']);

Route::get('jwks_uri', ['as' => 'jwks_uri', 'uses' => 'OauthController@jwks_uri']);

Route::get('userinfo', ['as' => 'userinfo', 'uses' => 'OauthController@userinfo']);

// Dynamic client registration
Route::post('register', ['as' => 'register', 'uses' => 'UmaController@register']);

// Requesting party claims endpoint
Route::get('rqp_claims', ['as' => 'rqp_claims', 'uses' => 'UmaController@rqp_claims']);

// Following routes need token authentiation
Route::group(['middleware' => 'token'], function () {
    // Resource set
    Route::resource('resource_set', 'ResourceSetController');

    // Policy
    Route::resource('policy', 'PolicyController');

    // Permission request
    Route::post('permission', ['as' => 'permission', 'uses' => 'UmaController@permission']);

    // Requesting party token request
    Route::post('authz_request', ['as' => 'authz_request', 'uses' => 'UmaController@authz_request']);

    // introspection
    Route::post('introspect', ['as'=> 'introspect', 'uses' => 'OauthController@introspect']);

    // Revocation
    Route::post('revoke', ['as' => 'revoke', 'uses' => 'OauthController@revoke']);
});

// OpenID Connect relying party routes
Route::get('google', ['as' => 'google', 'uses' => 'OauthController@google_redirect']);
Route::any('google_md/{npi?}', ['as' => 'google_md', 'uses' => 'OauthController@google_md']);
Route::any('google_md1', ['as' => 'google_md1', 'uses' => 'OauthController@google_md1']);
Route::get('account/google', ['as' => 'account/google', 'uses' => 'OauthController@google']);
Route::get('twitter', ['as' => 'twitter', 'uses' => 'OauthController@twitter_redirect']);
Route::get('account/twitter', ['as' => 'account/twitter', 'uses' => 'OauthController@twitter']);
Route::get('mdnosh', ['as' => 'mdnosh', 'uses' => 'OauthController@mdnosh']);
Route::get('installgoogle', ['as' => 'installgoogle', 'uses' => 'OauthController@installgoogle']);

// Configuration endpoints
Route::get('.well-known/openid-configuration', ['as' => 'openid-configuration', function () {
    $scopes = DB::table('oauth_scopes')->get();
    $config = [
        'issuer' => URL::to('/'),
        'grant_types_supported' => [
            'authorization_code',
            'client_credentials',
            'user_credentials',
            'implicit',
            'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'urn:ietf:params:oauth:grant_type:redelegate'
        ],
        'registration_endpoint' => URL::to('register'),
        'token_endpoint' => URL::to('token'),
        'authorization_endpoint' => URL::to('authorize'),
        'introspection_endpoint' => URL::to('introspection'),
        'userinfo_endpoint' => URL::to('userinfo'),
        'scopes_supported' => $scopes,
        'jwks_uri' => URL::to('jwks_uri'),
        'revocation_endpoint' => URL::to('revoke')
    ];
    return $config;
}]);

Route::get('.well-known/uma-configuration', function () {
    $config = [
        'issuer' => URL::to('/'),
        'pat_profiles_supported' => [
            'bearer'
        ],
        'aat_profiles_supported' => [
            'bearer'
        ],
        'rpt_profiles_supported' => [
            'bearer'
        ],
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
        // 'dynamic_client_endpoint' => URL::to('register'),
        'registration_endpoint' => URL::to('register'),
        'token_endpoint' => URL::to('token'),
        'authorization_endpoint' => URL::to('authorize'),
        'requesting_party_claims_endpoint' => URL::to('rqp_claims'),
        'resource_set_registration_endpoint' => URL::to('resource_set'),
        'introspection_endpoint' => URL::to('introspect'),
        'permission_registration_endpoint' => URL::to('permission'),
        'rpt_endpoint' => URL::to('authz_request'),
        'userinfo_endpoint' => URL::to('userinfo'),
        'policy_endpoint' => URL::to('policy'),
        'jwks_uri' => URL::to('jwks_uri')
    ];
    return $config;
});

// Webfinger
Route::get('.well-known/webfinger', ['as' => 'webfinger', 'uses' => 'OauthController@webfinger']);

// Update system call
Route::get('update_system', ['as' => 'update_system', 'uses' => 'OauthController@update_system']);

// test
Route::any('test1', ['as' => 'test1', 'uses' => 'OauthController@test1']);
