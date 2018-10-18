<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

use Auth;
use Config;
use DB;
use Google_Client;
use Mail;
use Session;
use Swift_Mailer;
use Swift_SmtpTransport;
use OAuth2\Response as OAuthResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Controller extends BaseController
{
	use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

	protected function activity_log($username, $action)
	{
		$data = [
			'username' => $username,
			'action' => $action,
			'created_at' => date('Y-m-d H:i:s')
		];
		DB::table('activity_log')->insert($data);
		return true;
	}

	protected function base64url_encode($data)
	{
		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
	}
	protected function changeEnv($data = []){
		if(count($data) > 0){
			// Read .env-file
			$env = file_get_contents(base_path() . '/.env');
			// Split string on every " " and write into array
			$env = preg_split('/\s+/', $env);;
			// Loop through given data
			foreach((array)$data as $key => $value){
				$new = true;
				// Loop through .env-data
				foreach($env as $env_key => $env_value){
					// Turn the value into an array and stop after the first split
					// So it's not possible to split e.g. the App-Key by accident
					$entry = explode("=", $env_value, 2);
					// Check, if new key fits the actual .env-key
					if($entry[0] == $key){
						// If yes, overwrite it with the new one
						$env[$env_key] = $key . "=" . $value;
						$new = false;
					} else {
						// If not, keep the old one
						$env[$env_key] = $env_value;
					}
				}
				if ($new == true) {
					$env[$key] = $key . "=" . $value;
				}
			}
			// Turn the array back to an String
			$env = implode("\n", $env);
			// And overwrite the .env with the new data
			file_put_contents(base_path() . '/.env', $env);
			return true;
		} else {
			return false;
		}
	}

	protected function gen_secret()
	{
		$length = 512;
		$val = '';
		for ($i = 0; $i < $length; $i++) {
			$val .= rand(0, 9);
		}
		$fp = fopen('/dev/urandom', 'rb');
		$val = fread($fp, 32);
		fclose($fp);
		$val .= uniqid(mt_rand(), true);
		$hash = hash('sha512', $val, true);
		$result = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
		return $result;
	}

	protected function gen_uuid()
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
	}

	protected function group_policy($client_id, $types, $action)
	{
		// $types is an array of claims
		$default_policy_type = $this->default_policy_type();
		// Create default policy claims if they don't exist
		foreach ($default_policy_type as $default_claim) {
			$claims = DB::table('claim')->where('claim_value', '=', $default_claim)->first();
			if (!$claims) {
				$claims_data = [
					'name' => 'Group',
					'claim_value' => $default_claim
				];
				DB::table('claim')->insert($claims_data);
			}
		}
		// Find all existing default polices for the resource server
		$default_policies_old_array = [];
		$resource_set_id_array = [];
		$policies_array = [];
		$resource_sets = DB::table('resource_set')->where('client_id', '=', $client_id)->get();
		if ($resource_sets->count()) {
			foreach ($resource_sets as $resource_set) {
				$resource_set_id_array[] = $resource_set->resource_set_id;
				$policies = DB::table('policy')->where('resource_set_id', '=', $resource_set->resource_set_id)->get();
				if ($policies->count()) {
					foreach ($policies as $policy) {
						$policies_array[] = $policy->policy_id;
						$query1 = DB::table('claim_to_policy')->where('policy_id', '=', $policy->policy_id)->get();
						if ($query1->count()) {
							foreach ($query1 as $row1) {
								$query2 = DB::table('claim')->where('claim_id', '=', $row1->claim_id)->first();
								if ($query2) {
									if (in_array($query2->claim_value, $default_policy_type)) {
										$default_policies_old_array[] = $policy->policy_id;
									}
								}
							}
						}
					}
				}
			}
		}
		// Remove all existing default policy scopes to refresh them, delete them all if action is to delete
		if (count($default_policies_old_array) > 0) {
			foreach ($default_policies_old_array as $old_policy_id) {
				DB::table('policy_scopes')->where('policy_id', '=', $old_policy_id)->delete();
				DB::table('claim_to_policy')->where('policy_id', '=', $old_policy_id)->delete();
				if ($action == 'delete') {
					DB::table('policy')->where('policy_id', '=', $old_policy_id)->delete();
				}
			}
		}
		if ($action !== 'delete') {
			// Identify resource sets without policies and create new policies
			// Get all resource set scopes to default policies
			if (count($resource_set_id_array) > 0) {
				foreach ($resource_set_id_array as $resource_set_id) {
					$query3 = DB::table('policy')->where('resource_set_id', '=', $resource_set_id)->first();
					if ($query3) {
						// Check if there is an existing policy with this resource set and attach all scopes these policies
						$policies1 = DB::table('policy')->where('resource_set_id', '=', $resource_set_id)->get();
						if ($policies1->count()) {
							foreach ($policies1 as $policy1) {
								if (in_array($policy1->policy_id, $default_policies_old_array)) {
									foreach ($types as $type) {
										$query4 = DB::table('claim')->where('claim_value', '=', $type)->first();
										$data1 = [
										  'claim_id' => $query4->claim_id,
										  'policy_id' => $policy1->policy_id
										];
										DB::table('claim_to_policy')->insert($data1);
									}
									$resource_set_scopes = DB::table('resource_set_scopes')->where('resource_set_id', '=', $resource_set_id)->get();
									if ($resource_set_scopes->count()) {
										foreach ($resource_set_scopes as $resource_set_scope) {
											$data2 = [
												'policy_id' => $policy1->policy_id,
												'scope' => $resource_set_scope->scope
											];
											DB::table('policy_scopes')->insert($data2);
										}
									}
								}
							}
						}
					} else {
						// Needs new policy
						$data3['resource_set_id'] = $resource_set_id;
						$policy_id = DB::table('policy')->insertGetId($data3);
						foreach ($types as $type1) {
							$query5 = DB::table('claim')->where('claim_value', '=', $type1)->first();
							$data4 = [
							  'claim_id' => $query5->claim_id,
							  'policy_id' => $policy_id
							];
							DB::table('claim_to_policy')->insert($data4);
						}
						$resource_set_scopes1 = DB::table('resource_set_scopes')->where('resource_set_id', '=', $resource_set_id)->get();
						if ($resource_set_scopes1->count()) {
							foreach ($resource_set_scopes1 as $resource_set_scope1) {
								$data5 = [
									'policy_id' => $policy_id,
									'scope' => $resource_set_scope1->scope
								];
								DB::table('policy_scopes')->insert($data5);
							}
						}
					}
				}
			}
		}
	}

	protected function login_sessions($user, $client_id)
	{
		$owner_query = DB::table('owner')->first();
		$proxies = DB::table('owner')->where('sub', '!=', $owner_query->sub)->get();
		$proxy_arr = [];
		if ($proxies->count()) {
			foreach ($proxies as $proxy_row) {
				$proxy_arr[] = $proxy_row->sub;
			}
		}
		$client = DB::table('oauth_clients')->where('client_id', '=', $client_id)->first();
		Session::put('client_id', $client_id);
		Session::put('owner', $owner_query->firstname . ' ' . $owner_query->lastname);
		Session::put('username', $user->username);
		Session::put('full_name', $user->first_name . ' ' . $user->last_name);
		Session::put('client_name', $client->client_name);
		Session::put('logo_uri', $client->logo_uri);
		Session::put('sub', $user->sub);
		Session::put('email', $user->email);
		Session::put('invite', 'no');
		Session::put('is_owner', 'no');
		if ($user->sub == $owner_query->sub || in_array($user->sub, $proxy_arr)) {
			Session::put('is_owner', 'yes');
		}
		if ($owner_query->sub == $user->sub) {
			Session::put('invite', 'yes');
		}
		Session::save();
		return true;
	}

	protected function login_uport_common($uport_user)
	{
		if (Session::get('oauth_response_type') == 'code') {
			$client_id = Session::get('oauth_client_id');
		} else {
			$client = DB::table('owner')->first();
			$client_id = $client->client_id;
		}
		Session::put('login_origin', 'login_direct');
		$user = DB::table('users')->where('email', '=', $uport_user->email)->first();
		$this->login_sessions($uport_user, $client_id);
		Auth::loginUsingId($user->id);
		$this->activity_log($uport_user->email, 'Login - uPort');
		$this->notify($uport_user);
		Session::save();
		$return['message'] = 'OK';
		if (Session::has('uma_permission_ticket') && Session::has('uma_redirect_uri') && Session::has('uma_client_id') && Session::has('email')) {
			// If generated from rqp_claims endpoint, do this
			$return['url'] = route('rqp_claims');
		} elseif (Session::get('oauth_response_type') == 'code') {
			// Confirm if client is authorized
			$authorized = DB::table('oauth_clients')->where('client_id', '=', $client_id)->where('authorized', '=', 1)->first();
			if ($authorized) {
				// This call is from authorization endpoint and client is authorized.  Check if user is associated with client
				$user_array = explode(' ', $authorized->user_id);
				if (in_array($uport_user->username, $user_array)) {
					// Go back to authorize route
					Session::put('is_authorized', 'true');
					$return['url'] = route('authorize');
				} else {
					// Get user permission
					$return['url'] = route('login_authorize');
				}
			} else {
				// Get owner permission if owner is logging in from new client/registration server
				if ($oauth_user) {
					if ($owner_query->sub == $uport_user->sub) {
						$return['url'] = route('authorize_resource_server');
					} else {
						// Somehow, this is a registered user, but not the owner, and is using an unauthorized client - return back to login screen
						$return['message'] = 'Unauthorized client.  Please contact the owner of this authorization server for assistance.';
					}
				} else {
					// Not a registered user
					$return['message'] = 'Not a registered user.  Please contact the owner of this authorization server for assistance.';
				}
			}
		} else {
			//  This call is directly from the home route.
			$return['url'] = route('consent_table');
		}
		return $return;
	}

	protected function notify($user)
	{
		if ($user->notify == 1) {
			$owner = DB::table('owner')->first();
			$data['message_data'] = 'This is a notification that ' . $user->first_name . ' ' . $user->last_name . '(' . $user->email . ') just accessed your Trustee Authorization Server.';
			$title = 'User Notification from your Trustee Authorization Server';
			$to = $owner->email;
			$this->send_mail('auth.emails.generic', $data, $title, $to);
			if ($owner->mobile != '') {
				$this->textbelt($owner->mobile, $data['message_data']);
			}
		}
	}

	protected function npi_lookup($first, $last='')
	{
		$url = 'https://npiregistry.cms.hhs.gov/api/?';
		$fields_arr = [
			'number' => '',
			'first_name' => '',
			'last_name' => '',
			'enumeration_type' => '',
			'taxonomy_description' => '',
			'organization_name' => '',
			'address_purpose' => '',
			'city' => '',
			'state' => '',
			'postal_code' => '',
			'country_code' => '',
			'limit' => '',
			'skip' => ''
		];
		if ($last == '') {
			$fields_arr['number'] = $first;
		} else {
			$fields_arr['first_name'] = $first;
			$fields_arr['last_name'] = $last;
		}
		$fields = http_build_query($fields_arr);
		$url .= $fields;
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_FAILONERROR,1);
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_TIMEOUT, 15);
		$json = curl_exec($ch);
		curl_close($ch);
		$arr = json_decode($json, true);
		return $arr;
	}

	protected function oidc_relay($param, $status=false)
    {
        $pnosh_url = url('/');
        $pnosh_url = str_replace(array('http://','https://'), '', $pnosh_url);
        $root_url = explode('/', $pnosh_url);
        $root_url1 = explode('.', $root_url[0]);
        $final_root_url = $root_url1[1] . '.' . $root_url1[2];
        if ($pnosh_url == 'shihjay.xyz') {
            $final_root_url = 'hieofone.org';
        }
        if ($final_root_url == 'hieofone.org') {
            $relay_url = 'https://dir.' . $final_root_url . '/oidc_relay';
            if ($status == false) {
                $state = md5(uniqid(rand(), TRUE));
                $relay_url = 'https://dir.' . $final_root_url . '/oidc_relay';
            } else {
                $state = '';
                $relay_url = 'https://dir.' . $final_root_url . '/oidc_relay/' . $param['state'];
            }
            $root_param = [
                'root_uri' => $root_url[0],
                'state' => $state,
                'origin_uri' => '',
                'response_uri' => '',
                'fhir_url' => '',
                'fhir_auth_url' => '',
                'fhir_token_url' => '',
                'type' => '',
                'cms_pid' => '',
                'refresh_token' => ''
            ];
            $params = array_merge($root_param, $param);
            $post_body = json_encode($params);
            $content_type = 'application/json';
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL, $relay_url);
            if ($status == false) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: {$content_type}",
                    'Content-Length: ' . strlen($post_body)
                ]);
            }
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch,CURLOPT_FAILONERROR,1);
            curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_TIMEOUT, 60);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,0);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close ($ch);
            if ($httpCode !== 404 && $httpCode !== 0) {
                if ($status == false) {
                    $return['message'] = $response;
                    $return['url'] = $relay_url . '_start/' . $state;
                    $return['state'] = $state;
                } else {
                    $response1 = json_decode($response, true);
                    if (isset($response1['error'])) {
                        $return['message'] = $response1['error'];
                    } else {
                        $return['message'] = 'Tokens received';
                        $return['tokens'] = $response1;
                    }
                }
            } else {
                $return['message'] = 'Error: unable to connect to the relay.';
            }
        } else {
            $return['message'] = 'Not supported.';
        }
        return $return;
    }

	protected function send_mail($template, $data_message, $subject, $to)
	{
		// $google_client = DB::table('oauth_rp')->where('type', '=', 'google')->first();
		// $google = new Google_Client();
		// $google->setClientID($google_client->client_id);
		// $google->setClientSecret($google_client->client_secret);
		// $google->refreshToken($google_client->refresh_token);
		// $credentials = $google->getAccessToken();
		// $username = $google_client->smtp_username;
		// $password = $credentials['access_token'];
		// $config = [
		//     'mail.driver' => 'smtp',
		//     'mail.host' => 'smtp.gmail.com',
		//     'mail.port' => 465,
		//     'mail.from' => ['address' => null, 'name' => null],
		//     'mail.encryption' => 'ssl',
		//     'mail.username' => $username,
		//     'mail.password' => $password,
		//     'mail.sendmail' => '/usr/sbin/sendmail -bs'
		// ];
		// config($config);
		// extract(Config::get('mail'));
		// $transport = Swift_SmtpTransport::newInstance($host, $port, 'ssl');
		// $transport->setAuthMode('XOAUTH2');
		// if (isset($encryption)) {
		//     $transport->setEncryption($encryption);
		// }
		// if (isset($username)) {
		//     $transport->setUsername($username);
		//     $transport->setPassword($password);
		// }
		// Mail::setSwiftMailer(new Swift_Mailer($transport));
		$owner = DB::table('owner')->first();
		Mail::send($template, $data_message, function ($message) use ($to, $subject, $owner) {
			$message->to($to)
				->from($owner->email, $owner->firstname . ' ' . $owner->lastname)
				->subject($subject);
		});
		return "E-mail sent.";
	}

	protected function default_policy_type($key=true)
	{
		$return = [
			'login_direct' => [
				'label' => 'Anyone signed-in directly to this Authorization Server sees and controls Everything',
				'description' => '<p>Any party that you invite directly to be an authorized user to this Authorization Server has access to your Protected Health Information (PHI).  For example, this can be a spouse, partner, family members, guardian, or attorney.</p>'
			],
			// 'login_md_nosh',
			'any_npi' => [
				'label' => 'Anyone that has a Doximity-verfied National Provider Identifier (NPI) sees these Resources',
				'description' => '<p>All individual HIPAA covered healthcare providers have a National Provider Identifier, including:</p>
					<ul>
						<li>Physicians</li>
						<li>Pharmacists</li>
						<li>Physician assistants</li>
						<li>Midwives</li>
						<li>Nurse practitioners</li>
						<li>Nurse anesthetists</li>
						<li>Dentsits</li>
						<li>Denturists</li>
						<li>Chiropractors</li>
						<li>Podiatrists</li>
						<li>Naturopathic physicians</li>
						<li>Clinical social workers</li>
						<li>Professional counselors</li>
						<li>Psychologists</li>
						<li>Physical therapists</li>
						<li>Occupational therapists</li>
						<li>Pharmacy technicians</li>
						<li>Atheletic trainers</li>
					</ul>
					<p>By setting this as a default, you allow any healthcare provider, known or unknown at any given time, to access and edit your protected health information.</p>'
			],
			// 'login_google',
			// 'login_uport',
			'public_publish_directory' => [
				'label' => 'Anyone can see and link to your Trustee in a Directory',
				'description' => '<p>Any party that has access to a Directory that you participate in can see where this resource is found.</p>'
			],
			// 'private_publish_directory' => [
			// 	'label' => 'Only previously authorized users can see where this resource is located in a Directory',
			// 	'description' => '<p>Only previously authorized users that has access to a Directory that you participate in can see where this resource is found.</p>'
			// ],
			'last_activity' => [
				'label' => 'Publish the most recent date and time when this Trustee was active to a Directory',
				'description' => '<p>Activity of a Trustee refers to when a client attempts to view/edit a resource, adding/updating/removing a resource server, or registering new clients.</p>'
			]
		];
		if ($key == true) {
			return array_keys($return);
		} else {
			return $return;
		}
	}

	protected function user_policies()
	{
		$return = [
			'all' => [
				'name' => 'All',
				'type' => 'all',
				'description' => 'Any authorized user to this Authorization Server given this policy has read and write access to your Protected Health Information (PHI).'
			],
			'read_only' => [
				'name' => 'Read Only',
				'type' => 'scope',
				'parameter' => 'view',
				'description' => 'Any authorized user to this Authorization Server given this policy has read-only access to your Protected Health Information (PHI).'
			],
			'allergies_and_medications' => [
				'name' => 'Allergies and Medications',
				'type' => 'fhir',
				'parameter' => 'AllergyIntolerance',
				'description' => 'Any authorized user to this Authorization Server given this policy can only view Allergies and Medications in your health record.'
			],
			'care_team_list' => [
				'name' => 'Care Team List',
				'type' => 'fhir',
				'parameter' => 'CareTeam',
				'description' => 'Any authorized user to this Authorization Server given this policy belongs on your clinical care team'
			]
		];
		return $return;
	}

	protected function add_user_policies($email, $policies)
	{
		$data = [
            'name' => 'email',
            'claim_value' => $email
        ];
        $claim_id = DB::table('claim')->insertGetId($data);
		foreach ($policies as $policy) {
			$query = DB::table('policy')->where('name', '=', $policy)->get();
	        if ($query->count()) {
	            foreach ($query as $row) {
	                $data1 = [
	                    'claim_id' => $claim_id,
	                    'policy_id' => $row->policy_id
	                ];
	                DB::table('claim_to_policy')->insert($data1);
	            }
	        }
		}
		return true;
	}

	protected function default_user_policies_create()
	{
		// Scan all resources
		$resources = DB::table('resource_set')->get();
		if ($resources->count()) {
			foreach ($resources as $resource) {
				$resource_data['resource_set_id'] = $resource->resource_set_id;
				$user_policies = $this->user_policies();
				$resource_scopes = DB::table('resource_set_scopes')->where('resource_set_id', '=', $resource->resource_set_id)->get();
				foreach ($user_policies as $user_policy_k => $user_policy_v) {
					if ($user_policy_v['type'] !== 'all') {
						$policy = DB::table('policy')->where('name', '=', $user_policy_v['name'])->where('resource_set_id', '=', $resource->resource_set_id)->first();
						if (!$policy) {
							$policy_id = DB::table('policy')->insertGetId(['resource_set_id' => $resource->resource_set_id, 'name' => $user_policy_v['name']]);
							// Scan all resource scopes for the resource set and place appropriate polices
							if ($resource_scopes->count()) {
								foreach ($resource_scopes as $resource_scope) {
									$scope_arr = explode('/', $resource_scope->scope);
									$policy_scope_data = [
										'policy_id' => $policy_id,
										'scope' => $resource_scope->scope
									];
									// All default user polices check for Patient
									if (in_array('Patient', $scope_arr)) {
										DB::table('policy_scopes')->insert($policy_scope_data);
									}
									if ($user_policy_v['type'] == 'scope') {
										if ($user_policy_v['parameter'] == $resource_scope->scope) {
											DB::table('policy_scopes')->insert($policy_scope_data);
										}
									}
									if ($user_policy_v['type'] == 'fhir') {
										if (in_array($user_policy_v['parameter'], $scope_arr)) {
											DB::table('policy_scopes')->insert($policy_scope_data);
										}
									}
								}
							}
						}
					}
				}
			}
		}
		// Build exisitng user claims
		$users = DB::table('oauth_users')->where('password', '!=', 'Pending')->get();
		foreach ($users as $user) {
			$user_query = DB::table('claim')->where('claim_value', '=', $user->email)->first();
			if (!$user_query) {
				$data = [
					'name' => 'email',
					'claim_value' => $user->email
				];
				DB::table('claim')->insert($data);
			}
		}
		// Build custom polices
		$policy_query = DB::table('custom_policy')->get();
		if (!$policy_query->count()) {
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
            }
		}
	}

	protected function custom_policies_edit($name, $type, $parameters=[], $action='')
	{
		$policies = DB::table('policy')->where('name', '=', $name)->get();
		$policy_id_arr = [];
		if ($polices->count()) {
			foreach ($polices as $policy) {
				// Clear all scopes if they exist
				DB::table('policy_scopes')->where('policy_id', '=', $policy->policy_id)->delete();
				$policy_id_arr[] = [
					'policy_id' => $policy->policy_id,
					'resource_set_id' => $policy->resource_set_id
				];
			}
		} else {
			$resources = DB::table('resource_set')->get();
			if ($resources->count()) {
				foreach ($resources as $resource) {
					$policy_id1 = DB::table('policy')->insertGetId(['resource_set_id' => $resource->resource_set_id, 'name' => $name]);
					$policy_id_arr[] = [
						'policy_id' => $policy_id1,
						'resource_set_id' => $resource->resource_set_id
					];
				}
			}
		}
		if ($action == 'delete') {
			DB::table('policy')->where('name', '=', $name)->delete();
		} else {
			foreach ($policy_id_arr as $policy_id_k => $policy_id_v) {
				foreach ($parameters as $parameter) {
					$resource_scopes = DB::table('resource_set_scopes')->where('resource_set_id', '=', $policy_id_v['resource_set_id'])->get();
					foreach ($resource_scopes as $resource_scope) {
						$scope_arr = explode('/', $resource_scope->scope);
						$policy_scope_data = [
							'policy_id' => $policy_id_v['policy_id'],
							'scope' => $resource_scope->scope
						];
						if ($type == 'scope-include') {
							if ($parameter == $resource_scope->scope) {
								DB::table('policy_scopes')->insert($policy_scope_data);
							}
						}
						if ($type == 'scope-exclude') {
							if ($parameter !== $resource_scope->scope) {
								DB::table('policy_scopes')->insert($policy_scope_data);
							}
						}
						if ($type == 'all') {
							DB::table('policy_scopes')->insert($policy_scope_data);
						}
						if ($type == 'fhir') {
							if (in_array($parameter, $scope_arr)) {
								DB::table('policy_scopes')->insert($policy_scope_data);
							}
						}
					}
				}
			}
		}
	}

	protected function directory_api($pre_url, $params, $action='directory_registration', $id='1')
	{
		$url =  $pre_url . '/check';
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
			Session::put('directory_uri', $pre_url . '/');
			$endpoint = $pre_url . '/'. $action;
			if ($action == 'directory_update' || $action == 'directory_remove') {
				$endpoint .= '/' . $id;
			}
			$post_body = json_encode($params);
			$content_type = 'application/json';
			$ch1 = curl_init();
			curl_setopt($ch1,CURLOPT_URL, $endpoint);
			curl_setopt($ch1, CURLOPT_POST, 1);
			curl_setopt($ch1, CURLOPT_POSTFIELDS, $post_body);
			curl_setopt($ch1, CURLOPT_HTTPHEADER, [
				"Content-Type: {$content_type}",
				'Content-Length: ' . strlen($post_body)
			]);
			curl_setopt($ch1, CURLOPT_HEADER, 0);
			curl_setopt($ch1, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch1, CURLOPT_TIMEOUT, 60);
			curl_setopt($ch1, CURLOPT_CONNECTTIMEOUT ,0);
			$output = curl_exec($ch1);
			$response['status'] = 'OK';
			$response['arr'] = json_decode($output, true);
			curl_close ($ch1);
		} else {
			$response['status'] = 'error';
			if ($action == 'directory_registration') {
				$response['message'] = 'The URL provided is not valid.';
			} else {
				$response['message'] = 'Directory URL does not exist';
			}
		}
		return $response;
	}

	protected function directory_update_api()
    {
		$response1 = [];
        $as_url = url('/');
        $owner = DB::table('owner')->first();
		$user = DB::table('oauth_users')->where('sub', '=', $owner->sub)->first();
		$query = DB::table('directories')->get();
        $rs = DB::table('oauth_clients')->where('authorized', '=', 1)->where('scope', 'LIKE', "%uma_protection%")->get();
		if ($query->count()) {
			foreach ($query as $directory) {
		        $rs_arr = [];
		        if ($rs) {
		            foreach ($rs as $rs_row) {
						$rs_to_directory = DB::table('rs_to_directory')->where('client_id', '=', $rs_row->client_id)->where('directory_id', '=', $directory->directory_id)->first();
						if ($rs_to_directory) {
							$public = $rs_to_directory->consent_public_publish_directory;
							$private = $rs_to_directory->consent_private_publish_directory;
							$last_activity = $rs_to_directory->consent_last_activity;
						} else {
							$public = $rs_row->consent_public_publish_directory;
							$private = $rs_row->consent_private_publish_directory;
							$last_activity = $rs_row->consent_last_activity;
							$rs_to_directory_data = [
	                            'directory_id' => $directory->directory_id,
	                            'client_id' => $rs_row->client_id,
	                            'consent_public_publish_directory' => $public,
	                            'consent_private_publish_directory' => $private,
								'consent_last_activity' => $last_activity
	                        ];
	                        DB::table('rs_to_directory')->insert($rs_to_directory_data);
						}
		                $rs_arr[] = [
		                    'name' => $rs_row->client_name,
		                    'uri' => $rs_row->client_uri,
		                    'public' => $public,
		                    'private' => $private,
							'last_activity' => $last_activity
		                ];
		            }
		        }
				$params = [
		            'as_uri' => $as_url,
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
				$url = rtrim($directory->uri, '/');
				$response = $this->directory_api($url, $params, 'directory_update', $directory->directory_id);
                $response1[] = $directory->name . ': ' . $response['arr']['message'];
			}
		}
        return $response1;
    }

	protected function policy_build($set=false)
	{
		$arr = $this->default_policy_type(false);
		$return = '';
		if ($set == false) {
			$query = DB::table('owner')->first();
		} else {
			$query = DB::table('oauth_clients')->where('client_id', '=', Session::get('current_client_id'))->first();
		}
		foreach ($arr as $row_k => $row_v) {
			$return .= '<div class="form-group"><div class="col-sm-offset-2 col-sm-10"><div class="checkbox"><label>';
			$return .= '<input type="checkbox" name="consent_' . $row_k . '"';
			if ($set == false) {
				$val = $query->{$row_k};
			} else {
				$consent = 'consent_' . $row_k;
				$val = $query->{$consent};
			}
			if ($val == 1) {
				$return .= ' checked';
			}
			$return .= '>' . $row_v['label'] . '</label><button type="button" class="btn btn-info" data-toggle="collapse" data-target="#' . $row_k . '_detail" style="margin-left:20px">Details</button></div>';
			$return .= '<div id="' . $row_k . '_detail" class="collapse">' . $row_v['description'] . '</div>';
			$return .= '</div></div>';
		}
		return $return;
	}

	protected function roles()
	{
		$arr = [
			'',
			'Admin',
			'Clinician',
			'Directory',
			'Family',
			'Custom'
		];
		return $arr;
	}

	protected function roles_build($value='Clinician')
	{
		$arr = $this->roles();
		$return = '';
		foreach ($arr as $row) {
			$return .= '<option value="' . $row . '"';
			if ($value == $row) {
				$return .= ' selected="selected"';
			}
			$return .= '>' . $row . '</option>';
		}
		return $return;
	}

	protected function certifier_roles()
	{
		$arr = [
			'Read Only' => [
				'description' => 'This Certifier verfies the authenticity of an individual, and is only able to read data about that individual.'
			],
			'Clinician' => [
				'description' => 'This Certifier verfies the authenticity of the clinician.'
			],
			'Family' => [
				'description' => 'This Certifier verifies the authenticity of the individual who is a family member.'
			],
			'Custom Role' => [
				'description' => 'This Certifier has a specific, custom role as indicated in the table.'
			]
		];
		return $arr;
	}

	protected function certifier_default()
	{
		$arr = [
			'Doximity' => [
				'roles' => ['Clinician'],
				'badges' => ['uPort', 'OIDC']
			],
			'Google' => [
				'roles' => ['Read Only', 'Family'],
				'badges' => ['OIDC']
			],
			'Emory Healthcare' => [
				'roles' => ['Clinician'],
				'badges' => ['OIDC']
			],
			'Homeless Service' => [
				'roles' => ['Custom Role'],
				'custom_role' => 'Navigator',
				'badges' => ['uPort']
			]
		];
		return $arr;
	}

	protected function query_custom_polices()
	{
		$arr = [];
		$query = DB::table('custom_policy')->get();
		if ($query->count()) {
			foreach ($query as $row) {
				$arr[] = [
					'name' => $row->name,
					'type' => $row->type,
					'parameter' => $row->parameters
				];
			}
		}
		// $arr[] = [
		// 	'name' => 'No sensitive',
		// 	'type' => 'scope-exclude',
		// 	'parameter' => 'sens/*'
		// ];
		// $arr[] = [
		// 	'name' => 'Write Notes',
		// 	'type' => 'scope-include',
		// 	'parameter' => 'view,edit'
		// ];
		// $arr[] = [
		// 	'name' => 'Everything',
		// 	'type' => 'all',
		// 	'parameter' => ''
		// ];
		// $arr[] = [
		// 	'name' => 'Consenter',
		// 	'type' => 'fhir',
		// 	'parameter' => ''
		// ];
		return $arr;
	}

	protected function custom_policy_build($value='')
	{
		$arr = $this->query_custom_polices();
		$return = '';
		foreach ($arr as $row) {
			$return .= '<option value="' . $row['name'] . '"';
			if ($value == $row['name']) {
				$return .= ' selected="selected"';
			}
			$return .= '>' . $row['name'] . '</option>';
		}
		return $return;
	}

	protected function fhir_resources()
    {
        $return = [
            'Condition' => [
                'icon' => 'fa-bars',
                'name' => 'Conditions',
                'table' => 'issues',
                'order' => 'issue'
            ],
            'MedicationStatement' => [
                'icon' => 'fa-eyedropper',
                'name' => 'Medications',
                'table' => 'rx_list',
                'order' => 'rxl_medication'
            ],
            'AllergyIntolerance' => [
                'icon' => 'fa-exclamation-triangle',
                'name' => 'Allergies',
                'table' => 'allergies',
                'order' => 'allergies_med'
            ],
            'Immunization' => [
                'icon' => 'fa-magic',
                'name' => 'Immunizations',
                'table' => 'immunizations',
                'order' => 'imm_immunization'
            ],
            'Patient' => [
                'icon' => 'fa-user',
                'name' => 'Patient Information',
                'table' => 'demographics',
                'order' => 'pid'
            ],
            'Encounter' => [
                'icon' => 'fa-stethoscope',
                'name' => 'Encounters',
                'table' => 'encounters',
                'order' => 'encounter_cc'
            ],
            'FamilyHistory' => [
                'icon' => 'fa-sitemap',
                'name' => 'Family History',
                'table' => 'other_history',
                'order' => 'oh_fh'
            ],
            'Binary' => [
                'icon' => 'fa-file-text',
                'name' => 'Documents',
                'table' => 'documents',
                'order' => 'documents_desc'
            ],
            'Observation' => [
                'icon' => 'fa-flask',
                'name' => 'Observations',
                'table' => 'tests',
                'order' => 'test_name'
            ]
        ];
        return $return;
    }

	protected function fhir_scopes_confidentiality()
    {
        $arr = [
            'conf/N' => 'Normal confidentiality',
            'conf/R' => 'Restricted confidentiality',
            'conf/V' => 'Very Restricted confidentiality'
        ];
        return $arr;
    }

    protected function fhir_scopes_sensitivities()
    {
        $arr = [
            'sens/ETH' => 'Substance abuse',
            'sens/PSY' => 'Psychiatry',
            'sens/GDIS' => 'Genetic disease',
            'sens/HIV' => 'HIV/AIDS',
            'sens/SCA' => 'Sickle cell anemia',
            'sens/SOC' => 'Social services',
            'sens/SDV' => 'Sexual assault, abuse, or domestic violence',
            'sens/SEX' => 'Sexuality and reproductive health',
            'sens/STD' => 'Sexually transmitted disease',
            'sens/DEMO' => 'All demographic information',
            'sens/DOB' => 'Date of birth',
            'sens/GENDER' => 'Gender and sexual orientation',
            'sens/LIVARG' => 'Living arrangement',
            'sens/MARST' => 'Marital status',
            'sens/RACE' => 'Race',
            'sens/REL' => 'Religion',
            'sens/B' => 'Business information',
            'sens/EMPL' => 'Employer',
            'sens/LOCIS' => 'Location',
            'sens/SSP' => 'Sensitive service provider',
            'sens/ADOL' => 'Adolescent',
            'sens/CEL' => 'Celebrity',
            'sens/DIAG' => 'Diagnosis',
            'sens/DRGIS' => 'Drug information',
            'sens/EMP' => 'Employee'
        ];
        return $arr;
    }

	/**
	* SMS notifcation with TextBelt
	*
	* @return Response
	*/
	protected function textbelt($number, $message)
	{
		$url = "http://cloud.noshchartingsystem.com:9090/text";
		$message = http_build_query([
			'number' => $number,
			'message' => $message
		]);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close($ch);
		return $output;
	}

	/**
	* Helper library for CryptoJS AES encryption/decryption
	* Allow you to use AES encryption on client side and server side vice versa
	*
	* @author BrainFooLong (bfldev.com)
	* @link https://github.com/brainfoolong/cryptojs-aes-php
	*/
	/**
	* Decrypt data from a CryptoJS json encoding string
	*
	* @param mixed $passphrase
	* @param mixed $jsonString
	* @return mixed
	*/
	protected function cryptoJsAesDecrypt($passphrase, $jsonString)
	{
		$jsondata = json_decode($jsonString, true);
		try {
			$salt = hex2bin($jsondata["s"]);
			$iv  = hex2bin($jsondata["iv"]);
		} catch(Exception $e) { return null; }
		$ct = base64_decode($jsondata["ct"]);
		$concatedPassphrase = $passphrase.$salt;
		$md5 = array();
		$md5[0] = md5($concatedPassphrase, true);
		$result = $md5[0];
		for ($i = 1; $i < 3; $i++) {
			$md5[$i] = md5($md5[$i - 1].$concatedPassphrase, true);
			$result .= $md5[$i];
		}
		$key = substr($result, 0, 32);
		$data = openssl_decrypt($ct, 'aes-256-cbc', $key, true, $iv);
		return json_decode($data, true);
	}
	/**
	* Encrypt value to a cryptojs compatiable json encoding string
	*
	* @param mixed $passphrase
	* @param mixed $value
	* @return string
	*/
	protected function cryptoJsAesEncrypt($passphrase, $value)
	{
		$salt = openssl_random_pseudo_bytes(8);
		$salted = '';
		$dx = '';
		while (strlen($salted) < 48) {
			$dx = md5($dx.$passphrase.$salt, true);
			$salted .= $dx;
		}
		$key = substr($salted, 0, 32);
		$iv  = substr($salted, 32,16);
		$encrypted_data = openssl_encrypt(json_encode($value), 'aes-256-cbc', $key, true, $iv);
		$data = array("ct" => base64_encode($encrypted_data), "iv" => bin2hex($iv), "s" => bin2hex($salt));
		return json_encode($data);
	}

	/**
	 * Laravel uses the HttpFoundation library's implementation of HTTP requests & responses. However, the OAuth server
	 * impl we use doesn't return them in the right form. The creator of the server provides a bridge implementation
	 * at https://github.com/bshaffer/oauth2-server-httpfoundation-bridge but it breaks with Symfony 4 (bug
	 * https://github.com/bshaffer/oauth2-server-httpfoundation-bridge/issues/31) Therefore, we do our own conversion
	 * for the HTTP responses (using request conversion from the library still), which seems to work.
	 *
	 * @param OAuthResponse $in
	 * @return SymfonyResponse
	 */
	protected function convertOAuthResponseToSymfonyResponse(OAuthResponse $in) : SymfonyResponse
	{
		return new SymfonyResponse($in->getResponseBody(), $in->getStatusCode(), $in->getHttpHeaders());
	}
}
