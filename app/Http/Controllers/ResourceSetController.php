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
        $return = [];
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $query = DB::table('oauth_access_tokens')->where('access_token', '=', substr($token, 0, 255))->first();
        $query1 = DB::table('resource_set')->where('client_id', '=', $query->client_id)->get();
        $i = 0;
        if ($query1) {
            foreach ($query1 as $row) {
                $return[$i]['resource_set_id'] = $row->resource_set_id;
                $return[$i]['name'] = $row->name;
                $return[$i]['icon_uri'] = $row->icon_uri;
                $query2 = DB::table('resource_set_scopes')->where('resource_set_id', '=', $row->resource_set_id)->get();
                if ($query2) {
                    foreach ($query2 as $row1) {
                        $return[$i]['scopes'][] = $row1->scope;
                    }
                }
                $i++;
            }
        }
        return $return;
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
        $data = [
            'name' => $request->input('name'),
            'icon_uri' => $request->input('icon_uri'),
            'client_id' => $client->client_id
        ];
        $resource_set_id = DB::table('resource_set')->insertGetId($data);
        $scopes_array = $request->input('scopes');
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
        if ($client1->consent_login_direct == 1) {
            $types[] = 'login_direct';
        }
        if ($client1->consent_login_md_nosh == 1) {
            $types[] = 'login_md_nosh';
        }
        if ($client1->consent_any_npi == 1) {
            $types[] = 'any_npi';
        }
        if ($client1->consent_login_google == 1) {
            $types[] = 'login_google';
        }
        $this->group_policy($client->client_id, $types, 'update');
        return $return;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
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
                $return['scopes'][] = $scope->scope;
            }
        }
        return $return;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
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
        $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
        if ($query) {
            $data = [
                'name' => $request->input('name'),
                'icon_uri' => $request->input('icon_uri'),
            ];
            DB::table('resource_set')->where('resource_set_id', '=', $id)->update($data);
            DB::table('resource_set_scopes')->where('resource_set_id', '=', $id)->delete();
            $scopes_array = $request->input('scopes');
            foreach ($scopes_array as $scope) {
                $data1 = [
                    'resource_set_id' => $resource_set_id,
                    'scope' => $scope
                ];
                DB::table('resource_set_scopes')->insert($data1);
            }
            $client1 = DB::table('oauth_clients')->where('client_id', '=', $client->client_id)->first();
            if ($client1->consent_login_direct == 1) {
                $types[] = 'login_direct';
            }
            if ($client1->consent_login_md_nosh == 1) {
                $types[] = 'login_md_nosh';
            }
            if ($client1->consent_any_npi == 1) {
                $types[] = 'any_npi';
            }
            if ($client1->consent_login_google == 1) {
                $types[] = 'login_google';
            }
            $this->group_policy($client->client_id, $types, 'update');
            return Response::json('', 201);
        } else {
            $response = [
                'error' => "not_found",
                'error_description' => "ResourceSet corresponding to id: " . $id . " not found"
            ];
            return Response::json($response, 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id resource_set_id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $query = DB::table('resource_set')->where('resource_set_id', '=', $id)->first();
        if ($query) {
            DB::table('resource_set')->where('resource_set_id', '=', $id)->delete();
            DB::table('resource_set_scopes')->where('resource_set_id', '=', $id)->delete();
            if ($client1->consent_login_direct == 1) {
                $types[] = 'login_direct';
            }
            if ($client1->consent_login_md_nosh == 1) {
                $types[] = 'login_md_nosh';
            }
            if ($client1->consent_any_npi == 1) {
                $types[] = 'any_npi';
            }
            if ($client1->consent_login_google == 1) {
                $types[] = 'login_google';
            }
            $this->group_policy($client->client_id, $types, 'update');
            return Response::json('', 204);
        } else {
            $response = [
                'error' => "not_found",
                'error_description' => "ResourceSet corresponding to id: " . $id . " not found"
            ];
            return Response::json($response, 404);
        }
    }
}
