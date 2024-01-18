<?php

namespace App\Http\Controllers;

use App\LogConfigs;
use Illuminate\Http\Request;

class LogConfigsController extends Controller
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
        $jsonData = LogConfigs::where('serviceid', $serviceId)->get(['serviceid', 'configid', 'bot', 'minutes','findlost', 'truefalse']);
        echo json_encode($jsonData);
    }

    public function update(Request $request)
    {
        $raw = $request->only(['serviceid', 'configid', 'bot', 'minutes', 'findlost', 'truefalse']);
        $data = json_encode($raw);
        $dataArr = json_decode($data, true);
        $serviceId = $dataArr['serviceid'];
        $configId = $dataArr['configid'];
        $bot = $dataArr['bot'];
        $minutes = $dataArr['minutes'];
        $findLost = $dataArr['findlost'];
        $truefalse = $dataArr['truefalse'];
        $jsonData = new LogConfigs();
        $jsonData->where('serviceid', $serviceId)->where('configid', $configId)->update(['bot' => $bot, 'minutes' => $minutes, 'findlost' => $findLost, 'truefalse' => $truefalse]);
        echo json_encode($data);
    }
}
