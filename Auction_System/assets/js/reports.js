import { Chart } from "@/components/ui/chart"
/**
 * Reports & Logs JavaScript
 * Handles AJAX requests, chart rendering, and export functionality
 */

$(document).ready(() => {
  // Define Chart if it doesn't exist (fallback)
  if (typeof Chart === "undefined" && typeof window.Chart !== "undefined") {
    window.Chart = window.Chart
  }

  // Initialize variables
  let startDate, endDate
  // Initialize date picker
  const dateRangePicker = flatpickr("#date-range", {
    mode: "range",
    dateFormat: "Y-m-d",
    defaultDate: [new Date(), new Date()],
    onChange: (selectedDates) => {
      // Update date range when changed
      if (selectedDates.length === 2) {
        startDate = formatDate(selectedDates[0])
        endDate = formatDate(selectedDates[1])
      }
    },
  })

  // Set initial date range
  startDate = formatDate(new Date())
  endDate = formatDate(new Date())

  // Initialize charts
  initCharts()

  // Handle quick filter changes
  $("#quick-filter").change(function () {
    const filter = $(this).val()
    const dates = getDateRangeFromFilter(filter)

    startDate = dates.startDate
    endDate = dates.endDate

    // Update date picker
    dateRangePicker.setDate([new Date(startDate), new Date(endDate)])
  })

  // Handle report type changes
  $("#report-type").change(function () {
    const reportType = $(this).val()

    // Hide all report sections
    $(".report-section").hide()

    // Show selected report section
    $(`#${reportType}-report`).show()

    // Load data for the selected report
    loadReportData(reportType)
  })

  // Handle apply filters button
  $("#apply-filters").click(() => {
    const reportType = $("#report-type").val()
    loadReportData(reportType)
  })

  // Handle reset filters button
  $("#reset-filters").click(() => {
    // Reset date range to today
    startDate = formatDate(new Date())
    endDate = formatDate(new Date())
    dateRangePicker.setDate([new Date(startDate), new Date(endDate)])

    // Reset quick filter
    $("#quick-filter").val("today")

    // Reset search keyword
    $("#search-keyword").val("")

    // Reload current report
    const reportType = $("#report-type").val()
    loadReportData(reportType)
  })

  // Handle export CSV button for auction summary
  $("#export-csv").click(() => {
    exportTableToCSV("auction-summary-table", "auction_summary_report.csv")
  })

  // Handle export PDF button for auction summary
  $("#export-pdf").click(() => {
    exportTableToPDF("auction-summary-table", "Auction Summary Report")
  })

  // Handle export CSV button for login logs
  $("#export-login-csv").click(() => {
    exportTableToCSV("login-logs-table", "login_logs_report.csv")
  })

  // Handle export PDF button for login logs
  $("#export-login-pdf").click(() => {
    exportTableToPDF("login-logs-table", "Login Logs Report")
  })

  // Handle export CSV button for system logs
  $("#export-system-csv").click(() => {
    exportTableToCSV("system-logs-table", "system_logs_report.csv")
  })

  // Handle export PDF button for system logs
  $("#export-system-pdf").click(() => {
    exportTableToPDF("system-logs-table", "System Logs Report")
  })

  // Toggle sidebar
  $(".menu-toggle").click(() => {
    $(".admin-container").toggleClass("sidebar-collapsed")
  })

  // Handle logout
  $("#logout-link").click((e) => {
    e.preventDefault()

    $.ajax({
      url: "../api/auth.php",
      type: "POST",
      data: {
        action: "logout",
      },
      dataType: "json",
      success: (response) => {
        if (response.success) {
          window.location.href = response.data.redirect
        }
      },
    })
  })

  // Load initial report data
  loadReportData("auction_summary")
})

/**
 * Load report data based on selected report type
 * @param {string} reportType - The type of report to load
 */
function loadReportData(reportType) {
  const keyword = $("#search-keyword").val()

  switch (reportType) {
    case "auction_summary":
      loadAuctionSummary(startDate, endDate, keyword)
      break

    case "login_logs":
      loadLoginLogs(startDate, endDate, keyword)
      break

    case "system_logs":
      loadSystemLogs(startDate, endDate, keyword)
      break
  }
}

/**
 * Load auction summary data
 * @param {string} startDate - Start date in YYYY-MM-DD format
 * @param {string} endDate - End date in YYYY-MM-DD format
 * @param {string} keyword - Search keyword
 */
function loadAuctionSummary(startDate, endDate, keyword) {
  // Show loading indicator
  $("#auction-summary-body").html(
    '<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>',
  )

  $.ajax({
    url: "../api/reports.php",
    type: "POST",
    data: {
      action: "get_auction_summary",
      start_date: startDate,
      end_date: endDate,
      keyword: keyword,
    },
    dataType: "json",
    success: (response) => {
      if (response.success) {
        // Update summary stats
        const summary = response.data.summary
        $("#total-auctions").text(summary.total)
        $("#approved-auctions").text(summary.approved)
        $("#paused-auctions").text(summary.paused)
        $("#ended-auctions").text(summary.ended)

        // Update table
        updateAuctionSummaryTable(response.data.daily_data)

        // Update charts
        updateAuctionTrendChart(response.data.daily_data)
      } else {
        // Show error message
        $("#auction-summary-body").html(
          '<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>',
        )
      }
    },
    error: () => {
      // Show error message
      $("#auction-summary-body").html(
        '<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>',
      )
    },
  })
}

/**
 * Update auction summary table with daily data
 * @param {Array} dailyData - Array of daily auction data
 */
function updateAuctionSummaryTable(dailyData) {
  let tableHtml = ""

  if (dailyData.length === 0) {
    tableHtml = '<tr><td colspan="7" class="text-center">No data found for the selected date range</td></tr>'
  } else {
    dailyData.forEach((day) => {
      tableHtml += `
                <tr>
                    <td>${day.date}</td>
                    <td>${day.total}</td>
                    <td>${day.approved}</td>
                    <td>${day.paused}</td>
                    <td>${day.ended}</td>
                    <td>${day.total_bids}</td>
                    <td>$${Number.parseFloat(day.avg_bid_amount).toFixed(2)}</td>
                </tr>
            `
    })
  }

  $("#auction-summary-body").html(tableHtml)
}

/**
 * Load login logs data
 * @param {string} startDate - Start date in YYYY-MM-DD format
 * @param {string} endDate - End date in YYYY-MM-DD format
 * @param {string} keyword - Search keyword
 */
function loadLoginLogs(startDate, endDate, keyword) {
  // Show loading indicator
  $("#login-logs-body").html(
    '<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>',
  )

  $.ajax({
    url: "../api/reports.php",
    type: "POST",
    data: {
      action: "get_login_logs",
      start_date: startDate,
      end_date: endDate,
      keyword: keyword,
    },
    dataType: "json",
    success: (response) => {
      if (response.success) {
        // Update table
        updateLoginLogsTable(response.data)

        // Update login activity chart
        updateLoginActivityChart(response.data)
      } else {
        // Show error message
        $("#login-logs-body").html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>')
      }
    },
    error: () => {
      // Show error message
      $("#login-logs-body").html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>')
    },
  })
}

/**
 * Update login logs table
 * @param {Array} logs - Array of login log data
 */
function updateLoginLogsTable(logs) {
  let tableHtml = ""

  if (logs.length === 0) {
    tableHtml = '<tr><td colspan="7" class="text-center">No login logs found for the selected date range</td></tr>'
  } else {
    logs.forEach((log) => {
      const userType = log.user_type.charAt(0).toUpperCase() + log.user_type.slice(1)
      const badgeClass = log.user_type === "admin" ? "bg-danger" : "bg-primary"
      const statusBadgeClass = log.status === "success" ? "bg-success" : "bg-danger"

      tableHtml += `
                <tr>
                    <td>${log.id}</td>
                    <td>${log.email || "Unknown"}</td>
                    <td><span class="badge ${badgeClass}">${userType}</span></td>
                    <td>${log.ip_address}</td>
                    <td>${log.user_agent}</td>
                    <td>${formatDateTime(log.login_time)}</td>
                    <td><span class="badge ${statusBadgeClass}">${log.status.charAt(0).toUpperCase() + log.status.slice(1)}</span></td>
                </tr>
            `
    })
  }

  $("#login-logs-body").html(tableHtml)
}

/**
 * Load system logs data
 * @param {string} startDate - Start date in YYYY-MM-DD format
 * @param {string} endDate - End date in YYYY-MM-DD format
 * @param {string} keyword - Search keyword
 */
function loadSystemLogs(startDate, endDate, keyword) {
  // Show loading indicator
  $("#system-logs-body").html(
    '<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>',
  )

  $.ajax({
    url: "../api/reports.php",
    type: "POST",
    data: {
      action: "get_system_logs",
      start_date: startDate,
      end_date: endDate,
      keyword: keyword,
    },
    dataType: "json",
    success: (response) => {
      if (response.success) {
        // Update table
        updateSystemLogsTable(response.data)
      } else {
        // Show error message
        $("#system-logs-body").html('<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>')
      }
    },
    error: () => {
      // Show error message
      $("#system-logs-body").html('<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>')
    },
  })
}

/**
 * Update system logs table
 * @param {Array} logs - Array of system log data
 */
function updateSystemLogsTable(logs) {
  let tableHtml = ""

  if (logs.length === 0) {
    tableHtml = '<tr><td colspan="6" class="text-center">No system logs found for the selected date range</td></tr>'
  } else {
    logs.forEach((log) => {
      tableHtml += `
                <tr>
                    <td>${log.id}</td>
                    <td>${log.action}</td>
                    <td>${log.email || "System"}</td>
                    <td>${log.ip_address}</td>
                    <td>${log.details || "-"}</td>
                    <td>${formatDateTime(log.created_at)}</td>
                </tr>
            `
    })
  }

  $("#system-logs-body").html(tableHtml)
}

/**
 * Initialize charts
 */
function initCharts() {
  // Auction trend chart
  const auctionTrendCtx = document.getElementById("auction-trend-chart").getContext("2d")
  window.auctionTrendChart = new Chart(auctionTrendCtx, {
    type: "line",
    data: {
      labels: [],
      datasets: [
        {
          label: "Total Auctions",
          data: [],
          borderColor: "#4e73df",
          backgroundColor: "rgba(78, 115, 223, 0.1)",
          borderWidth: 2,
          pointBackgroundColor: "#4e73df",
          tension: 0.3,
        },
        {
          label: "Approved Auctions",
          data: [],
          borderColor: "#1cc88a",
          backgroundColor: "rgba(28, 200, 138, 0.1)",
          borderWidth: 2,
          pointBackgroundColor: "#1cc88a",
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: "Auction Trends",
          font: {
            size: 16,
          },
        },
        legend: {
          position: "bottom",
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0,
          },
        },
      },
    },
  })

  // Login activity chart
  const loginActivityCtx = document.getElementById("login-activity-chart").getContext("2d")
  window.loginActivityChart = new Chart(loginActivityCtx, {
    type: "bar",
    data: {
      labels: [],
      datasets: [
        {
          label: "Successful Logins",
          data: [],
          backgroundColor: "rgba(28, 200, 138, 0.8)",
          borderWidth: 0,
        },
        {
          label: "Failed Logins",
          data: [],
          backgroundColor: "rgba(231, 74, 59, 0.8)",
          borderWidth: 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: "Login Activity",
          font: {
            size: 16,
          },
        },
        legend: {
          position: "bottom",
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0,
          },
        },
      },
    },
  })
}

/**
 * Update auction trend chart with daily data
 * @param {Array} dailyData - Array of daily auction data
 */
function updateAuctionTrendChart(dailyData) {
  const labels = []
  const totalData = []
  const approvedData = []

  dailyData.forEach((day) => {
    labels.push(day.date)
    totalData.push(day.total)
    approvedData.push(day.approved)
  })

  window.auctionTrendChart.data.labels = labels
  window.auctionTrendChart.data.datasets[0].data = totalData
  window.auctionTrendChart.data.datasets[1].data = approvedData
  window.auctionTrendChart.update()
}

/**
 * Update login activity chart
 * @param {Array} logs - Array of login log data
 */
function updateLoginActivityChart(logs) {
  // Group logs by date
  const logsByDate = {}

  logs.forEach((log) => {
    const date = log.login_time.split(" ")[0]

    if (!logsByDate[date]) {
      logsByDate[date] = {
        success: 0,
        failed: 0,
      }
    }

    if (log.status === "success") {
      logsByDate[date].success++
    } else {
      logsByDate[date].failed++
    }
  })

  // Convert to arrays for chart
  const labels = Object.keys(logsByDate).sort()
  const successData = []
  const failedData = []

  labels.forEach((date) => {
    successData.push(logsByDate[date].success)
    failedData.push(logsByDate[date].failed)
  })

  window.loginActivityChart.data.labels = labels
  window.loginActivityChart.data.datasets[0].data = successData
  window.loginActivityChart.data.datasets[1].data = failedData
  window.loginActivityChart.update()
}

/**
 * Export table to CSV
 * @param {string} tableId - ID of the table to export
 * @param {string} filename - Name of the CSV file
 */
function exportTableToCSV(tableId, filename) {
  const table = document.getElementById(tableId)
  const csv = []

  // Get header row
  const headerRow = []
  const headers = table.querySelectorAll("thead th")
  headers.forEach((header) => {
    headerRow.push('"' + header.textContent.trim() + '"')
  })
  csv.push(headerRow.join(","))

  // Get data rows
  const rows = table.querySelectorAll("tbody tr")
  rows.forEach((row) => {
    if (!row.querySelector("td[colspan]")) {
      const rowData = []
      const cells = row.querySelectorAll("td")
      cells.forEach((cell) => {
        rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"')
      })
      csv.push(rowData.join(","))
    }
  })

  // Download CSV file
  const csvContent = csv.join("\n")
  const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" })
  const link = document.createElement("a")
  const url = URL.createObjectURL(blob)

  link.setAttribute("href", url)
  link.setAttribute("download", filename)
  link.style.visibility = "hidden"

  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

/**
 * Export table to PDF
 * @param {string} tableId - ID of the table to export
 * @param {string} title - Title of the PDF document
 */
function exportTableToPDF(tableId, title) {
  const { jsPDF } = window.jspdf
  const doc = new jsPDF()

  // Add title
  doc.setFontSize(18)
  doc.text(title, 14, 22)

  // Add date range
  doc.setFontSize(12)
  doc.text(`Date Range: ${startDate} to ${endDate}`, 14, 30)

  // Add table
  doc.autoTable({
    html: "#" + tableId,
    startY: 35,
    theme: "grid",
    headStyles: {
      fillColor: [66, 66, 66],
      textColor: 255,
      fontStyle: "bold",
    },
    alternateRowStyles: {
      fillColor: [245, 245, 245],
    },
    margin: { top: 35 },
  })

  // Add footer
  const pageCount = doc.internal.getNumberOfPages()
  for (let i = 1; i <= pageCount; i++) {
    doc.setPage(i)
    doc.setFontSize(10)
    doc.text(
      `Generated on ${new Date().toLocaleString()} - Page ${i} of ${pageCount}`,
      14,
      doc.internal.pageSize.height - 10,
    )
  }

  // Save PDF
  doc.save(title.replace(/\s+/g, "_").toLowerCase() + ".pdf")
}

/**
 * Format date to YYYY-MM-DD
 * @param {Date} date - Date object
 * @returns {string} Formatted date string
 */
function formatDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, "0")
  const day = String(date.getDate()).padStart(2, "0")

  return `${year}-${month}-${day}`
}

/**
 * Format date and time
 * @param {string} dateTimeStr - Date time string
 * @returns {string} Formatted date time string
 */
function formatDateTime(dateTimeStr) {
  const date = new Date(dateTimeStr)

  return date.toLocaleString()
}

/**
 * Get date range from quick filter
 * @param {string} filter - Quick filter value
 * @returns {Object} Object with startDate and endDate
 */
function getDateRangeFromFilter(filter) {
  const today = new Date()
  let startDate, endDate

  switch (filter) {
    case "today":
      startDate = formatDate(today)
      endDate = formatDate(today)
      break

    case "yesterday":
      const yesterday = new Date(today)
      yesterday.setDate(yesterday.getDate() - 1)
      startDate = formatDate(yesterday)
      endDate = formatDate(yesterday)
      break

    case "this_week":
      const thisWeekStart = new Date(today)
      thisWeekStart.setDate(today.getDate() - today.getDay())
      startDate = formatDate(thisWeekStart)
      endDate = formatDate(today)
      break

    case "last_week":
      const lastWeekStart = new Date(today)
      lastWeekStart.setDate(today.getDate() - today.getDay() - 7)
      const lastWeekEnd = new Date(today)
      lastWeekEnd.setDate(today.getDate() - today.getDay() - 1)
      startDate = formatDate(lastWeekStart)
      endDate = formatDate(lastWeekEnd)
      break

    case "this_month":
      const thisMonthStart = new Date(today.getFullYear(), today.getMonth(), 1)
      startDate = formatDate(thisMonthStart)
      endDate = formatDate(today)
      break

    case "last_month":
      const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1)
      const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0)
      startDate = formatDate(lastMonthStart)
      endDate = formatDate(lastMonthEnd)
      break
  }

  return { startDate, endDate }
}
