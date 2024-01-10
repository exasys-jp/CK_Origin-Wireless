<?php

?>

<!DOCTYPE html>

<head>
  <title>WirelessAI Simple Viewer</title>
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/crypto-js.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.9-1/enc-base64.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.2/Chart.bundle.js"></script>
  <script>
    /* 設定箇所 ここから */
    const apiKeyId = "e33b1153-3946-4584-82f3-cb89e93cfbe7";
    const apiKey = "5111262334fd0eddb80e2222f774d86d";
    const services = {
      0: {
        id: "b931b8ec-ed08-43c5-af82-df395ac327c2", // serviceId
        type: "wellBeing",
        name: "EXA-develop",
        origin: "1111WJ000578",  // origin device id
        bots: {
          "1111WJ000633": "寝室",// bot device id
          "1111WJ000677": "リビング"
        }
      },
      1: {
        id: "f826a4e5-e1c0-4e3b-9538-9b63050e4b64", // serviceId
        type: "wellBeing",
        name: "TKTK-POC003(江戸川:工藤さま)",
        origin: "1911WJ000788",  // origin device id
        bots: {
          "1911WJ000790": "寝室",// bot device id
          "1911WJ000791": "リビング"
        }
      },
    }
    
    function onload() {
      let serviceSelectors = document.getElementsByClassName("serviceSelector")

      for (let key in services) {
        const service = services[key]
        for (var serviceSelector of serviceSelectors) {
          let serviceOption = document.createElement("option")
          serviceOption.value = key
          serviceOption.innerHTML = service.type + ": " + service.name
          serviceSelector.appendChild(serviceOption)
        }
      }

      window.onresize = () => {
        for (let i = 0; i < 3; i++) {
          switch (i) {
            case 0:
              svgName = "allWellBeingHistorySvg"
              historyData = allHistoryData
              break
            case 1:
              svgName = "firstWellBeingHistorySvg"
              historyData = firstHistoryData
              break
            case 2:
              svgName = "secondWellBeingHistorySvg"
              historyData = secondHistoryData
              break
          }
          drawHistoryGraph()
        }
      }

      startBusyModal(
        () => {
          if (activeAuthToken) {
            stopBusyModal()
            if (Object.keys(services).length > 0) {
              selectService(Object.keys(services)[0])
            }
          }
        }
      )
    }

    var serviceId
    var selectedService
    var dateRangeText


    function selectService(key) {

      // update service id
      selectedService = services[key]
      serviceId = selectedService.id

      // show correct live and history UIs
      const engineSpecificElements = document.getElementsByClassName("engineSpecific")
      for (var element of engineSpecificElements) {
        if (element.dataset["type"] != null) {
          if (element.dataset["type"] === selectedService.type) {
            switch (element.tagName) {
              case "TR":
                element.style.display = "table-row"
                break
              default:
                if (element.classList.contains("segmentedControl") || element.id === "historyXLabels") {
                  element.style.display = "table"
                }
                else {
                  element.style.display = "block"
                }
            }
          }
          else {
            element.style.display = "none"
          }
        }
      }

      // clear cached data
      cachedDeviceStatus = null

      // get info about this service
      getService(serviceId)
      getDeviceNetwork(selectedService.origin, () => { getDeviceStatus(selectedService.origin) })
      getServiceParams(serviceId)
      getServiceConfig(serviceId)

      // reset cached values
      refDate = new Date()
      historyData = []

      // default history range & view
      loadHistory()
    }

    function SetServiceParams(params, onSuccess, onFailure) {
      ssApi(
        "POST",
        `/api/services/${serviceId}/params`,
        {
          engineType: selectedService.type,
          serviceId: serviceId,
          transactionId: String(Math.round(new Date())),
          engineMsg: params
        },
        (data) => {
          if (onSuccess) onSuccess(data)
        },
        (status, resp) => {
          if (onFailure) onFailure(status, resp)
          showAlert("Unable to set service params:\n" + JSON.stringify(resp, null, 2))
        }
      )
    }

    var pendingAsyncAction = null

    function startBusyModal(validate, timeoutMsg, action) {
      var expriation = new Date()
      expriation.setSeconds(expriation.getSeconds() + 10)

      let busyModal = document.getElementById("busyModal")
      busyModal.style.display = "block"

      pendingAsyncAction = {
        action: action,
        validate: validate,
        interval: setInterval(
          () => {
            const now = new Date()
            if (now >= expriation) {
              stopBusyModal()
              showAlert(timeoutMsg)
            }
            else if (action === "run") {
              getServiceConfig(serviceId)
            }
            else if (action === "params") {
              getServiceParams(serviceId)
            }
            else {
              validate()
            }
          },
          2000)
      }
    }

    function stopBusyModal() {
      if (pendingAsyncAction) {
        clearInterval(pendingAsyncAction.interval)
        pendingAsyncAction = null
        let busyModal = document.getElementById("busyModal")
        busyModal.style.display = "none"
      }
    }

    function deviceName(deviceId) {
      if (deviceId) {
        if (deviceId == selectedService.origin) {
          return selectedService.originLabel ? selectedService.originLabel : "Origin"
        }
        let botName = selectedService.bots[deviceId]
        if (botName) {
          return botName
        }
        return deviceId.slice(-4)
      }
      return "Unknown"
    }

    function getService(serviceId) {
      ssApi(
        "GET",
        `/api/services/${serviceId}`
      )
    }

    function getDeviceNetwork(deviceId, onSuccess, onFailure) {
      const deviceList = document.getElementById("deviceList")
      while (deviceList.lastChild) {
        deviceList.lastChild.remove()
      }
      if (deviceId != null) {
        ssApi(
          "GET",
          `/api/devices/${deviceId}/network`,
          null,
          (data) => {
            renderDeviceNetwork(data.network)
            if (onSuccess) onSuccess()
          }
        )
      }
    }

    function renderDeviceNetwork(network) {
      const deviceList = document.getElementById("deviceList")
      while (deviceList.lastChild) {
        deviceList.lastChild.remove()
      }

      const origin = network.origin

      if (origin) {
        let tr = document.createElement("tr")
        deviceList.appendChild(tr)
        tr.className = "deviceRow"

        let td = document.createElement("td")
        tr.appendChild(td)
        td.className = "left"

        let deviceIdField = document.createElement("div")
        deviceIdField.innerHTML = `ORIGIN: ${origin}`
        td.appendChild(deviceIdField)

        let onlineStatusField = document.createElement("div")
        onlineStatusField.className = "subtitle"
        onlineStatusField.id = "originStatus"
        td.appendChild(onlineStatusField)
      }

      if (network.bots) {
        for (let bot of network.bots) {
          let tr = document.createElement("tr")
          deviceList.appendChild(tr)
          tr.className = "deviceRow"

          let td = document.createElement("td")
          tr.appendChild(td)
          td.className = "left"

          let deviceIdField = document.createElement("div")
          deviceIdField.innerHTML = `BOT: ${bot}`
          const botDesc = selectedService.bots[bot]
          if (botDesc && botDesc.length > 0) {
            deviceIdField.innerHTML += ` (${botDesc})`
          }
          td.appendChild(deviceIdField)

          let onlineStatusField = document.createElement("div")
          onlineStatusField.className = "subtitle"
          onlineStatusField.id = `deviceStatus-${bot}`
          td.appendChild(onlineStatusField)

        }
      }
    }

    function getServiceConfig(serviceId) {
      ssApi(
        "GET",
        `/api/services/${serviceId}/config`,
        null,
        (data) => {
          renderServiceConfig(data.config)
        },
        (status, resp) => {
          renderServiceConfig(null)
        }
      )
    }

    function getServiceParams(serviceId) {
      clearServiceParams()

      ssApi(
        "GET",
        `/api/services/${serviceId}/params`,
        null,
        (data) => {
          renderServiceParams(data.operationInfo)
        }
      )
    }

    function clearServiceParams() {
      cachedSchedules = []
      cachedOperationInfo = null
    }

    var cachedOperationInfo = null

    function renderServiceParams(operationInfo) {
      // cache it
      cachedOperationInfo = operationInfo

      // validate any pending async action
      if (pendingAsyncAction && pendingAsyncAction.validate(operationInfo)) {
        stopBusyModal()
      }

    }

    function renderServiceConfig(config) {
      const run = (config && config.cmd === "start")

      // validate any pending async action
      if (pendingAsyncAction &&
        pendingAsyncAction.action === "run") {
        if (pendingAsyncAction.validate(config)) {
          stopBusyModal()
        }
        else if (config && config.success === false) {
          stopBusyModal()
          showAlert(`Request to ${config.cmd} service was rejected.`)
        }
      }
    }

    function getDeviceStatus(deviceId) {
      renderDeviceStatus(undefined)

      if (deviceId != null) {
        ssApi(
          "GET",
          `/api/devices/${deviceId}/status`,
          null,
          (data) => {
            renderDeviceStatus(data.status)
          }
        )
      }
    }

    var cachedDeviceStatus = null

    function renderDeviceStatus(status) {
      // cache it
      cachedDeviceStatus = status

      let onlineStatusField = document.getElementById("originStatus")
      if (onlineStatusField) {
        onlineStatusField.innerHTML = ""
        onlineStatusField.className = "subtitle"

        if (status) {
          let statusText = (status.online != null && status.online) ? "online" : "offline"
          onlineStatusField.classList.add(statusText)
          onlineStatusField.innerHTML = `&#9679; ${statusText}`
        }
      }

      for (var bot in selectedService.bots ? selectedService.bots : []) {
        let botStatusField = document.getElementById(`deviceStatus-${bot}`)
        if (botStatusField) {
          botStatusField.innerHTML = ""
          botStatusField.className = "subtitle"
          if (status) {
            let statusText = (status.bots && status.bots.includes(bot)) ? "online" : "offline"
            botStatusField.classList.add(statusText)
            botStatusField.innerHTML = `&#9679; ${statusText}`
          }
        }
      }
    }

    var historyRange = "day"

    function onHistoryRange(value) {
      historyRange = value
      loadHistory()
    }

    var historyView = "lifeLog"

    function onHistoryView(view) {
      historyView = view
      loadHistory()
    }

    var historyGraph = "allHistory"

    function changeHistoryGraph(value) {
      historyGraph = value
      loadHistory()
    }

    var refDate
    var historyData = null
    var svgName = "allWellBeingHistorySvg"
    var allHistoryData
    var firstHistoryData
    var secondHistoryData

    function loadHistory() {
      var begin = new Date(refDate)
      var end

      if (selectedService.type === "wellBeing") {
        let historyViewSegmentedControl = document.getElementById("historyView")
        for (let segment of historyViewSegmentedControl.children) {
          if (segment.dataset.value == historyView) {
            segment.classList.add("selected")
          }
          else {
            segment.classList.remove("selected")
          }
        }

        switch (historyView) {
          case "sleep":
            historyRange = "week"
            break
          default: // lifeLog
            historyRange = "day"
        }
      }

      let historyRangeSegmentedControl = document.getElementById("historyRange")
      for (let segment of historyRangeSegmentedControl.children) {
        if (segment.dataset.value == historyRange) {
          segment.classList.add("selected")
        }
        else {
          segment.classList.remove("selected")
        }
      }

      let historyGraphSegmentedControl = document.getElementById("historyGraph")
        for (let segment of historyGraphSegmentedControl.children) {
          if (segment.dataset.value == historyGraph) {
            segment.classList.add("selected")
          }
          else {
            segment.classList.remove("selected")
          }
        }

      const startHour = selectedService.type === "wellBeing" ? 0 : 0

      var interval
      switch (historyRange) {
        case "month":
          begin.setDate(1)
          begin.setHours(startHour, 0, 0, 0)
          end = new Date(begin.getFullYear(), begin.getMonth() + 1, 1)
          interval = 86400 // 1 day
          break
        case "week":
          begin.setDate(begin.getDate() - begin.getDay())
          begin.setHours(startHour, 0, 0, 0)
          console.assert(begin.getDay() == 0)
          end = new Date(begin)
          end.setDate(end.getDate() + 7)
          interval = 86400 // 1 day
          break
        case "hour":
          begin.setHours(begin.getHours(), 0, 0, 0)
          console.assert(begin <= refDate)
          end = new Date(begin)
          end.setHours(end.getHours() + 1)
          interval = 300 // 5 minute
          break
        default: // "day"
          begin.setHours(startHour, 0, 0, 0)
          if (begin > refDate) {
            begin.setDate(begin.getDate() - 1)
          }
          end = new Date(begin)
          end.setDate(begin.getDate() + 1)
          interval = 3600 // 1 hour
      }

      // refDate = begin

      let dateRange = document.getElementById("historyDateRange")
      dateRange.innerHTML = ""
      data = null
      historyData = null
      drawHistoryGraph()

      const count = Math.ceil((Math.ceil(end / 1000) - Math.floor(begin / 1000)) / interval)

      // setup x-axis labels
      let xLabels = document.getElementById("historyXLabels")
      while (xLabels.lastChild) {
        xLabels.removeChild(xLabels.lastChild)
      }
      const labelText = {
        "month": (i) => {
          return String(i + 1)
        },
        "week": (i) => {
          let date = new Date(begin)
          date.setDate(begin.getDate() + i)
          return date.toLocaleString(
            undefined,
            { month: "numeric", day: "numeric" }
          )
        },
        "day": (i) => {
          return String(i)
        },
        "hour": (i) => {
          return ":" + (i < 2 ? "0" : "") + String(i * 5)
        }
      }
      for (var i = 0; i < count; i++) {
        let label = document.createElement("div")
        label.style.display = "table-cell"
        label.innerHTML = labelText[historyRange](i)
        xLabels.appendChild(label)
      }

      for (let i = 0; i < 3; i++) {
        switch (i) {
          case 0:
          default:
            // 全取得
            var url = `/api/services/${serviceId}/histories?begin=${Math.floor(begin / 1000)}&end=${Math.floor(end / 1000)}`
            break
          case 1:
            // 前半取得
            var url = `/api/services/${serviceId}/histories?begin=${Math.floor(begin / 1000)}&end=${Math.floor(end / 1000 - 43200)}`
            break
          case 2:
            // 後半取得
            var url = `/api/services/${serviceId}/histories?begin=${Math.floor(begin / 1000 + 43200)}&end=${Math.floor(end / 1000)}`
            break
        }

        switch (selectedService.type) {
          case "softSecurity":
            url += `&interval=${interval}`
            break
          case "wellBeing":
            url += `&view=${historyView}`
            break
        }

        ssApi(
          "GET",
          url,
          null,
          (data) => {
            switch (historyRange) {
              case "month":
                let lastDay = new Date(end)
                lastDay.setDate(lastDay.getDate() - 1)
                dateRangeText = begin.toLocaleString(
                  undefined,
                  { year: "numeric", month: "numeric", day: "numeric" }
                ) + " - " +
                  lastDay.toLocaleString(
                    undefined,
                    { year: "numeric", month: "numeric", day: "numeric" }
                  )
                break
              case "week":
                dateRangeText = begin.toLocaleString(
                  undefined,
                  { year: "numeric", month: "numeric", day: "numeric" }
                ) + " - " +
                  end.toLocaleString(
                    undefined,
                    { year: "numeric", month: "numeric", day: "numeric" }
                  )
                break
              case "hour":
                dateRangeText = begin.toLocaleString(
                  undefined,
                  { year: "numeric", month: "numeric", day: "numeric", hour: "numeric", minute: "2-digit" }
                )
                break
              default: // "day"
                if (begin.getHours() == 0) {
                  dateRangeText = begin.toLocaleString(
                    undefined,
                    { weekday: "short", year: "numeric", month: "numeric", day: "numeric" }
                  )
                }
                else {
                  dateRangeText =
                    begin.toLocaleString(
                      undefined,
                      { year: "numeric", month: "numeric", day: "numeric" }
                    ) + " - " +
                    end.toLocaleString(
                      undefined,
                      { year: "numeric", month: "numeric", day: "numeric" }
                    )
                }
            }

            begin.toLocaleDateString(
              undefined,
              { weekday: "short", year: "numeric", month: "numeric", day: "numeric" }
            )
            dateRange.innerHTML = dateRangeText + " <b>&#x21bb;</b>"

            if (data && data.serviceId === serviceId) {
              const allDayHistory = document.getElementById("allWellBeingHistorySvg")
              const firstDayHistory = document.getElementById("firstWellBeingHistorySvg")
              const secondDayHistory = document.getElementById("secondWellBeingHistorySvg")

              switch (historyGraph) {
                case "allHistory":
                  firstDayHistory.style.display="none"
                  secondDayHistory.style.display="none"
                  allDayHistory.style.display="block"
                  break
                case "splitHistory":
                  allDayHistory.style.display="none"
                  firstDayHistory.style.display="block"
                  secondDayHistory.style.display="block"
                  break
                }
              switch (i) {
                case 0:
                  svgName = "allWellBeingHistorySvg"
                  allHistoryData = data
                  historyData = data
                  drawHistoryGraph()
                  break
                case 1:
                  svgName = "firstWellBeingHistorySvg"
                  firstHistoryData = data
                  historyData = data
                  drawHistoryGraph()
                  break
                case 2:
                  svgName = "secondWellBeingHistorySvg"
                  secondHistoryData = data
                  historyData = data
                  drawHistoryGraph()
                  break
              }
            }
          },
          (status, resp) => {
            console.log(`Error retrieving history.\nResp=${JSON.stringify(resp, null, 2)}`)
          }
        )
      }
    }
    

    function drawHistoryGraph() {
      switch (selectedService.type) {
        case "softSecurity":
          drawSoftSecurityHistory()
          break
        case "wellBeing":
          drawWellBeingHistory()
          break
      }
    }

    function drawSoftSecurityHistory() {
      let svg = document.getElementById("historySvg")
      let bars = svg.getElementsByClassName("historyBar")
      while (bars.length > 0) {
        svg.removeChild(bars[0])
      }

      let valueLabels = svg.getElementsByClassName("historyValueLabel")
      while (valueLabels.length > 0) {
        svg.removeChild(valueLabels[0])
      }

      const count = (historyData && historyData.activityLevels) ? historyData.activityLevels.length : 0
      if (count > 0) {
        const barWidth = svg.clientWidth / count

        for (var index = 0; index < count; index++) {
          const activityLevel = historyData.activityLevels[index]
          const barHeight = Math.min(100, activityLevel) * svg.clientHeight / 100
          if (activityLevel > 0) {
            let bar = document.createElementNS("http://www.w3.org/2000/svg", "rect") // document.createElement("rect")
            bar.setAttribute("data-value", `${index}`)
            bar.setAttribute("x", String(barWidth * index))
            bar.setAttribute("y", String(svg.clientHeight - barHeight))
            bar.setAttribute("width", String(barWidth))
            bar.setAttribute("height", String(barHeight))
            bar.setAttribute("class", "historyBar")
            bar.setAttribute("onclick", "clickOnHdata(this)")
            svg.appendChild(bar)
          }
          // value label
          let valueLabel = document.createElementNS("http://www.w3.org/2000/svg", "text")
          valueLabel.setAttribute("data-value", `${index}`)
          valueLabel.setAttribute("x", index * barWidth + barWidth / 2)
          valueLabel.setAttribute("y", String(Math.max(10, svg.clientHeight - barHeight - 5)))
          valueLabel.setAttribute("text-anchor", "middle")
          valueLabel.setAttribute("class", "historyValueLabel")
          valueLabel.setAttribute("onclick", "clickOnHdata(this)")
          valueLabel.innerHTML = activityLevel
          svg.appendChild(valueLabel)
        }
      }
    }

    // tweak to detect double-click
    var lastHdataClick

    function clickOnHdata(target) {
      // no double-click handling on hour view
      if (historyRange == "hour") return

      let timestamp = new Date()
      const value = parseInt(target.dataset.value)
      if (lastHdataClick != null && lastHdataClick.value === value && historyData) {
        // check if 2nd click is within 750 milliseconds from 1st
        var threshold = new Date(timestamp)
        threshold.setMilliseconds(threshold.getMilliseconds() - 750)
        if (lastHdataClick.timestamp >= threshold) {
          const beginTime = new Date(historyData.begin * 1000)
          switch (historyRange) {
            case "month":
            case "week":
              refDate.setDate(beginTime.getDate() + value)
              onHistoryRange("day")
              break
            default: // "day"
              refDate.setHours(beginTime.getHours() + value)
              onHistoryRange("hour")
          }
          lastHdataClick = null
          return // do not continue
        }
      }
      lastHdataClick = {
        value: value,
        timestamp: timestamp
      }
    }

    function drawWellBeingHistory() {
      if (selectedService.type !== "wellBeing") {
        return
      }

      const svg = document.getElementById(svgName)
      while (svg.lastChild) {
        svg.removeChild(svg.lastChild)
      }

      // don't go any further if page not visible
      if (svg.clientWidth <= 0) return

      switch (historyView) {
        case "sleep":
          drawSleepGraph(svg)
          break
        default: // lifeLog
          drawLifeLogGraph(svg)
      }
    }

    function drawLifeLogGraph(svg) {

      const graphMargin = { left: 60, top: 25, right: 15, bottom: 20 }

      const graph = {
        x: graphMargin.left,
        y: graphMargin.top,
        width: svg.clientWidth - graphMargin.left - graphMargin.right,
        height: svg.clientHeight - graphMargin.top - graphMargin.bottom
      }

      // graph rectangle
      let rect = document.createElementNS("http://www.w3.org/2000/svg", "rect")
      rect.setAttribute('x', `${graph.x}`)
      rect.setAttribute('y', `${graph.y}`)
      rect.setAttribute('width', `${graph.width}`)
      rect.setAttribute('height', `${graph.height}`)
      rect.setAttribute("class", "graphRect")
      svg.appendChild(rect)

      // proceed no further if no history data
      if (!historyData) return

      let gGridlines = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gGridlines.setAttribute("class", "graphGridline")
      svg.appendChild(gGridlines)

      // vertical gridlines
      var x, y
      const historyDuration = historyData.end - historyData.begin
      let gVerticalGridlines = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gVerticalGridlines.setAttribute('stroke', 'rgba(255, 255, 255, 0)')
      gGridlines.appendChild(gVerticalGridlines)

      // x-axis labels
      const totalHours = Math.ceil(historyDuration / 3600)
      const maxLabels = Math.floor(graph.width / 50)
      var hoursPerLabel = Math.ceil(totalHours / maxLabels)
      while (totalHours % hoursPerLabel > 0) {
        hoursPerLabel += 1
      }
      const labelCount = Math.max(1, Math.floor(totalHours / hoursPerLabel))
      const dx = graph.width / labelCount
      let gXAxisLabels = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gXAxisLabels.setAttribute("class", "axisLabel")
      gXAxisLabels.setAttribute('text-anchor', 'middle')
      svg.appendChild(gXAxisLabels)
      y = graph.y + graph.height + 15
      for (var tEpoch = historyData.begin; tEpoch <= historyData.end; tEpoch += hoursPerLabel * 3600) {
        let text = document.createElementNS("http://www.w3.org/2000/svg", "text")
        let date = new Date(tEpoch * 1000)
        text.innerHTML = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
        x = graph.x + graph.width * (tEpoch - historyData.begin) / historyDuration
        text.setAttribute('x', String(x))
        text.setAttribute('y', String(y))
        gXAxisLabels.appendChild(text)

        // vertical gridline
        if (x > graph.x && x < graph.x + graph.width) {
          let line = document.createElementNS("http://www.w3.org/2000/svg", "line")
          line.setAttribute('stroke', 'rgba(0, 0, 0, 0.05)')
          line.setAttribute('x1', x)
          line.setAttribute('y1', graph.y)
          line.setAttribute('x2', x)
          line.setAttribute('y2', graph.y + graph.height)
          gVerticalGridlines.appendChild(line)
        }
      }

      // y-axis labels
      const botsInHistory = (historyData && historyData.lifeLog) ? historyData.lifeLog.map(x => x.bot).filter(x => x != null) : []
      const botIds = [...new Set([...Object.keys(selectedService.bots), ...botsInHistory])]
        .sort((a, b) => a > b ? -1 : 1)

      var botLabels = botIds.map(b => describeBot(b))
      var ylabely = []
      var ylabeltext = []

      const yAxisLabels = [
        describeSleepStage(2),
        describeSleepStage(1),
        describeSleepStage(0),
        ...botLabels,
        '滞在',
        '不在'
      ]
      const stateCount = yAxisLabels.length
      const dy = graph.height / stateCount
      let gYAxisLabels = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gYAxisLabels.setAttribute("class", "axisLabel")
      gYAxisLabels.setAttribute('text-anchor', 'end')
      svg.appendChild(gYAxisLabels)
      x = graph.x - 6
      for (var i = 0; i < yAxisLabels.length; i++) {
        let text = document.createElementNS("http://www.w3.org/2000/svg", "text")
        text.innerHTML = yAxisLabels[i]
        y = graph.y + i * dy + dy / 2 + 3
        text.setAttribute('x', String(x))
        text.setAttribute('y', String(y))
        ylabely.push(y - 3);
        gYAxisLabels.appendChild(text)
      }

      // horizontal gridlines
      let gHorizontalGridlines = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gHorizontalGridlines.setAttribute('stroke', 'rgba(0, 0, 0, 0.3)')
      gGridlines.appendChild(gHorizontalGridlines)
      for (var i = 0; i < stateCount; i++) {
        y = graph.y + i * dy + dy / 2
        let line = document.createElementNS("http://www.w3.org/2000/svg", "line")
        line.setAttribute('x1', graph.x)
        line.setAttribute('y1', y)
        line.setAttribute('x2', graph.x + graph.width)
        line.setAttribute('y2', y)
        gHorizontalGridlines.appendChild(line)
      }

      var lasty
      var points = ''
      let firsty
      var linePoints = []
      var horizontalLines = []
      let dividelength = 2
      let gHolizontalLines = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gHolizontalLines.setAttribute('fill', 'none')
      gHolizontalLines.setAttribute('stroke', 'var(--brightGreen)')
      gHolizontalLines.setAttribute('stroke-width', '30')
      svg.appendChild(gHolizontalLines)

      const addPolyline = () => {
        if (points.length > 0) {
          firsty = linePoints[0].substring(linePoints[0].indexOf(','))
          linePoints[0] = '60.5' + firsty
          for (let i = 0; i < linePoints.length; i += dividelength) {
            let splitLinePoints = linePoints.slice(i, i + dividelength);
            horizontalLines.push(splitLinePoints);
          }
          for (let i = 0; i < horizontalLines.length; i++) {
            let line = document.createElementNS("http://www.w3.org/2000/svg", "line")
            let liney = horizontalLines[i][1].substring(horizontalLines[i][1].indexOf(',') + 1)
            line.setAttribute('x1', horizontalLines[i][0].substring(0, horizontalLines[i][0].indexOf(',')))
            line.setAttribute('y1', horizontalLines[i][0].substring(horizontalLines[i][0].indexOf(',') + 1))
            line.setAttribute('x2', horizontalLines[i][1].substring(0, horizontalLines[i][1].indexOf(',')))
            line.setAttribute('y2', horizontalLines[i][1].substring(horizontalLines[i][1].indexOf(',') + 1))
            // line.setAttribute("onmouseover", `showLifeStageBalloon(${sleepIndex}, ${stageIndex}, ${(x * 2 + segmentWidth) / 2}, ${y})`)
            // line.setAttribute("onmouseout", `hideLifeStageBalloon()`)
            if (liney == ylabely[0]) {
              gHolizontalLines.appendChild(line).setAttribute('stroke', 'midnightblue')
            } else if (liney == ylabely[1]) {
              gHolizontalLines.appendChild(line).setAttribute('stroke', 'cornflowerblue')
            } else if (liney == ylabely[2]) {
              gHolizontalLines.appendChild(line).setAttribute('stroke', 'gold')
            } else if (liney == ylabely[ylabely.length - 2]) {
              gHolizontalLines.appendChild(line).setAttribute('stroke', 'coral')
            } else if (liney == ylabely[ylabely.length - 1]) {
              gHolizontalLines.appendChild(line).setAttribute('stroke', 'silver')
            } else {
              gHolizontalLines.appendChild(line).setAttribute('stroke', 'orange')
            }
            gHolizontalLines.appendChild(line)
          }
        }
      }

      for (let entry of historyData.lifeLog ? historyData.lifeLog : []) {
        if (entry.sleepStage != null) {
          yLevel = 2 - entry.sleepStage
        }
        else if (entry.bot) {
          yLevel = 3 + botIds.indexOf(entry.bot)
        }
        else {
          yLevel = 3 + botIds.length + 1 - entry.detection
        }
        x = graph.x + graph.width * (entry.stateTime - historyData.begin) / historyDuration
        y = graph.y + yLevel * dy + dy / 2

        // horizontal line
        if (points.length > 0) {
          points += ` ${x},${lasty} `
          linePoints.push(`${x},${lasty}`)
        }

        if (yLevel < stateCount) {
          // vertical line
          points += `${x},${y}`
          linePoints.push(`${x},${y}`)
        }
        else {
          addPolyline()
        }
        lasty = y
      }
      if (lasty != null && lasty <= graph.y + graph.height) {
        // last horizontal line
        var x = graph.x + graph.width
        const now = new Date() / 1000
        if (historyData.end > now) {
          x = graph.x + graph.width * (now - historyData.begin) / historyDuration
        }
        points += ` ${x},${lasty}`
        linePoints.push(`${x},${lasty}`)
      }
      addPolyline()
    }

    function showLifeStageBalloon(sleepIndex, stageIndex, xPos, yPos) {
      hideLifeStageBalloon()

      if (!historyData || !historyData.sleeps) return

      const sleep = historyData.sleeps[sleepIndex]
      if (!sleep) return

      const stage = sleep.stages[stageIndex]
      if (!stage) return

      const timeToBedText = (new Date(sleep.timeToBed * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )
      const wakeupTime = sleep.timeToBed + sleep.timeInBed
      const wakeupTimeText = (new Date(wakeupTime * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )
      const durationText = describeDuration(sleep.timeInBed)
      const stageStartTimeText = (new Date(stage.time * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )
      const stageEndTime = (stageIndex < (sleep.stages.length - 1)) ? sleep.stages[stageIndex + 1].time : wakeupTime
      const stageEndTimeText = (new Date(stageEndTime * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )

      // balloon g object
      const svg = document.getElementById("wellBeingHistorySvg")
      let gBalloon = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gBalloon.setAttribute("id", "balloon")
      svg.appendChild(gBalloon)

      // balloon background rectangle
      const balloonColor = "rgba(192, 0, 192, 1.0)"
      let balloonRect = document.createElementNS("http://www.w3.org/2000/svg", "rect")
      balloonRect.setAttribute('style', `fill: ${balloonColor}; stroke: rgba(255, 255, 255, 0.5)`)
      balloonRect.setAttribute("y", "0")
      balloonRect.setAttribute("rx", "6")
      balloonRect.setAttribute("ry", "6")
      gBalloon.appendChild(balloonRect)

      // balloon text section 1
      let balloonText1 = document.createElementNS("http://www.w3.org/2000/svg", "text")
      balloonText1.innerHTML = `Sleep: ${timeToBedText} - ${wakeupTimeText} (${durationText})`
      balloonText1.setAttribute("alignment-baseline", "middle")
      balloonText1.setAttribute('style', 'font-size: x-small; fill: white;')
      gBalloon.appendChild(balloonText1)
      const box1 = balloonText1.getBBox()

      // balloon text section 2
      let balloonText2 = document.createElementNS("http://www.w3.org/2000/svg", "text")
      balloonText2.innerHTML = `Stage: ${describeSleepStage(stage.stage)} (${stageStartTimeText} - ${stageEndTimeText})`
      balloonText2.setAttribute("alignment-baseline", "middle")
      balloonText2.setAttribute('style', 'font-size: x-small; fill: white;')
      gBalloon.appendChild(balloonText2)
      const box2 = balloonText2.getBBox()

      // adjust balloon rect size & position
      const balloonPadding = { top: 5, right: 10, bottom: 5, left: 10 }
      const balloonSize = {
        width: Math.max(box1.width, box2.width) + balloonPadding.left + balloonPadding.right,
        height: box1.height + box2.height + balloonPadding.top + balloonPadding.bottom
      }
      const xBalloon = Math.max(0, Math.min(xPos - balloonSize.width / 2, svg.clientWidth - balloonSize.width))
      balloonRect.setAttribute("x", String(xBalloon))
      balloonRect.setAttribute("width", String(balloonSize.width))
      balloonRect.setAttribute("height", String(balloonSize.height))

      // adjust text position
      const xText = xBalloon + balloonPadding.left
      balloonText1.setAttribute('x', String(xText))
      balloonText1.setAttribute('y', String(balloonPadding.top + box1.height / 2))
      balloonText2.setAttribute('x', String(xText))
      balloonText2.setAttribute('y', String(balloonPadding.top + box1.height + box2.height / 2))

      let line = document.createElementNS("http://www.w3.org/2000/svg", "line")
      line.setAttribute("stroke", balloonColor)
      line.setAttribute("stroke-width", "1")
      line.setAttribute('x1', String(xPos))
      line.setAttribute('y1', balloonSize.height)
      line.setAttribute('x2', String(xPos))
      line.setAttribute('y2', String(yPos))
      gBalloon.appendChild(line)
    }

    function describeDuration(totalSeconds) {
      const sec = totalSeconds % 60
      const min = Math.floor(totalSeconds / 60) % 60
      const hr = Math.floor(totalSeconds / 3600)
      return String(hr) + ":" + String(min).padStart(2, '0') + ":" + String(sec).padStart(2, "0")
    }

    function hideLifeStageBalloon() {
      const balloon = document.getElementById("balloon")
      if (balloon) {
        balloon.remove()
      }
    }

    function drawSleepGraph(svg) {

      hideSleepStageBalloon()

      const graphMargin = { left: 60, top: 50, right: 50, bottom: 20 }

      const graph = {
        x: graphMargin.left,
        y: graphMargin.top,
        width: svg.clientWidth - graphMargin.left - graphMargin.right,
        height: svg.clientHeight - graphMargin.top - graphMargin.bottom
      }

      // graph rectangle
      let rect = document.createElementNS("http://www.w3.org/2000/svg", "rect")
      rect.setAttribute('x', `${graph.x}`)
      rect.setAttribute('y', `${graph.y}`)
      rect.setAttribute('width', `${graph.width}`)
      rect.setAttribute('height', `${graph.height}`)
      rect.setAttribute('class', "graphRect")
      svg.appendChild(rect)

      // proceed no further if no history data
      if (!historyData) return

      // gridlines
      let gGridlines = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gGridlines.setAttribute("class", "graphGridline")
      svg.appendChild(gGridlines)
      let gVerticalGridlines = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gGridlines.appendChild(gVerticalGridlines)
      let gHorizontalGridlines = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gGridlines.appendChild(gHorizontalGridlines)

      // axis labels
      const gAxisLabels = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gAxisLabels.setAttribute("class", "axisLabel")
      svg.appendChild(gAxisLabels)

      // x-axis labels
      const totalHours = 24
      const maxLabels = Math.floor(graph.width / 50)
      var hoursPerLabel = Math.ceil(totalHours / maxLabels)
      while (totalHours % hoursPerLabel > 0) {
        hoursPerLabel += 1
      }
      const labelCount = Math.max(1, Math.floor(totalHours / hoursPerLabel))
      const dx = graph.width / labelCount
      let gXAxisLabels = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gXAxisLabels.setAttribute('text-anchor', 'middle')
      gAxisLabels.appendChild(gXAxisLabels)
      var x, y
      y = graph.y + graph.height + 15 // text height = 11, text top margin = 4 
      for (var tEpoch = historyData.begin; tEpoch <= historyData.begin + 86400; tEpoch += hoursPerLabel * 3600) {
        let text = document.createElementNS("http://www.w3.org/2000/svg", "text")
        let date = new Date(tEpoch * 1000)
        text.innerHTML = date.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
        x = graph.x + graph.width * (tEpoch - historyData.begin) / 86400
        text.setAttribute('x', String(x))
        text.setAttribute('y', String(y))
        gXAxisLabels.appendChild(text)

        // vertical gridline
        if (x > graph.x && x < graph.x + graph.width) {
          let line = document.createElementNS("http://www.w3.org/2000/svg", "line")
          line.setAttribute('stroke', 'rgba(0, 0, 0, 0.05)')
          line.setAttribute('x1', x)
          line.setAttribute('y1', graph.y)
          line.setAttribute('x2', x)
          line.setAttribute('y2', graph.y + graph.height)
          gVerticalGridlines.appendChild(line)
        }
      }

      // y-axis labels
      const yLabelInfo = [
        { // left
          timeOffset: 0,
          x: graph.x - 6,
          textAnchor: "end",
        },
        { // right
          timeOffset: 86400,
          x: graph.x + graph.width + 6,
          textAnchor: "start",
        }
      ]
      yLabelInfo.forEach((info) => {
        info.g = document.createElementNS("http://www.w3.org/2000/svg", "g")
        gAxisLabels.appendChild(info.g)
        info.g.setAttribute("text-anchor", info.textAnchor)
      })

      const dy = graph.height / 7
      for (var i = 0; i < 7; i++) {
        // left & right y-axis labels
        y = graph.y + graph.height - i * dy - dy / 2 + 3
        yLabelInfo.forEach((info) => {
          let text = document.createElementNS("http://www.w3.org/2000/svg", "text")
          info.g.appendChild(text)
          text.innerHTML = (new Date((historyData.begin + i * 86400 + info.timeOffset) * 1000)).toLocaleString(
            undefined,
            { weekday: 'short', month: "numeric", day: "numeric" }
          )
          text.setAttribute('x', String(info.x))
          text.setAttribute('y', String(y))
        })
        // horizontal gridlines
        y = graph.y + graph.height - i * dy - dy / 2
        let line = document.createElementNS("http://www.w3.org/2000/svg", "line")
        line.setAttribute('stroke', 'rgba(0, 0, 0, 0.3)')
        line.setAttribute('x1', graph.x)
        line.setAttribute('y1', y)
        line.setAttribute('x2', graph.x + graph.width)
        line.setAttribute('y2', y)
        gHorizontalGridlines.appendChild(line)
      }

      // sleep stages
      const gStages = document.createElementNS("http://www.w3.org/2000/svg", "g")
      svg.appendChild(gStages)
      gStages.setAttribute("style", "stroke: rgba(0, 0, 0, 0.5); stroke-width: 0.5")
      const stageHeight = dy / 2
      historyData.sleeps.forEach((sleep, sleepIndex) => {
        // detect overlapped sleeps
        var overlapped = false
        for (var ps = 0; ps < sleepIndex; ps++) {
          const priorSleep = historyData.sleeps[ps]
          if (priorSleep.timeToBed + priorSleep.timeInBed > sleep.timeToBed &&
            priorSleep.timeToBed < sleep.timeToBed + sleep.timeInBed) {
            overlapped = true
            break
          }
        }
        sleep.stages.forEach((stage, stageIndex) => {
          if (stage.stage >= 0 && stage.time < historyData.end) {
            const stageEnd = Math.min(historyData.end, (stageIndex < sleep.stages.length - 1) ? sleep.stages[stageIndex + 1].time : sleep.timeToBed + sleep.timeInBed)
            if (stageEnd > historyData.begin) {
              var segmentStartTime = Math.max(stage.time, historyData.begin)
              do {
                const row = Math.floor((segmentStartTime - historyData.begin) / 86400)
                const endOfRow = historyData.begin + 86400 * (row + 1)
                const segmentEndTime = Math.min(endOfRow, stageEnd)
                const segmentWidth = Math.max(1, Math.round((segmentEndTime - segmentStartTime) / 86400 * graph.width))
                const offsetInDay = (segmentStartTime - historyData.begin) % 86400
                x = graph.x + graph.width * offsetInDay / 86400
                y = graph.y + graph.height - row * dy - dy / 2 - stageHeight / 2
                if (overlapped) y += dy / 6

                let rect = document.createElementNS("http://www.w3.org/2000/svg", "rect")
                rect.setAttribute('x', String(x))
                rect.setAttribute('y', String(y))
                rect.setAttribute('width', segmentWidth)
                rect.setAttribute('height', stageHeight)
                console.log(sleepIndex)
                rect.setAttribute("onmouseover", `showSleepStageBalloon(${sleepIndex}, ${stageIndex}, ${(x * 2 + segmentWidth) / 2}, ${y})`)
                rect.setAttribute("onmouseout", `hideSleepStageBalloon()`)
                const fill = overlapped ? `rgba(${Math.round((2 - stage.stage) * 85 + 85)}, 0, 0, 0.5)` : `rgba(0, ${Math.round((2 - stage.stage) * 85 + 85)}, 0, 1.0)`
                rect.setAttribute('style', `fill: ${fill};`)
                gStages.appendChild(rect)

                segmentStartTime = segmentEndTime
              } while (segmentStartTime < stageEnd)
            }
          }
        })
      })
    }

    function showSleepStageBalloon(sleepIndex, stageIndex, xPos, yPos) {
      hideSleepStageBalloon()

      if (!historyData || !historyData.sleeps) return

      const sleep = historyData.sleeps[sleepIndex]
      if (!sleep) return

      const stage = sleep.stages[stageIndex]
      if (!stage) return

      const timeToBedText = (new Date(sleep.timeToBed * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )
      const wakeupTime = sleep.timeToBed + sleep.timeInBed
      const wakeupTimeText = (new Date(wakeupTime * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )
      const durationText = describeDuration(sleep.timeInBed)
      const stageStartTimeText = (new Date(stage.time * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )
      const stageEndTime = (stageIndex < (sleep.stages.length - 1)) ? sleep.stages[stageIndex + 1].time : wakeupTime
      const stageEndTimeText = (new Date(stageEndTime * 1000)).toLocaleString(
        undefined,
        { hour: "numeric", minute: "2-digit", second: "2-digit" }
      )

      // balloon g object
      const svg = document.getElementById("wellBeingHistorySvg")
      let gBalloon = document.createElementNS("http://www.w3.org/2000/svg", "g")
      gBalloon.setAttribute("id", "balloon")
      svg.appendChild(gBalloon)

      // balloon background rectangle
      const balloonColor = "rgba(192, 0, 192, 1.0)"
      let balloonRect = document.createElementNS("http://www.w3.org/2000/svg", "rect")
      balloonRect.setAttribute('style', `fill: ${balloonColor}; stroke: rgba(255, 255, 255, 0.5)`)
      balloonRect.setAttribute("y", "0")
      balloonRect.setAttribute("rx", "6")
      balloonRect.setAttribute("ry", "6")
      gBalloon.appendChild(balloonRect)

      // balloon text section 1
      let balloonText1 = document.createElementNS("http://www.w3.org/2000/svg", "text")
      balloonText1.innerHTML = `Sleep: ${timeToBedText} - ${wakeupTimeText} (${durationText})`
      balloonText1.setAttribute("alignment-baseline", "middle")
      balloonText1.setAttribute('style', 'font-size: x-small; fill: white;')
      gBalloon.appendChild(balloonText1)
      const box1 = balloonText1.getBBox()

      // balloon text section 2
      let balloonText2 = document.createElementNS("http://www.w3.org/2000/svg", "text")
      balloonText2.innerHTML = `Stage: ${describeSleepStage(stage.stage)} (${stageStartTimeText} - ${stageEndTimeText})`
      balloonText2.setAttribute("alignment-baseline", "middle")
      balloonText2.setAttribute('style', 'font-size: x-small; fill: white;')
      gBalloon.appendChild(balloonText2)
      const box2 = balloonText2.getBBox()

      // adjust balloon rect size & position
      const balloonPadding = { top: 5, right: 10, bottom: 5, left: 10 }
      const balloonSize = {
        width: Math.max(box1.width, box2.width) + balloonPadding.left + balloonPadding.right,
        height: box1.height + box2.height + balloonPadding.top + balloonPadding.bottom
      }
      const xBalloon = Math.max(0, Math.min(xPos - balloonSize.width / 2, svg.clientWidth - balloonSize.width))
      balloonRect.setAttribute("x", String(xBalloon))
      balloonRect.setAttribute("width", String(balloonSize.width))
      balloonRect.setAttribute("height", String(balloonSize.height))

      // adjust text position
      const xText = xBalloon + balloonPadding.left
      balloonText1.setAttribute('x', String(xText))
      balloonText1.setAttribute('y', String(balloonPadding.top + box1.height / 2))
      balloonText2.setAttribute('x', String(xText))
      balloonText2.setAttribute('y', String(balloonPadding.top + box1.height + box2.height / 2))

      let line = document.createElementNS("http://www.w3.org/2000/svg", "line")
      line.setAttribute("stroke", balloonColor)
      line.setAttribute("stroke-width", "1")
      line.setAttribute('x1', String(xPos))
      line.setAttribute('y1', balloonSize.height)
      line.setAttribute('x2', String(xPos))
      line.setAttribute('y2', String(yPos))
      gBalloon.appendChild(line)
    }

    function describeDuration(totalSeconds) {
      const sec = totalSeconds % 60
      const min = Math.floor(totalSeconds / 60) % 60
      const hr = Math.floor(totalSeconds / 3600)
      return String(hr) + ":" + String(min).padStart(2, '0') + ":" + String(sec).padStart(2, "0")
    }

    function hideSleepStageBalloon() {
      const balloon = document.getElementById("balloon")
      if (balloon) {
        balloon.remove()
      }
    }

    function describeSleepStage(sleepStage) {
      switch (sleepStage) {
        case 0:
        default:
          return '起床'
        case 1:
          return '深い眠り'
        case 2:
          return '浅い眠り'
        // default:
        //   return `Unknown`
      }
    }

    function describeLocalization(entry) {
      switch (entry.detection) {
        case -2:
          return 'Service stopped'
        case -1:
          return 'Offline'
        case 0:
          return 'Localization: Inactive'
        case 1:
          var description = 'Localization: Active'
          if (entry.bot != null) {
            description += ` - ${describeBot(entry.bot)}`
          }
          return description
      }
      return '(unknown detection)'
    }

    function describeBot(botId) {
      if (botId != null) {
        let label = selectedService.bots[botId]
        if (label) return label
        return `Bot-${botId.substr(botId.length - 4)}`
      }
      return ''
    }

    function historyNavPrev() {
      switch (historyRange) {
        case "month":
          refDate.setMonth(refDate.getMonth() - 1)
          break
        case "week":
          refDate.setDate(refDate.getDate() - 7)
          break
        case "hour":
          refDate.setHours(refDate.getHours() - 1)
          break
        default:
          refDate.setDate(refDate.getDate() - 1)
      }
      loadHistory()
    }

    function historyNavNext() {
      switch (historyRange) {
        case "month":
          refDate.setMonth(refDate.getMonth() + 1)
          break
        case "week":
          refDate.setDate(refDate.getDate() + 7)
          break
        case "hour":
          refDate.setHours(refDate.getHours() + 1)
          break
        default:
          refDate.setDate(refDate.getDate() + 1)
          break
      }
      loadHistory()
    }

    function toggle(target) {
      var toggleSwitch = target
      if (target.classList.contains("toggleSwitchBlock")) {
        toggleSwitch = target.parentElement
      }
      if (toggleSwitch.classList.contains("on")) {
        // turning off
        toggleSwitch.classList.remove("on")
        return false
      }
      // turning on
      toggleSwitch.classList.add("on")
      return true
    }

    function selectTab(name) {
      const tabName = name + "Tab"
      const targetTab = document.getElementById(tabName)
      if (targetTab) {
        let tabs = targetTab.parentNode.getElementsByClassName("tab")
        for (let tab of tabs) {
          if (tab.id == tabName) {
            tab.classList.add("selected")
          }
          else {
            tab.classList.remove("selected")
          }
        }
      }
      // show corresponding page if exists
      const pageName = name + "Page"
      const targetPage = document.getElementById(pageName)
      if (targetPage) {
        let pages = targetPage.parentNode.getElementsByClassName("page")
        for (let page of pages) {
          if (page.id == pageName) {
            page.classList.add("active")
          }
          else {
            page.classList.remove("active")
          }
        }
      }

      if (name === "home") {
        drawHistoryGraph()
      }
    }

    let sensingServerUrl = "https://gfs.aisix.cloud"

    function ssApi(method, path, params, onSuccess, onError) {
      let header = { "Authorization": activeAuthToken }
      let url = sensingServerUrl + path

      let request = new XMLHttpRequest()
      request.open(method, url)

      if (header) {
        for (let key in header) {
          request.setRequestHeader(key, header[key])
        }
      }
      request.onload = function () {
        var data = JSON.parse(this.response)

        console.log(
          `${method} ${url}` +
          `${(params != null) ? ("\npayload=\n" + JSON.stringify(params, null, 2)) : ""}` +
          `\nresp=[${request.status}]\n${JSON.stringify(data, null, 2)}`)

        if (request.status >= 200 && request.status < 400) {
          if (onSuccess) onSuccess(data)
        }
        else {
          if (onError) onError(request.status, data)
        }
      }
      if (params) {
        request.setRequestHeader("Content-Type", "application/json;charset=UTF-8")
        request.send(JSON.stringify(params))
      }
      else {
        request.send()
      }
    }

    // generate initial JWT
    var activeAuthToken = genAuthToken()

    // refresh JWT every 30 minutes
    setInterval(() => { activeAuthToken = genAuthToken() }, 1800000)

    // generate JWT
    function genAuthToken() {
      let header = {
        alg: "HS256",
        typ: "JWT"
      }

      let epochNow = Date.now() / 1000

      let data = {
        iss: "admin@sensingserver",
        iat: epochNow - 3600,
        exp: epochNow + 3600,
        sub: "/api/services",
        keyId: apiKeyId,
      }

      let secret = apiKey

      let stringifiedHeader = CryptoJS.enc.Utf8.parse(JSON.stringify(header))
      let encodedHeader = base64url(stringifiedHeader)

      let stringifiedData = CryptoJS.enc.Utf8.parse(JSON.stringify(data))
      let encodedData = base64url(stringifiedData)

      var signature = encodedHeader + "." + encodedData
      signature = CryptoJS.HmacSHA256(signature, secret)
      signature = base64url(signature)

      let authToken = encodedHeader + "." + encodedData + "." + signature
      console.log(`authToken = ${authToken}`)
      return authToken
    }

    function base64url(source) {
      // Encode in classical base64
      encodedSource = CryptoJS.enc.Base64.stringify(source)

      // Remove padding equal characters
      encodedSource = encodedSource.replace(/=+$/, "")

      // Replace characters according to base64url specifications
      encodedSource = encodedSource.replace(/\+/g, "-")
      encodedSource = encodedSource.replace(/\//g, "_")

      return encodedSource
    }
  </script>
  <style>
    :root {
      --bgcolor: rgb(244,247,250);
      --brightGreen: #00ff1a;
    }

    body {
      color: rgb(50, 50, 50)
      background-color: var(--bgcolor);
      font-family: Arial, Helvetica, sans-serif;
      text-align: center;
      margin: 0px;
    }

    .disclaimer {
      position: fixed;
      width: 100%;
      z-index: 100;
      top: 0px;
      background-color: red;
      height: 20px;
      line-height: 20px;
      font-size: small;
      font-weight: bold;
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1;
      left: 0;
      top: 20;
      width: 100%;
      height: 100%;
      background-color: var(--bgcolor);
      overflow-x: hidden;
      overflow-y: auto;
    }

    #mainScreen {
      display: block;
      z-index: 1;
    }

    .disabled {
      pointer-events: none;
      cursor: default;
    }

    .pageHeader {
      display: table;
      table-layout: fixed;
      width: 100%;
      margin-top: 20px;
      background-color: rgba(0, 0, 0, 0.4);
      height: 50px;
    }

    .pageHeaderLeft {
      display: table-cell;
      text-align: left;
      vertical-align: middle;
    }

    .pageHeaderCenter {
      display: table-cell;
      text-align: center;
      vertical-align: middle;
      font-size: large;
      font-weight: bold;
    }

    .pageHeaderRight {
      display: table-cell;
      text-align: right;
      vertical-align: middle;
    }

    .panelList input {
      background-color: transparent;
      border: solid 1px rgba(255, 255, 255, 0.2);
      color: white;
    }

    .panelList {
      border-collapse: collapse;
      width: 100%;
    }

    .panelList tr {
      border-bottom: 1px solid rgba(0, 0, 0, 0.4);
    }

    .panelList tr:last-child {
      border-bottom: none;
    }

    .panelList td {
      padding: 10px 0px;
    }

    #dataView {
      padding: 10px 10px 0px 10px;
    }

    .scheduleDay {
      display: inline-block;
      font-size: small;
      opacity: 0.5;
      border-radius: 5px;
      border: 1px solid transparent;
      margin: 0px 3px 7px 0px;
      text-align: center;
      width: 36px;
    }

    .scheduleDay.selected {
      border-color: var(--brightGreen);
      opacity: 1.0;
    }

    .scheduleTime {
      font-size: large;
    }

    select {
      outline: none;
      border: none;
      text-align-last: center;
      background-color: transparent;
      color: white;
      border: 0px;
    }

    select option {
      background-color: rgba(0, 0, 0, 0.6);
      color: white;
    }

    select:active,
    select:hover,
    select:focus {
      outline: none;
    }

    .serviceSelector {
      background-color: rgba(0, 0, 0, 0.1);
      font-size: large;
      color: white;
      padding: 5px 0px;
      margin-bottom: 5px;
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 5px;
      width: 100%;
    }

    .tabs {
      display: table;
      width: 100%;
      height: 30px;
    }

    .tab {
      display: table-cell;
      line-height: 30px;
      text-align: center;
      padding: 0px 10px 0px 10px;
      cursor: pointer;
      height: 30px;
      background-color: rgba(0, 0, 0, 0.4);
    }

    .tab.selected {
      /* background-color: var(--bgcolor); */
      border-top-left-radius: 5px 5px;
      border-top-right-radius: 5px 5px;
      background-color: transparent;
    }

    .page {
      display: none;
      position: absolute;
      top: 105px;
      padding: 10px;
      width: calc(100% - 20px);
    }

    .page.active {
      display: block;
    }

    .groupBox {
      border-radius: 5px;
      background-color: rgb(255, 255, 255);
      margin: 0px 0px 5px 0px;
      padding: 0px 10px;
    }

    .graphRect {
      fill: rgb(255, 255, 255);
      stroke: rgba(0, 0, 0, 0.7);
    }

    .graphGridline {
      stroke: rgba(255, 255, 255, 0.3);
      stroke-width: 1;
    }

    .axisLabel {
      font-size: 11px;
      fill: rgba(0, 0, 0, 0.5);
    }

    .graphPolyline {
      fill: none;
      stroke: var(--brightGreen);
      stroke-width: 1;
    }

    #realtimeSvg {
      width: 100%;
      height: 360px;
    }

    #historySvg {
      width: 100%;
      height: 300px;
      border: solid 1px rgba(255, 255, 255, 0.25);
      background-color: rgba(0, 0, 0, 0.4)
    }

    .historyBar {
      fill: var(--brightGreen);
      opacity: 0.75;
      stroke-width: 1px;
      stroke: var(--bgcolor);
    }

    .historyValueLabel {
      font-size: xx-small;
      fill: white;
    }

    #historyXLabels {
      display: table;
      table-layout: fixed;
      font-size: xx-small;
      width: 100%;
      height: 10px;
      opacity: 0.5;
    }

    .button {
      display: inline-block;
      text-align: center;
      cursor: pointer;
      padding: 5px 15px;
    }

    .button.rounded {
      border-radius: 9px;
      border: solid 1px rgb(255, 255, 255, 0.5);
      background: rgb(0, 0, 0, 0.2);
    }

    .toggleSwitch {
      display: inline-block;
      cursor: pointer;
      background-color: rgba(0, 0, 0, 0.4);
      border-radius: 99999px;
      border: solid 1px transparent;
      text-align: left;
      width: 36px;
      height: 20px;
    }

    .toggleSwitch.on {
      background-color: var(--brightGreen);
      text-align: right;
    }

    .toggleSwitchBlock {
      display: inline-block;
      background-color: #818d98;
      border-radius: 99999px;
      cursor: pointer;
      width: 20px;
      height: 20px;
    }

    .segmentedControl {
      display: table;
      table-layout: fixed;
      background-color: rgba(0, 0, 0, 0.4);
      border-radius: 99999px;
      padding: 1px;
    }

    .segmentedControlButton {
      display: table-cell;
      border-radius: 99999px;
      text-align: center;
      padding: 3px;
      cursor: pointer;
      color: white;
    }

    .segmentedControlButton.selected {
      background-color: rgba(255, 255, 255, 0.33);
      cursor: default;
      color: black;
    }

    .subtitle {
      font-size: small;
      color: rgba(255, 255, 255, 0.66);
    }

    .detailIndicator {
      display: inline-block;
      color: rgba(255, 255, 255, 0.66);
      font-size: 28px;
    }

    .online {
      color: var(--brightGreen);
      opacity: 0.5;
    }

    .offline {
      color: orange;
      opacity: 0.75;
    }

    #dataSaving input {
      background-color: rgba(0, 0, 0, 0.2);
    }

    td {
      vertical-align: middle;
    }

    td.left {
      text-align: left;
    }

    td.right {
      text-align: right;
    }

    td.center {
      text-align: center;
    }

    .loader {
      display: inline-block;
      border: 5px solid white;
      border-top: 5px solid green;
      border-radius: 50%;
      width: 30px;
      height: 30px;
      animation: spin 2s linear infinite;
    }

    .locDetLabel {
      display: inline-block;
      font-size: small;
      border-radius: 3px;
      background-color: rgba(0, 0, 0, 0.2);
      color: rgba(255, 255, 255, 0.2);
      margin: 0px 1px 1px 0px;
      padding: 2px 8px;
    }

    .locDetLabel.on {
      color: white;
      background-color: red
    }

    @keyframes spin {
      0% {
        transform: rotate(0deg);
      }

      100% {
        transform: rotate(360deg);
      }
    }
  </style>
</head>

<body onload="onload()">
  <div id="mainScreen" class="modal">
    <div style="padding: 30px 10px 5px 10px; background-color: rgba(0, 0, 0, 0.4);">
      <select class="serviceSelector" id="serviceSelector" onchange="selectService(this.value)">
      </select>
    </div>
    <div class="page active" id="homePage">
      <div id="history">
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
              style="width: 95%; max-width: 480px; margin: 15px auto 0px auto; font-size: small;">
              <div data-value="allHistory" class="segmentedControlButton selected"
                onclick="changeHistoryGraph(this.dataset.value)">終日表示</div>
              <div data-value="splitHistory" class="segmentedControlButton" 
                onclick="changeHistoryGraph(this.dataset.value)">午前／午後表示</div>
            </div>
            <svg width="100%" height="380" id="allWellBeingHistorySvg">
            </svg>
            <svg width="100%" height="380" id="firstWellBeingHistorySvg">
            </svg>
            <svg width="100%" height="380" id="secondWellBeingHistorySvg">
            </svg>
            <div class="segmentedControl" id="historyView"
              style="width: 95%; max-width: 480px; margin: 15px auto 0px auto; font-size: small;">
              <div data-value="lifeLog" class="segmentedControlButton selected"
                onclick="onHistoryView(this.dataset.value)">活動ログ</div>
              <div data-value="sleep" class="segmentedControlButton" onclick="onHistoryView(this.dataset.value)">睡眠ログ
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="groupBox" style="padding: 0">
        <div
          style="text-align:left; font-weight: bold; background-color: rgba(0, 0, 0, 0.3); padding: 5px 10px; border-top-left-radius: 5px; border-top-right-radius: 5px;">
          Devices
        </div>
        <div style="padding: 0px 10px">
          <table id="deviceList" class="panelList"></table>
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