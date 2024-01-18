<?php

namespace App\Http\Controllers;

use App\NoticeLogs;
use Illuminate\Http\Request;

class NoticeLogsController extends Controller
{
    public function show(Request $request)
    {
        $raw = $request->only(['serviceid']);
        $data = json_encode($raw);
        $dataArr = json_decode($data, true);
        $serviceId = $dataArr['serviceid'];
        $jsonData = NoticeLogs::where('serviceid', $serviceId)->get(['serviceid', 'findlost', 'noticetime', 'bot']);
        echo json_encode($jsonData);
    }

    public function create(Request $request)
    {
        $raw = $request->only(['serviceid', 'findlost', 'noticetime', 'bot']);
        $data = json_encode($raw);
        $dataArr = json_decode($data, true);
        $serviceId = $dataArr['serviceid'];
        $findLost = $dataArr['findlost'];
        $noticeTime = $dataArr['noticetime'];
        $bot = $dataArr['bot'];
        $noticeLogs = new NoticeLogs();
        $noticeLogs->create(['serviceid' => $serviceId, 'findlost' => $findLost, 'noticetime' => $noticeTime, 'bot' => $bot]);
        echo $data;
    }
}
