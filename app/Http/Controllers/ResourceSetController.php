<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use URL;
use Response;
use App\Http\Requests;

class ResourceSetController extends Controller
{
    /**
     * Display a listing of the resource.
     * $request contains client_id parameter
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $client = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        // Check if there is a uma_authorization scope
        $client_scopes = explode(' ', $client->scope);
        if (in_array('uma_authorization', $client_scopes)) {
            $return = [];
            $query1 = DB::table('resource_set')->get();
            $i = 0;
            if ($query1) {
                foreach ($query1 as $row) {
                    $return[$i] = $row->resource_set_id;
                    // $return[$i]['resource_set_id'] = $row->resource_set_id;
                    // $return[$i]['name'] = $row->name;
                    // $return[$i]['icon_uri'] = $row->icon_uri;
                    // $query2 = DB::table('resource_set_scopes')->where('resource_set_id', '=', $row->resource_set_id)->get();
                    // if ($query2) {
                    //     foreach ($query2 as $row1) {
                    //         $return[$i]['scopes'][] = $row1->scope;
                    //     }
                    // }
                    $i++;
                }
            }
            $statusCode = 200;
        } else {
            $statusCode = 401;
            $return = [
                'error' => 'unauthorized',
                'error_description' => 'The request has not been applied because it lacks valid authentication credentials for the target resource.'
            ];
        }
        return response()->json($return, $statusCode);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * $request is json object
     * {"name": "example", "icon_uri":"https://icon.uri", "scopes":["scope1", "scope2", "scope3"]}
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $client = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        $client_info = DB::table('oauth_clients')->where('client_id', '=', $client->client_id)->first();
        $name = $request->input('name');
        if (strpos($client_info->client_name, $name) == FALSE) {
            $name .= 'from ' . $client->client_name;
        }
        // Check if there is a uma_protection scope
        $client_scopes = explode(' ', $client->scope);
        if (in_array('uma_protection', $client_scopes)) {
            $data = [
                'name' => $request->input('name'),
                'icon_uri' => $request->input('icon_uri'),
                'client_id' => $client->client_id
            ];
            $resource_set_id = DB::table('resource_set')->insertGetId($data);
            $scopes_array = $request->input('resource_scopes');
            foreach ($scopes_array as $scope) {
                $data1 = [
                    'resource_set_id' => $resource_set_id,
                    'scope' => $scope
                ];
                DB::table('resource_set_scopes')->insert($data1);
            }
            $return = [
                '_id' => $resource_set_id,
                'user_access_policy_uri' => URL::to('policy')
            ];
            $client1 = DB::table('oauth_clients')->where('client_id', '=', $client->client_id)->first();
            $types = [];
            $default_policy_types = $this->default_policy_type();
            foreach ($default_policy_types as $default_policy_type) {
                $consent = 'consent_' . $default_policy_type;
                if ($client1->{$consent} == 1) {
                    $types[] = $default_policy_type;
                }
            }
            $this->group_policy($client->client_id, $types, 'update');
            $statusCode = 200;
        } else {
            $statusCode = 401;
            $return = [
                'error' => 'unauthorized',
                'error_description' => 'The request has not been applied because it lacks valid authentication credentials for the target resource.'
            ];
        }
        return response()->json($return, $statusCode);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $client = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        // Check if there is a uma_authorization scope
        $client_scopes = explode(' ', $client->scope);
        if (in_array('uma_authorization', $client_scopes)) {
            $return = [];
            $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
            if ($query) {
                $return = [
                    '_id' => $id,
                    'name' => $query->name,
                    'icon_uri' => $query->icon_uri,
                ];
                $query1 = DB::table('resource_set_scopes')->where('resource_set_id', '=', $id)->get();
                foreach ($query1 as $scope) {
                    $return['resource_scopes'][] = $scope->scope;
                }
                $statusCode = 200;
            } else {
                $return = [
                    'error' => 'not_found',
                    'error_description' => 'ResourceSet corresponding to id: ' . $id . ' not found'
                ];
                $statusCode = 404;
            }
        } else {
            $return = [
                'error' => 'unauthorized',
                'error_description' => 'The request has not been applied because it lacks valid authentication credentials for the target resource.'
            ];
            $statusCode = 401;
        }
        return response()->json($return, $statusCode);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id resource_set_id
     * $request is json object
     * {"name": "example", "icon_uri":"https://icon.uri", "scopes":["scope1", "scope2", "scope3"]}
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $client = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        // Check if there is a uma_protection scope
        $client_scopes = explode(' ', $client->scope);
        if (in_array('uma_protection', $client_scopes)) {
            $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
            if ($query) {
                $data = [
                    'name' => $request->input('name'),
                    'icon_uri' => $request->input('icon_uri'),
                ];
                DB::table('resource_set')->where('resource_set_id', '=', $id)->update($data);
                DB::table('resource_set_scopes')->where('resource_set_id', '=', $id)->delete();
                $scopes_array = $request->input('resource_scopes');
                foreach ($scopes_array as $scope) {
                    $data1 = [
                        'resource_set_id' => $resource_set_id,
                        'scope' => $scope
                    ];
                    DB::table('resource_set_scopes')->insert($data1);
                }
                $client1 = DB::table('oauth_clients')->where('client_id', '=', $client->client_id)->first();
                $types = [];
                $default_policy_types = $this->default_policy_type();
                foreach ($default_policy_types as $default_policy_type) {
                    $consent = 'consent_' . $default_policy_type;
                    if ($client1->{$consent} == 1) {
                        $types[] = $default_policy_type;
                    }
                }
                $this->group_policy($client->client_id, $types, 'update');
                $return = '';
                $statusCode = 201;
            } else {
                $return = [
                    'error' => 'not_found',
                    'error_description' => 'ResourceSet corresponding to id: ' . $id . ' not found'
                ];
                $statusCode = 204;
            }
        } else {
            $return = [
                'error' => 'unauthorized',
                'error_description' => 'The request has not been applied because it lacks valid authentication credentials for the target resource.'
            ];
            $statusCode = 401;
        }
        return response()->json($return, $statusCode);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id resource_set_id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $client = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        // Check if there is a uma_protection scope
        $client_scopes = explode(' ', $client->scope);
        if (in_array('uma_protection', $client_scopes)) {
            $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
            if ($query) {
                DB::table('resource_set')->where('resource_set_id', '=', $id)->delete();
                DB::table('resource_set_scopes')->where('resource_set_id', '=', $id)->delete();
                $types = [];
                $default_policy_types = $this->default_policy_type();
                foreach ($default_policy_types as $default_policy_type) {
                    $consent = 'consent_' . $default_policy_type;
                    if ($client1->{$consent} == 1) {
                        $types[] = $default_policy_type;
                    }
                }
                $this->group_policy($client->client_id, $types, 'update');
                $return = '';
                $statusCode = 204;
            } else {
                $return = [
                    'error' => 'not_found',
                    'error_description' => 'ResourceSet corresponding to id: ' . $id . ' not found'
                ];
                $statusCode = 404;
            }
        } else {
            $return = [
                'error' => 'unauthorized',
                'error_description' => 'The request has not been applied because it lacks valid authentication credentials for the target resource.'
            ];
            $statusCode = 401;
        }
        return response()->json($return, $statusCode);
    }
}
