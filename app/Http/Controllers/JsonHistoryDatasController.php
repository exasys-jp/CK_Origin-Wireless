<?php

namespace App\Http\Controllers;

use App\JsonHistoryDatas;
use Illuminate\Http\Request;

class JsonHistoryDatasController extends Controller
{
    public function index()
    {
        return view('result');
    }

    public function show(Request $request) 
    {
        $raw = $request->only(['serviceid']);
        $data = json_encode($raw);
        $dataArr = json_decode($data, true);
        $serviceId = $dataArr['serviceid'];
        $jsonData = JsonHistoryDatas::where('serviceid', $serviceId)->first("lastdata");
        $lastData = $jsonData['lastdata'];
        echo json_encode($lastData);
    }

    public function update(Request $request)
    {
        $serviceId = $request->only(['serviceid']);
        $all = $request->only(['serviceid', 'stateTime', 'detection', 'bot']);
        $raw = $request->only(['stateTime', 'detection', 'bot']);
        $data = json_encode($raw);
        $alldata = json_encode($all);
        $jsonData = new JsonHistoryDatas();
        $jsonData->where('serviceid', $serviceId)->update(['lastdata' => $data]);
        echo $alldata;
    }
}
