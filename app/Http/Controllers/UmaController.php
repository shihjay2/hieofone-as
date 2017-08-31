<?php
namespace App\Http\Controllers;

use App;
use App\Http\Controllers\Controller;
use App\Libraries\OpenIDConnectClient;
use App\User;
use DB;
use Illuminate\Http\Request;
use OAuth2\HttpFoundationBridge\Request as BridgeRequest;
use OAuth2\HttpFoundationBridge\Response as BridgeResponse;
use Response;
use URL;

class UmaController extends Controller
{
    /**
    * Dynamic client registration
    *
    * @return Response
    */
    public function register(Request $request)
    {
        $clientId = $this->gen_uuid();
        $clientSecret = $this->gen_secret();
        // redirect URIs with space between entries
        //$redirect_uris = 'http://test.com';
        $redirect_uris_arr = $request->input('redirect_uris');
        $redirect_uris = implode(' ', $redirect_uris_arr);
        $uma_protection = false;
        // grant types with space between entries
        if ($request->input('grant_types') == '') {
            $grant_types = 'client_credentials password authorization_code implicit jwt-bearer refresh_token';
        } else {
            $grant_types_arr = $request->input('grant_types');
            $grant_types = implode(' ', $grant_types_arr);
        }
        // $grant_types = 'client_credentials authorization_code implicit jwt-bearer refresh_token';
        // scopes with space between entries
        if ($request->input('scope') == '') {
            $scopes = 'openid profile email address phone offline_access uma_authorization';
        } else {
            $scopes = $request->input('scope');
            $scopes_array = explode(' ', $scopes);
            // check if this client is a resource server
            if (in_array('uma_protection', $scopes_array)) {
                $uma_protection = true;
            }
        }
        // Scope below for servers
        // $scopes = 'openid profile email address phone offline_access uma_protection';
        // username in oauth_users table
        $user_id = '';
        $client_uri = $request->input('client_uri');
        $claims_redirect_uris_arr = $request->input('claims_redirect_uris');
        if (empty($claims_redirect_uris_arr)) {
            $claims_redirect_uris = '';
        } else {
            $claims_redirect_uris = implode(' ', $claims_redirect_uris_arr);
        }
        // create a new client
        $data = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirect_uris,
            'grant_types' => $grant_types,
            'scope' => $scopes,
            'user_id' => $user_id,
            'logo_uri' => $request->input('logo_uri'),
            'client_name' => $request->input('client_name'),
            'client_uri' => $client_uri,
            'authorized' => 0,
            'claims_redirect_uris' => $claims_redirect_uris
        ];
        // Automatic client authorization if default policies set
        $owner = DB::table('owner')->first();
        if ($owner->login_direct == 1 || $owner->login_md_nosh == 1 || $owner->any_npi == 1 || $owner->login_google == 1) {
            $data['authorized'] = 1;
        }
        if ($uma_protection == true) {
            $data['allow_introspection'] = 1;
            // Make sure authorization owner knows that a resource server is being registered and needs authorization
            $data['authorized'] = 0;
        } else {
            $data['allow_introspection'] = 0;
        }
        DB::table('oauth_clients')->insert($data);
        $response = [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'client_id_issued_at' => time()
        ];
        // Email notification to owner to authorize the client or resource server
        if ($uma_protection == true) {
            $data1['message_data'] = 'You have a new resource server awaiting authorization on your HIE of One Authorization Server.  ';
            $data1['message_data'] .= 'Go to ' . URL::to('authorize_resource_server') . '/ to review and authorize.';
            $title = 'New Resource server Registered';
        } else {
            $data1['message_data'] = 'You have a new client awaiting authorization on your HIE of One Authorization Server.  ';
            $data1['message_data'] .= 'Go to ' . URL::to('authorize_client') . '/ to review and authorize.';
            $title = 'New Client Registered';
        }
        $to = $owner->email;
        $this->send_mail('auth.emails.generic', $data1, $title, $to);
        if ($owner->mobile != '') {
            $this->textbelt($owner->mobile, $data1['message_data']);
        }
        return $response;
    }

    /**
    * Permission request
    *
    * @return Response
    */

    public function permission(Request $request)
    {
        // Parameters
        $resource_set_id = $request->input('resource_set_id');
        $scopes_array = $request->input('scopes'); //array
        $access_lifetime = App::make('oauth2')->getConfig('access_lifetime');
        $i = count($scopes_array);
        $valid_scopes_array = [];
        $query = DB::table('resource_set_scopes')->where('resource_set_id', '=', $resource_set_id)->first();
        if ($query) {
            // Resource set exists
            foreach ($scopes_array as $scope) {
                $query1 = DB::table('resource_set_scopes')->where('resource_set_id', '=', $resource_set_id)->where('scope', '=', $scope)->first();
                if ($query1) {
                    // Scope exists for resource set and add to valid scopes array for future processing
                    $valid_scopes_array[] = $scope;
                }
            }
            $j = count($valid_scopes_array);
            if ($i = $j) {
                // Scopes request match.  Generate permission ticket
                $data1 = [
                    'resource_set_id' => $resource_set_id
                ];
                $permission_id = DB::table('permission')->insertGetId($data1);
                foreach ($valid_scopes_array as $valid_scope) {
                    $data2 = [
                        'permission_id' => $permission_id,
                        'scope' => $valid_scope
                    ];
                    DB::table('permission_scopes')->insert($data2);
                }
                $ticket = $this->gen_uuid();
                $expires = date('Y-m-d H:i:s', time() + $access_lifetime);
                $data3 = [
                    'ticket' => $ticket,
                    'permission_id' => $permission_id,
                    'expires' => $expires
                ];
                DB::table('permission_ticket')->insert($data3);
                $response = [
                    'ticket' => $ticket
                ];
            } else {
                $response = [
                    'error' => 'invalid_scope',
                    'error_description' => 'At least one of the scopes included in the request was not registered previously by this resource server.'
                ];
            }
        } else {
            $response = [
           'error' => 'invalid_resource_set_id',
           'error_description' => 'The provided resource set identifier was not found at the authorization server.'
         ];
        }
        return $response;
    }

    /**
    * Requesting party claims
    *
    * @return Response
    */

    public function rqp_claims(Request $request)
    {
        // Check if this is a redirect from OAuth2
        $error = '';
        if ($request->session()->has('uma_permission_ticket') && $request->session()->has('uma_redirect_uri') && $request->session()->has('uma_client_id') && $request->session()->has('email')) {
            $params['state'] = $request->session()->get('uma_state');
            $redirect_uri = $request->session()->get('uma_redirect_uri');
            $client_id = $request->session()->get('uma_client_id');
            $ticket = $request->session()->get('uma_permission_ticket');
            $params['ticket'] = $ticket;
            // Verify client and claims redirect uri exists
            $query0 = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
            if ($query0) {
                // Client exists and is authorized
                $scope_array = explode(' ', $query0->scope);
                if (in_array('uma_authorization', $scope_array)) {
                    // Client is allowed to access resources through UMA
                    $claims_redirect_uris_arr = explode(' ', $query0->claims_redirect_uris);
                    if (in_array($redirect_uri, $claims_redirect_uris_arr)) {
                        // Redirect URI match; check permission ticket
                        $query1 = DB::table('permission_ticket')->where('ticket', '=', $ticket)->first();
                        if ($query1) {
                            $expires = strtotime($query1->expires);
                            if ($expires > time()) {
                                // Valid ticket and gather claims
                                $resource_set = DB::table('permission')->where('permission_id', '=', $query1->permission_id)->first();
                                $policies = DB::table('policy')->where('resource_set_id', '=', $resource_set->resource_set_id)->get();
                                if ($policies) {
                                    $valid_claims_array = [];
                                    foreach ($policies as $policy) {
                                        $claims = DB::table('claim_to_policy')->where('policy_id', '=', $policy->policy_id)->get();
                                        if ($claims) {
                                            foreach ($claims as $claim) {
                                                $claim_item = DB::table('claim')->where('claim_id', '=', $claim->claim_id)->first();
                                                $valid_claims_array[] = $claim_item->claim_value;
                                            }
                                        }
                                    }
                                    if (count($valid_claims_array) > 0) {
                                        $claim_id = '';
                                        // Get default policies and get a match
                                        $rs_query1 = DB::table('resource_set')->where('resource_set_id', '=', $resource_set->resource_set_id)->first();
                                        $rs_query2 = DB::table('oauth_clients')->where('client_id', '=', $rs_query1->client_id)->first();
                                        if ($rs_query2->consent_login_direct == 1) {
                                            if ($request->session()->get('login_origin') == 'login_direct' && $claim_id == '') {
                                                $rs_query3 = DB::table('claim')->where('claim_value', '=', 'login_direct')->first();
                                                $claim_id = $rs_query3->claim_id;
                                            }
                                        }
                                        if ($rs_query2->consent_login_md_nosh == 1) {
                                            if ($request->session()->get('login_origin') == 'login_md_nosh' && $claim_id == '') {
                                                $rs_query4 = DB::table('claim')->where('claim_value', '=', 'login_md_nosh')->first();
                                                $claim_id = $rs_query4->claim_id;
                                            }
                                        }
                                        if ($rs_query2->consent_any_npi == 1) {
                                            if ($request->session()->has('npi') && $claim_id == '') {
                                                if ($request->session()->get('npi') !== '') {
                                                    $rs_query5 = DB::table('claim')->where('claim_value', '=', 'any_npi')->first();
                                                    $claim_id = $rs_query5->claim_id;
                                                }
                                            }
                                        }
                                        if ($rs_query2->consent_login_google == 1) {
                                            if ($request->session()->get('login_origin') == 'login_google' && $claim_id == '') {
                                                $rs_query6 = DB::table('claim')->where('claim_value', '=', 'login_google')->first();
                                                $claim_id = $rs_query6->claim_id;
                                            }
                                        }
                                        // Test if email matches
                                        if (in_array($request->session()->get('email'), $valid_claims_array) && $claim_id == '') {
                                            $claim1 = DB::table('claim')->where('claim_value', '=', $request->session()->get('email'))->first();
                                            $claim_id = $claim1->claim_id;
                                        }
                                        if ($claim_id !== '') {
                                            // Claims match, attach permission ticket to claim
                                            $data = [
                                                'permission_ticket_id' => $query1->permission_ticket_id,
                                                'claim_id' => $claim_id
                                            ];
                                            DB::table('claim_to_permission_ticket')->insert($data);
                                            // Set last_activity data to claim_to_policy
                                            $data1 = [
                                                'last_activity' => time()
                                            ];
                                            DB::table('claim_to_policy')->where('policy_id', '=', $policy->policy_id)->update($data1);
                                            $params['authorization_state'] = 'claims_submitted';
                                        } else {
                                            // No matching claim, not authorized$params['authorization_state'] = 'not_authorized';
                                            $params['authorization_state'] = 'not_authorized';
                                        }
                                    } else {
                                        // No claim to policy, not authorized
                                        $params['authorization_state'] = 'not_authorized';
                                    }
                                } else {
                                    // No policies, not authorized
                                    $params['authorization_state'] = 'not_authorized';
                                }
                            } else {
                                // Expired ticket
                            $params['error'] = 'invalid_request';
                            }
                        } else {
                            // Invalid ticket
                            $params['error'] = 'invalid_request';
                        }
                    } else {
                        // Redirect URI does not match
                        $error = 'Missing, invalid, or mismatching claims redirection URI';
                    }
                } else {
                    // Client does not have permissions to access resources
                    $error = 'Client identifier is missing or invalid,';
                }
            } else {
                // Client does not exist or is unauthorized
                $error = 'Client identifier is missing or invalid,';
            }
            // Clear all session data
            $request->session()->flush();
            if ($error == '') {
                $redirect_uri .= '?' . http_build_query($params, null, '&');
                return redirect($redirect_uri);
            } else {
                return response($error, 400);
            }
        } else {
            // New instance, place input into a new session
            $request->session()->put('uma_permission_ticket', $request->input('ticket'));
            $request->session()->put('uma_redirect_uri', $request->input('claims_redirect_uri'));
            $request->session()->put('uma_client_id', $request->input('client_id'));
            $request->session()->put('uma_state', $request->input('state'));
            // Let requesting party choose type of OpenID Connect service to gather claims
            return redirect()->route('login');
        }
    }

    /**
    * Requesting party token
    *
    * @return Response
    */

    public function authz_request(Request $request)
    {
        $ticket = $request->input('ticket');
        $access_lifetime = App::make('oauth2')->getConfig('access_lifetime');
        $query = DB::table('permission_ticket')->where('ticket', '=', $ticket)->first();
        if ($query) {
            $expires = strtotime($query->expires);
            if ($expires > time()) {
                // Ticket not expired, check if ticket is attached to claim
                $query1 = DB::table('claim_to_permission_ticket')->where('permission_ticket_id', '=', $query->permission_ticket_id)->first();
                if ($query1) {
                    //Valid ticket
                    $permission_id = $query->permission_id;
                    // Find client associated with permission ticket
                    $token = str_replace('Bearer ', '', $request->header('Authorization'));
                    $token_query = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
                    $client = DB::table('oauth_clients')->where('client_id', '=', $token_query->client_id)->first();
                    // Create RPT
                    $request->merge([
                        'client_id' => $client->client_id,
                        'client_secret' => $client->client_secret,
                        'grant_type' => 'client_credentials'
                    ]);
                    $bridgedRequest = BridgeRequest::createFromRequest($request);
                    $response = new BridgeResponse();
                    $response = App::make('oauth2')->grantAccessToken($bridgedRequest, $response);
                    $new_token_query = DB::table('oauth_access_tokens')->where('access_token', '=', substr($response['access_token'], 0, 255))->first();
                    $data = [
                        'permission_id' => $permission_id,
                        'jwt' => $response['access_token'],
                        'expires' => $new_token_query->expires
                    ];
                    DB::table('oauth_access_tokens')->where('access_token', '=', substr($response['access_token'], 0, 255))->update($data);
                    $response = [
                        'rpt' => $response['access_token']
                    ];
                    // Expire permission ticket
                    $expires1 = date('Y-m-d H:i:s', time());
                    $data1 = [
                        'expires' => $expires1
                    ];
                    DB::table('permission_ticket')->where('permission_id', '=', $permission_id)->update($data1);
                    $statusCode = 200;
                } else {
                    // Invalid ticket
                    $response = [
                        'error' => 'invalid_ticket'
                    ];
                    $statusCode = 400;
                }
            } else {
                // Expired ticket
                $response = [
                    'error' => 'expired_ticket'
                ];
                $statusCode = 400;
            }
        } else {
            $response = [
                'error' => 'invalid_ticket'
            ];
            $statusCode = 400;
        }
        return Response::json($response, $statusCode);
    }
}
