<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use DB;
use URL;
use Response;
use App\Http\Requests;

class PolicyController extends Controller
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
     * {"resourceId": "1", "permissions"[{"claim": "person@email.com", "scopes":["scope1", "scope2", "scope3"]},{"claim": "person@email.com", "scopes":["scope1", "scope2", "scope3"]}]
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $permissions_array = $request->input('permissions');
        foreach($permissions_array as $permissions) {
          $data1['resource_set_id'] = $request->input('resourceId');
          $policy_id = DB::table('policy')->insertGetId($data1);
          $query = DB::table('claim')->where('claim_value', '=', $permissions->claim)->first();
          if ($query) {
            $claim_id = $query->claim_id;
          } else {
            $data2 = [
              'name' => 'email',
              'claim_value' => $permissions->claim
            ];
            $claim_id = DB::table('claim')->insertGetId($data2);
          }
          $data3 = [
            'claim_id' => $claim_id,
            'policy_id' => $policy_id
          ];
          DB::table('claim_to_policy')->insert($data3);
          $scopes_array = $permissions->scopes;
          foreach($scopes_array as $scope) {
            $data4 = [
              'policy_id' => $policy_id,
              'scope' => $scope
            ];
            DB::table('policy_scopes')->insert($data4);
          }
        }
        return Response::json('', 201);
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
     * $request is json object
     * {"resourceId": "1", "permissions"[{"claim": "person@email.com", "scopes":["scope1", "scope2", "scope3"]},{"claim": "person@email.com", "scopes":["scope1", "scope2", "scope3"]}]
     * @param  int  $id (policy_id)
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $permissions_array = $request->input('permissions');
        $query = DB::table('policy')->where('policy_id', '=', $id)->where('resource_set_id', '=', $request->input('resourceId'))->first();
        if ($query) {
          DB::table('policy_scopes')->where('policy_id', '=', $id)->delete();
          DB::table('claim_to_policy')->where('policy_id', '=', $id)->delete();
          foreach($permissions_array as $permissions) {
            $query1 = DB::table('claim')->where('claim_value', '=', $permissions->claim)->first();
            if ($query1) {
              $claim_id = $query1->claim_id;
            } else {
              $data2 = [
                'name' => 'email',
                'claim_value' => $permissions->claim
              ];
              $claim_id = DB::table('claim')->insertGetId($data2);
            }
            $data3 = [
              'claim_id' => $claim_id,
              'policy_id' => $policy_id
            ];
            DB::table('claim_to_policy')->insert($data3);
            $scopes_array = $permissions->scopes;
            foreach($scopes_array as $scope) {
              $data4 = [
                'policy_id' => $policy_id,
                'scope' => $scope
              ];
              DB::table('policy_scopes')->insert($data4);
            }
          }
          return Response::json('', 201);
        } else {
          $response = [
            'code' => 404,
            'reason' => "Not found",
            'message' => "UMA Policy not found, " . $id
          ];
          return Response::json($response, 404);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id policy_id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
      $query = DB::table('policy')->where('policy_id', '=', $id)->where('resource_set_id', '=', $request->input('resourceId'))->first();
      if ($query) {
        DB::table('policy_scopes')->where('policy_id', '=', $id)->delete();
        DB::table('claim_to_policy')->where('policy_id', '=', $id)->delete();
        DB::table('policy')->where('policy_id', '=', $id)->delete();
        return Response::json('', 200);
      } else {
        $response = [
          'code' => 404,
          'reason' => "Not found",
          'message' => "UMA Policy not found, " . $id
        ];
        return Response::json($response, 404);
      }
    }
}
