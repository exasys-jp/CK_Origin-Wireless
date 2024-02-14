<!DOCTYPE html>

<head>
  <title>CK-WiFi</title>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/enc-base64.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/list.js/2.3.1/list.min.js"></script>
  <script src="{{ asset('js/wifiviewer.js') }}"></script>
  <link rel="stylesheet" href="{{ asset('css/wifiviewer.css') }}">
</head>

<body onload="onload()">
  <div id="mainScreen" class="modal">
    <div style="padding: 30px 10px 5px 10px; background-color: rgba(0, 0, 0, 0.4);">
      <select class="serviceSelector" id="serviceSelector" onchange="selectService(this.value)">
      </select>
    </div>
    <div class="page active" id="homePage">
      <div id="history">
        <div class="segmentedControl" id="historyView"
          style="width: 100%; margin: 15px auto 0px auto; font-size: midium; font-weight: bold; border-radius: 5px 5px 0 0;">
          <div data-value="lifeLog" class="segmentedControlButton selected"
            onclick="onHistoryView(this.dataset.value)" style="border-radius: 5px 0 0 0;">活動ログ</div>
          <div data-value="sleep" class="segmentedControlButton" 
            onclick="onHistoryView(this.dataset.value)" style="border-radius: 0 5px 0 0;">睡眠ログ
          </div>
        </div>
        <div class="groupBox" style="padding-top: 3px; padding-bottom: 15px;">
          <table style="width: 100%; margin: 10px 10px 0px 10px">
            <tr>
              <td class="left" style="width: 48px;">
                <span class="button" onclick="historyNavPrev()">&#9668;</span>
              </td>
              <td class="center">
                <span class="button" id="historyDateRange" onclick="loadHistory()"></span>
              </td>
              <td class="right" style="width: 48px;">
                <span class="button" onclick="historyNavNext()">&#9658;</span>
              </td>
            </tr>
          </table>
          <div class="engineSpecific" data-type="softSecurity">
            <svg width="100%" height="200" id="historySvg" style="margin-top: 10px">
              <g stroke="rgba(255, 255, 255, 0.1)">
                <line x1="0" y1="25%" x2="100%" y2="25%" stroke-width="1" />
                <line x1="0" y1="75%" x2="100%" y2="75%" stroke-width="1" />
              </g>
              <g stroke="rgba(255, 255, 255, 0.2)">
                <line x1="0" y1="50%" x2="100%" y2="50%" stroke-width="1" />
              </g>
            </svg>
            <div id="historyXLabels"></div>
            <div class="segmentedControl" id="historyRange"
              style="width: 100%; margin: 15px auto 0px auto; font-size: small;">
              <div data-value="month" class="segmentedControlButton" onclick="onHistoryRange(this.dataset.value)">Month
              </div>
              <div data-value="week" class="segmentedControlButton" onclick="onHistoryRange(this.dataset.value)">Week
              </div>
              <div data-value="day" class="segmentedControlButton" onclick="onHistoryRange(this.dataset.value)">Day
              </div>
              <div data-value="hour" class="segmentedControlButton" onclick="onHistoryRange(this.dataset.value)">Hour
              </div>
            </div>
          </div>
          <div class="engineSpecific" data-type="wellBeing">
            <div class="segmentedControl" id="historyGraph"
              style="width: 95%; max-width: 480px; margin: 15px auto 0px auto; font-size: midium; font-weight: bold;">
              <div data-value="allHistory" class="segmentedControlButton selected"
                onclick="changeHistoryGraph(this.dataset.value)">終日表示</div>
              <div data-value="splitHistory" class="segmentedControlButton" id="splitHistory"
                onclick="changeHistoryGraph(this.dataset.value)">午前／午後表示</div>
            </div>
            <svg width="100%" height="380" id="wellBeingHistorySvg">
            </svg>
            <svg width="100%" height="380" id="firstWellBeingHistorySvg">
            </svg>
            <svg width="100%" height="380" id="secondWellBeingHistorySvg">
            </svg>
          </div>
          <div style="margin-top: 10px;">
            <button onclick="onHistoryJson()">csvダウンロード</button>
            <input type="text" style="width: 200px;" id="csvtitle" />
            <select id="DLCsvMode">
              <option value="raw">検出時のみ</option>
              <option value="1sec">1秒値</option>
            </select>
          </div>
        </div>
      </div>
      <div id="dataDetail" class="groupBox" style="padding: 0;">
        <div
          style="text-align:left; font-weight: bold; background-color: rgba(0, 0, 0, 0.3); padding: 5px 10px; border-top-left-radius: 5px; border-top-right-radius: 5px;">
          データ詳細
        </div>
        <div style="padding: 10px 10px">
          <table id="graphValue" class="valueList"></table>
          <table id="averageValue" class="valueList"></table>
          <input type="button" value="週平均値" onclick="weekAverage()"/>
        </div>
      </div>
      <div class="groupBox" style="padding: 0">
        <div
          style="text-align:left; font-weight: bold; background-color: rgba(0, 0, 0, 0.3); padding: 5px 10px; border-top-left-radius: 5px; border-top-right-radius: 5px;">
          現在のデバイス状況
        </div>
        <div style="padding: 0px 10px">
          <table id="deviceList" class="panelList"></table>
        </div>
      </div>
      <div class="groupBox" style="padding: 0">
        <div
          style="text-align:left; font-weight: bold; background-color: rgba(0, 0, 0, 0.3); padding: 5px 10px; border-top-left-radius: 5px; border-top-right-radius: 5px;">
          通知条件設定
        </div>
        <div style="padding: 10px 10px">
          <div class="checkbox-container" id="checkbox-container1">
            <input type="checkbox" id="checkbox1"/>
            <label class="checkmark" for="checkbox1"></label>
            <select id="selectDevice1"></select>
            <input type="number" id="selectMinutes1" min="0" max="1440" placeholder="1~1440"/>
            <select id="findLost1">
              <option value="find">在室</option>
              <option value="lost">不在</option>
            </select>
          </div>
          <div class="checkbox-container" id="checkbox-container2">
            <input type="checkbox" id="checkbox2"/>
            <label class="checkmark" for="checkbox2"></label>
            <select id="selectDevice2"></select>
            <input type="number" id="selectMinutes2" min="0" max="1440" placeholder="1~1440"/>
            <select id="findLost2">
              <option value="find">在室</option>
              <option value="lost">不在</option>
            </select>
          </div>
          <div class="checkbox-container" id="checkbox-container3">
            <input type="checkbox" id="checkbox3"/>
            <label class="checkmark" for="checkbox3"></label>
            <select id="selectDevice3"></select>
            <input type="number" id="selectMinutes3" min="0" max="1440" placeholder="1~1440"/>
            <select id="findLost3">
              <option value="find">在室</option>
              <option value="lost">不在</option>
            </select>
          </div>
          <div class="checkbox-container" id="checkbox-container4">
            <input type="checkbox" id="checkbox4"/>
            <label class="checkmark" for="checkbox4"></label>
            <select id="selectDevice4"></select>
            <input type="number" id="selectMinutes4" min="0" max="1440" placeholder="1~1440"/>
            <select id="findLost4">
              <option value="find">在室</option>
              <option value="lost">不在</option>
            </select>
          </div>
          <div class="logConfigSave" style="text-align: left; display: flex;">
            <input type="button" value="保存" onclick="onLogConfigSave()"/>
            <div id="savecheck" style="margin: 5px; display: none;">保存しました</div>
          </div>
        </div>
      </div>
      <div class="groupBox" style="padding: 0;">
        <div
          style="text-align:left; font-weight: bold; background-color: rgba(0, 0, 0, 0.3); padding: 5px 10px; border-top-left-radius: 5px; border-top-right-radius: 5px;">
          条件通知ログ
        </div>
        <div id="noticeLogBox" class="noticeLogBox">
          <table id="noticeLogs" class="noticeLogs">
            <thead class="noticeLogHead">
              <tr>
                <th class="sort" data-sort="findLost">入室検知／不在検知</th>
                <th class="sort" data-sort="noticeTime">通知時間</th>
                <th class="sort" data-sort="deviceName">デバイス名</th>
              </tr>
            </thead>
            <tbody class="list" id="noticeLog"></tbody>
          </table>
          <div class="pager">
            <ul class="pagination"></ul>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div id="busyModal" class="modal" style="background-color: rgba(0, 0, 0, 0.5)">
    <div style="position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);">
      <div class="loader"></div>
      <div style="font-size: x-large; font-weight: bold; margin-top: 10px;">Please wait...</div>
    </div>
  </div>
</body>