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
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
        $data = [
          'name' => $request->input('name'),
          'icon_uri' => $request->input('icon_uri'),
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
        // Generate policy
        $policy = [
          'resource_set_id' => $resource_set_id
        ];
        $policy_id = DB::table('policy')->insertGetId($policy);
        $return = [
          '_id' => $resource_set_id,
          'user_access_policy_uri' => URL::to('policy') . '/' . $policy_id
        ];
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
        //
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
