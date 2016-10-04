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
     * $request contains resourceId parameter
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $resource_set_id = $request->input('resourceId');
        $query = DB::table('policy')->where('resource_set_id', '=', $resource_set_id)->get();
        $return = [];
        $i = 0;
        if ($query) {
            foreach ($query as $row) {
                $return[$i] = [];
                $query1 = DB::table('claim_to_policy')->where('policy_id', '=', $row->policy_id)->get();
                $return[$i]['policy_id'] = $row->policy_id;
                if ($query1) {
                    foreach ($query1 as $row1) {
                        $query2 = DB::table('claim')->where('claim_id', '=', $row1->claim_id)->first();
                        if ($query2) {
                            $return[$i]['email'] = $query2->claim_value;
                            $return[$i]['name']  = $query2->name;
                            $return[$i]['last_activity'] = $row1->last_activity;
                        }
                    }
                }
                $query4 = DB::table('policy_scopes')->where('policy_id', '=', $row->policy_id)->get();
                foreach ($query4 as $scope) {
                    $return[$i]['scopes'][] = $scope->scope;
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
     * {"resourceId": "1", "permissions"[{"claim": "person@email.com", "scopes":["scope1", "scope2", "scope3"]},{"claim": "person@email.com", "scopes":["scope1", "scope2", "scope3"]}]
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $permissions_array = $request->input('permissions');
        $data1['resource_set_id'] = $request->input('resourceId');
        $policy_id = DB::table('policy')->insertGetId($data1);
        $query = DB::table('claim')->where('claim_value', '=', $permissions_array['claim'])->first();
        if ($query) {
            $claim_id = $query->claim_id;
        } else {
            $data2 = [
                'name' => $permissions_array['name'],
                'claim_value' => $permissions_array['claim']
            ];
            $claim_id = DB::table('claim')->insertGetId($data2);
        }
        $data3 = [
            'claim_id' => $claim_id,
            'policy_id' => $policy_id
        ];
        DB::table('claim_to_policy')->insert($data3);
        $scopes_array = $permissions_array['scopes'];
        foreach ($scopes_array as $scope) {
            $data4 = [
                'policy_id' => $policy_id,
                'scope' => $scope
            ];
            DB::table('policy_scopes')->insert($data4);
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
            $query1 = DB::table('claim')->where('claim_value', '=', $permissions_array['claim'])->first();
            if ($query1) {
                $claim_id = $query1->claim_id;
            } else {
                $data2 = [
                    'name' => $permissions_array['name'],
                    'claim_value' => $permissions_array['claim']
                ];
                $claim_id = DB::table('claim')->insertGetId($data2);
            }
            $data3 = [
                'claim_id' => $claim_id,
                'policy_id' => $policy_id
            ];
            DB::table('claim_to_policy')->insert($data3);
            $scopes_array = $permissions_array['scopes'];
            foreach ($scopes_array as $scope) {
                $data4 = [
                    'policy_id' => $policy_id,
                    'scope' => $scope
                ];
                DB::table('policy_scopes')->insert($data4);
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
