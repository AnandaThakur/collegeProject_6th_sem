<?php
// Update the require path to use absolute path and add error handling
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/functions.php';
require_once '../config/database.php';

// Try to include the reports_tables.php file with error handling
$reportsTablesPath = __DIR__ . '/../database/reports_tables.php';
if (file_exists($reportsTablesPath)) {
    require_once $reportsTablesPath;
} else {
    // If the file doesn't exist, define a minimal version of createReportsTables
    if (!function_exists('createReportsTables')) {
        function createReportsTables() {
            $conn = getDbConnection();
            
            // Create login_logs table if it doesn't exist
            $sql = "CREATE TABLE IF NOT EXISTS login_logs (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) UNSIGNED,
                user_type ENUM('admin', 'buyer', 'seller') NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) NOT NULL,
                login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('success', 'failed') NOT NULL DEFAULT 'success'
            )";
            
            $conn->query($sql);
            
            // Create system_logs table if it doesn't exist
            $sql = "CREATE TABLE IF NOT EXISTS system_logs (
                id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT(11) UNSIGNED,
                action VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            
            $conn->query($sql);
            
            return $conn;
        }
    }
}

// Check if user is logged in and is admin
startSession();
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php?admin=true');
}

// Get database connection
$conn = getDbConnection();

// Ensure the required tables exist
createReportsTables();

// Log this page access
logAdminAction('Accessed Reports & Logs page');

// Get initial report data (default to today)
$startDate = date('Y-m-d');
$endDate = date('Y-m-d');

// Get auction summary counts
$auctionStats = getAuctionStats($conn, $startDate, $endDate);

// Get recent login logs
$loginLogs = getRecentLoginLogs($conn, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Logs - Auction Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        /* Custom styles for reports page */
        .stat-card {
            border-radius: 8px;
            padding: 15px;
            height: 100%;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card-body {
            display: flex;
            align-items: center;
        }
        .stat-card-icon {
            font-size: 2rem;
            margin-right: 15px;
            opacity: 0.8;
        }
        .stat-card-info {
            flex-grow: 1;
        }
        .stat-card-title {
            font-size: 0.9rem;
            margin-bottom: 5px;
            opacity: 0.8;
        }
        .stat-card-value {
            font-size: 1.5rem;
            font-weight: bold;
        }
        .export-buttons {
            display: flex;
            gap: 5px;
        }
        .report-section {
            transition: all 0.3s ease;
        }
        .date-range {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <svg class="logo-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <path d="M30 30 L70 30 L70 70 L30 70 Z" stroke="#FFF" stroke-width="6" fill="none" />
                        <path d="M20 20 L80 20 L80 80 L20 80 Z" stroke="#FFF" stroke-width="6" fill="none" transform="rotate(45 50 50)" />
                    </svg>
                    <span>Auction Admin</span>
                </div>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li>
                        <a href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php">
                            <i class="fas fa-users"></i>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="auctions.php">
                            <i class="fas fa-gavel"></i>
                            <span>Auction Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="bids.php">
                            <i class="fas fa-chart-line"></i>
                            <span>Bid Monitoring</span>
                        </a>
                    </li>
                    <li>
                        <a href="notifications.php">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="wallet-management.php">
                            <i class="fas fa-wallet"></i>
                            <span>Wallet Management</span>
                        </a>
                    </li>
                    <li class="active">
                        <a href="reports.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports & Logs</span>
                        </a>
                    </li>
                    <li>
                        <a href="chat-monitoring.php">
                            <i class="fas fa-comments"></i>
                            <span>Chat Monitoring</span>
                        </a>
                    </li>
                    <li>
                        <a href="system-settings.php">
                            <i class="fas fa-cog"></i>
                            <span>System Settings</span>
                        </a>
                    </li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="#" id="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Top Navbar -->
            <div class="admin-navbar">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="navbar-right">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="admin-profile">
                        <span><?php echo $_SESSION['email']; ?></span>
                        <i class="fas fa-user-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="content-header">
                    <h1>Reports & Logs</h1>
                    <div class="date-range">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('F d, Y'); ?></span>
                    </div>
                </div>

                <!-- Report Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date-range" class="form-label">Date Range</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                        <input type="text" class="form-control" id="date-range" placeholder="Select date range">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="quick-filter" class="form-label">Quick Filter</label>
                                    <select class="form-select" id="quick-filter">
                                        <option value="today">Today</option>
                                        <option value="yesterday">Yesterday</option>
                                        <option value="this_week">This Week</option>
                                        <option value="last_week">Last Week</option>
                                        <option value="this_month">This Month</option>
                                        <option value="last_month">Last Month</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="report-type" class="form-label">Report Type</label>
                                    <select class="form-select" id="report-type">
                                        <option value="auction_summary">Auction Summary</option>
                                        <option value="login_logs">Login Logs</option>
                                        <option value="system_logs">System Logs</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="search-keyword" class="form-label">Search Keyword</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" id="search-keyword" placeholder="Search by keyword">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="mb-3 w-100 text-end">
                                    <button type="button" id="apply-filters" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Apply Filters
                                    </button>
                                    <button type="button" id="reset-filters" class="btn btn-outline-secondary ms-2">
                                        <i class="fas fa-undo me-2"></i>Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Auction Summary Report -->
                <div id="auction_summary-report" class="report-section">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Auction Summary Report</h5>
                            <div class="export-buttons">
                                <button type="button" id="export-csv" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-file-csv me-1"></i>Export CSV
                                </button>
                                <button type="button" id="export-pdf" class="btn btn-sm btn-outline-danger ms-2">
                                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                    <div class="stat-card bg-primary text-white">
                                        <div class="stat-card-body">
                                            <div class="stat-card-icon">
                                                <i class="fas fa-gavel"></i>
                                            </div>
                                            <div class="stat-card-info">
                                                <div class="stat-card-title">Total Auctions</div>
                                                <div class="stat-card-value" id="total-auctions"><?php echo $auctionStats['total']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                    <div class="stat-card bg-success text-white">
                                        <div class="stat-card-body">
                                            <div class="stat-card-icon">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <div class="stat-card-info">
                                                <div class="stat-card-title">Approved Auctions</div>
                                                <div class="stat-card-value" id="approved-auctions"><?php echo $auctionStats['approved']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                    <div class="stat-card bg-warning text-dark">
                                        <div class="stat-card-body">
                                            <div class="stat-card-icon">
                                                <i class="fas fa-pause-circle"></i>
                                            </div>
                                            <div class="stat-card-info">
                                                <div class="stat-card-title">Paused Auctions</div>
                                                <div class="stat-card-value" id="paused-auctions"><?php echo $auctionStats['paused']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="stat-card bg-danger text-white">
                                        <div class="stat-card-body">
                                            <div class="stat-card-icon">
                                                <i class="fas fa-stop-circle"></i>
                                            </div>
                                            <div class="stat-card-info">
                                                <div class="stat-card-title">Ended Auctions</div>
                                                <div class="stat-card-value" id="ended-auctions"><?php echo $auctionStats['ended']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="auction-summary-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Date</th>
                                            <th>Total Auctions</th>
                                            <th>Approved</th>
                                            <th>Paused</th>
                                            <th>Ended</th>
                                            <th>Total Bids</th>
                                            <th>Avg. Bid Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="auction-summary-body">
                                        <tr>
                                            <td><?php echo date('Y-m-d'); ?></td>
                                            <td><?php echo $auctionStats['total']; ?></td>
                                            <td><?php echo $auctionStats['approved']; ?></td>
                                            <td><?php echo $auctionStats['paused']; ?></td>
                                            <td><?php echo $auctionStats['ended']; ?></td>
                                            <td><?php echo $auctionStats['total_bids']; ?></td>
                                            <td>$<?php echo number_format($auctionStats['avg_bid_amount'], 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Login Logs Report -->
                <div id="login_logs-report" class="report-section" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-sign-in-alt me-2"></i>Login Logs</h5>
                            <div class="export-buttons">
                                <button type="button" id="export-login-csv" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-file-csv me-1"></i>Export CSV
                                </button>
                                <button type="button" id="export-login-pdf" class="btn btn-sm btn-outline-danger ms-2">
                                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="login-logs-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>User Type</th>
                                            <th>IP Address</th>
                                            <th>Device/Browser</th>
                                            <th>Login Time</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="login-logs-body">
                                        <?php foreach ($loginLogs as $log): ?>
                                        <tr>
                                            <td><?php echo $log['id']; ?></td>
                                            <td><?php echo $log['email'] ?? 'Unknown'; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $log['user_type'] == 'admin' ? 'danger' : 'primary'; ?>">
                                                    <?php echo ucfirst($log['user_type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $log['ip_address']; ?></td>
                                            <td><?php echo $log['user_agent']; ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['login_time'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $log['status'] == 'success' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($log['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($loginLogs)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center">No login logs found</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Logs Report -->
                <div id="system_logs-report" class="report-section" style="display: none;">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>System Logs</h5>
                            <div class="export-buttons">
                                <button type="button" id="export-system-csv" class="btn btn-sm btn-outline-success">
                                    <i class="fas fa-file-csv me-1"></i>Export CSV
                                </button>
                                <button type="button" id="export-system-pdf" class="btn btn-sm btn-outline-danger ms-2">
                                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="system-logs-table">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>ID</th>
                                            <th>Action</th>
                                            <th>User</th>
                                            <th>IP Address</th>
                                            <th>Details</th>
                                            <th>Timestamp</th>
                                        </tr>
                                    </thead>
                                    <tbody id="system-logs-body">
                                        <!-- System logs will be loaded via AJAX -->
                                        <tr>
                                            <td colspan="6" class="text-center">Loading system logs...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Analytics</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <canvas id="auction-trend-chart"></canvas>
                            </div>
                            <div class="col-md-6 mb-4">
                                <canvas id="login-activity-chart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script>
        // Simple reports.js inline to avoid path issues
        document.addEventListener('DOMContentLoaded', function() {
            // Make jsPDF available globally
            window.jspdf = window.jspdf || {};
            window.jspdf.jsPDF = window.jspdf.jsPDF || window.jsPDF;
            
            // Initialize variables
            let startDate = '<?php echo $startDate; ?>';
            let endDate = '<?php echo $endDate; ?>';
            
            // Initialize date picker
            const dateRangePicker = flatpickr("#date-range", {
                mode: "range",
                dateFormat: "Y-m-d",
                defaultDate: [new Date(startDate), new Date(endDate)],
                onChange: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = formatDate(selectedDates[0]);
                        endDate = formatDate(selectedDates[1]);
                    }
                }
            });
            
            // Initialize charts
            initCharts();
            
            // Handle quick filter changes
            $("#quick-filter").change(function() {
                const filter = $(this).val();
                const dates = getDateRangeFromFilter(filter);
                
                startDate = dates.startDate;
                endDate = dates.endDate;
                
                // Update date picker
                dateRangePicker.setDate([new Date(startDate), new Date(endDate)]);
            });
            
            // Handle report type changes
            $("#report-type").change(function() {
                const reportType = $(this).val();
                
                // Hide all report sections
                $(".report-section").hide();
                
                // Show selected report section
                $(`#${reportType}-report`).show();
                
                // Load data for the selected report
                loadReportData(reportType);
            });
            
            // Handle apply filters button
            $("#apply-filters").click(function() {
                const reportType = $("#report-type").val();
                loadReportData(reportType);
            });
            
            // Handle reset filters button
            $("#reset-filters").click(function() {
                // Reset date range to today
                startDate = formatDate(new Date());
                endDate = formatDate(new Date());
                dateRangePicker.setDate([new Date(startDate), new Date(endDate)]);
                
                // Reset quick filter
                $("#quick-filter").val("today");
                
                // Reset search keyword
                $("#search-keyword").val("");
                
                // Reload current report
                const reportType = $("#report-type").val();
                loadReportData(reportType);
            });
            
            // Handle export CSV button for auction summary
            $("#export-csv").click(function() {
                exportTableToCSV("auction-summary-table", "auction_summary_report.csv");
            });
            
            // Handle export PDF button for auction summary
            $("#export-pdf").click(function() {
                exportTableToPDF("auction-summary-table", "Auction Summary Report");
            });
            
            // Handle export CSV button for login logs
            $("#export-login-csv").click(function() {
                exportTableToCSV("login-logs-table", "login_logs_report.csv");
            });
            
            // Handle export PDF button for login logs
            $("#export-login-pdf").click(function() {
                exportTableToPDF("login-logs-table", "Login Logs Report");
            });
            
            // Handle export CSV button for system logs
            $("#export-system-csv").click(function() {
                exportTableToCSV("system-logs-table", "system_logs_report.csv");
            });
            
            // Handle export PDF button for system logs
            $("#export-system-pdf").click(function() {
                exportTableToPDF("system-logs-table", "System Logs Report");
            });
            
            // Toggle sidebar
            $('.menu-toggle').click(function() {
                $('.admin-container').toggleClass('sidebar-collapsed');
            });
            
            // Handle logout
            $('#logout-link').click(function(e) {
                e.preventDefault();
                
                $.ajax({
                    url: '../api/auth.php',
                    type: 'POST',
                    data: {
                        action: 'logout'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            window.location.href = '../login.php';
                        }
                    }
                });
            });
            
            // Load initial report data
            loadReportData("auction_summary");
            
            /**
             * Load report data based on selected report type
             * @param {string} reportType - The type of report to load
             */
            function loadReportData(reportType) {
                const keyword = $("#search-keyword").val();
                
                switch (reportType) {
                    case "auction_summary":
                        loadAuctionSummary(startDate, endDate, keyword);
                        break;
                        
                    case "login_logs":
                        loadLoginLogs(startDate, endDate, keyword);
                        break;
                        
                    case "system_logs":
                        loadSystemLogs(startDate, endDate, keyword);
                        break;
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
                $("#auction-summary-body").html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>');
                
                $.ajax({
                    url: '../api/reports.php',
                    type: 'POST',
                    data: {
                        action: 'get_auction_summary',
                        start_date: startDate,
                        end_date: endDate,
                        keyword: keyword
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update summary stats
                            const summary = response.data.summary;
                            $("#total-auctions").text(summary.total);
                            $("#approved-auctions").text(summary.approved);
                            $("#paused-auctions").text(summary.paused);
                            $("#ended-auctions").text(summary.ended);
                            
                            // Update table
                            updateAuctionSummaryTable(response.data.daily_data);
                            
                            // Update charts
                            updateAuctionTrendChart(response.data.daily_data);
                        } else {
                            // Show error message
                            $("#auction-summary-body").html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>');
                            console.error("Error loading auction summary:", response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        $("#auction-summary-body").html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>');
                        console.error("AJAX error:", status, error);
                    }
                });
            }
            
            /**
             * Update auction summary table with daily data
             * @param {Array} dailyData - Array of daily auction data
             */
            function updateAuctionSummaryTable(dailyData) {
                let tableHtml = '';
                
                if (dailyData.length === 0) {
                    tableHtml = '<tr><td colspan="7" class="text-center">No data found for the selected date range</td></tr>';
                } else {
                    dailyData.forEach(function(day) {
                        tableHtml += `
                            <tr>
                                <td>${day.date}</td>
                                <td>${day.total}</td>
                                <td>${day.approved}</td>
                                <td>${day.paused}</td>
                                <td>${day.ended}</td>
                                <td>${day.total_bids}</td>
                                <td>$${parseFloat(day.avg_bid_amount).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                }
                
                $("#auction-summary-body").html(tableHtml);
            }
            
            /**
             * Load login logs data
             * @param {string} startDate - Start date in YYYY-MM-DD format
             * @param {string} endDate - End date in YYYY-MM-DD format
             * @param {string} keyword - Search keyword
             */
            function loadLoginLogs(startDate, endDate, keyword) {
                // Show loading indicator
                $("#login-logs-body").html('<tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>');
                
                $.ajax({
                    url: '../api/reports.php',
                    type: 'POST',
                    data: {
                        action: 'get_login_logs',
                        start_date: startDate,
                        end_date: endDate,
                        keyword: keyword
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update table
                            updateLoginLogsTable(response.data);
                            
                            // Update login activity chart
                            updateLoginActivityChart(response.data);
                        } else {
                            // Show error message
                            $("#login-logs-body").html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>');
                            console.error("Error loading login logs:", response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        $("#login-logs-body").html('<tr><td colspan="7" class="text-center text-danger">Error loading data</td></tr>');
                        console.error("AJAX error:", status, error);
                    }
                });
            }
            
            /**
             * Update login logs table
             * @param {Array} logs - Array of login log data
             */
            function updateLoginLogsTable(logs) {
                let tableHtml = '';
                
                if (logs.length === 0) {
                    tableHtml = '<tr><td colspan="7" class="text-center">No login logs found for the selected date range</td></tr>';
                } else {
                    logs.forEach(function(log) {
                        const userType = log.user_type.charAt(0).toUpperCase() + log.user_type.slice(1);
                        const badgeClass = log.user_type === 'admin' ? 'bg-danger' : 'bg-primary';
                        const statusBadgeClass = log.status === 'success' ? 'bg-success' : 'bg-danger';
                        
                        tableHtml += `
                            <tr>
                                <td>${log.id}</td>
                                <td>${log.email || 'Unknown'}</td>
                                <td><span class="badge ${badgeClass}">${userType}</span></td>
                                <td>${log.ip_address}</td>
                                <td>${log.user_agent}</td>
                                <td>${formatDateTime(log.login_time)}</td>
                                <td><span class="badge ${statusBadgeClass}">${log.status.charAt(0).toUpperCase() + log.status.slice(1)}</span></td>
                            </tr>
                        `;
                    });
                }
                
                $("#login-logs-body").html(tableHtml);
            }
            
            /**
             * Load system logs data
             * @param {string} startDate - Start date in YYYY-MM-DD format
             * @param {string} endDate - End date in YYYY-MM-DD format
             * @param {string} keyword - Search keyword
             */
            function loadSystemLogs(startDate, endDate, keyword) {
                // Show loading indicator
                $("#system-logs-body").html('<tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Loading data...</td></tr>');
                
                $.ajax({
                    url: '../api/reports.php',
                    type: 'POST',
                    data: {
                        action: 'get_system_logs',
                        start_date: startDate,
                        end_date: endDate,
                        keyword: keyword
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update table
                            updateSystemLogsTable(response.data);
                        } else {
                            // Show error message
                            $("#system-logs-body").html('<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>');
                            console.error("Error loading system logs:", response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show error message
                        $("#system-logs-body").html('<tr><td colspan="6" class="text-center text-danger">Error loading data</td></tr>');
                        console.error("AJAX error:", status, error);
                    }
                });
            }
            
            /**
             * Update system logs table
             * @param {Array} logs - Array of system log data
             */
            function updateSystemLogsTable(logs) {
                let tableHtml = '';
                
                if (logs.length === 0) {
                    tableHtml = '<tr><td colspan="6" class="text-center">No system logs found for the selected date range</td></tr>';
                } else {
                    logs.forEach(function(log) {
                        tableHtml += `
                            <tr>
                                <td>${log.id}</td>
                                <td>${log.action}</td>
                                <td>${log.email || 'System'}</td>
                                <td>${log.ip_address}</td>
                                <td>${log.details || '-'}</td>
                                <td>${formatDateTime(log.created_at)}</td>
                            </tr>
                        `;
                    });
                }
                
                $("#system-logs-body").html(tableHtml);
            }
            
            /**
             * Initialize charts
             */
            function initCharts() {
                // Auction trend chart
                const auctionTrendCtx = document.getElementById('auction-trend-chart').getContext('2d');
                window.auctionTrendChart = new Chart(auctionTrendCtx, {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Total Auctions',
                                data: [],
                                borderColor: '#4e73df',
                                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                                borderWidth: 2,
                                pointBackgroundColor: '#4e73df',
                                tension: 0.3
                            },
                            {
                                label: 'Approved Auctions',
                                data: [],
                                borderColor: '#1cc88a',
                                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                                borderWidth: 2,
                                pointBackgroundColor: '#1cc88a',
                                tension: 0.3
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Auction Trends',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
                
                // Login activity chart
                const loginActivityCtx = document.getElementById('login-activity-chart').getContext('2d');
                window.loginActivityChart = new Chart(loginActivityCtx, {
                    type: 'bar',
                    data: {
                        labels: [],
                        datasets: [
                            {
                                label: 'Successful Logins',
                                data: [],
                                backgroundColor: 'rgba(28, 200, 138, 0.8)',
                                borderWidth: 0
                            },
                            {
                                label: 'Failed Logins',
                                data: [],
                                backgroundColor: 'rgba(231, 74, 59, 0.8)',
                                borderWidth: 0
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Login Activity',
                                font: {
                                    size: 16
                                }
                            },
                            legend: {
                                position: 'bottom'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
            
            /**
             * Update auction trend chart with daily data
             * @param {Array} dailyData - Array of daily auction data
             */
            function updateAuctionTrendChart(dailyData) {
                const labels = [];
                const totalData = [];
                const approvedData = [];
                
                dailyData.forEach(function(day) {
                    labels.push(day.date);
                    totalData.push(day.total);
                    approvedData.push(day.approved);
                });
                
                window.auctionTrendChart.data.labels = labels;
                window.auctionTrendChart.data.datasets[0].data = totalData;
                window.auctionTrendChart.data.datasets[1].data = approvedData;
                window.auctionTrendChart.update();
            }
            
            /**
             * Update login activity chart
             * @param {Array} logs - Array of login log data
             */
            function updateLoginActivityChart(logs) {
                // Group logs by date
                const logsByDate = {};
                
                logs.forEach(function(log) {
                    const date = log.login_time.split(' ')[0];
                    
                    if (!logsByDate[date]) {
                        logsByDate[date] = {
                            success: 0,
                            failed: 0
                        };
                    }
                    
                    if (log.status === 'success') {
                        logsByDate[date].success++;
                    } else {
                        logsByDate[date].failed++;
                    }
                });
                
                // Convert to arrays for chart
                const labels = Object.keys(logsByDate).sort();
                const successData = [];
                const failedData = [];
                
                labels.forEach(function(date) {
                    successData.push(logsByDate[date].success);
                    failedData.push(logsByDate[date].failed);
                });
                
                window.loginActivityChart.data.labels = labels;
                window.loginActivityChart.data.datasets[0].data = successData;
                window.loginActivityChart.data.datasets[1].data = failedData;
                window.loginActivityChart.update();
            }
            
            /**
             * Export table to CSV
             * @param {string} tableId - ID of the table to export
             * @param {string} filename - Name of the CSV file
             */
            function exportTableToCSV(tableId, filename) {
                const table = document.getElementById(tableId);
                let csv = [];
                
                // Get header row
                const headerRow = [];
                const headers = table.querySelectorAll('thead th');
                headers.forEach(function(header) {
                    headerRow.push('"' + header.textContent.trim() + '"');
                });
                csv.push(headerRow.join(','));
                
                // Get data rows
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(function(row) {
                    if (!row.querySelector('td[colspan]')) {
                        const rowData = [];
                        const cells = row.querySelectorAll('td');
                        cells.forEach(function(cell) {
                            rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
                        });
                        csv.push(rowData.join(','));
                    }
                });
                
                // Download CSV file
                const csvContent = csv.join('\n');
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
            
            /**
             * Export table to PDF
             * @param {string} tableId - ID of the table to export
             * @param {string} title - Title of the PDF document
             */
            function exportTableToPDF(tableId, title) {
                try {
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();
                    
                    // Add title
                    doc.setFontSize(18);
                    doc.text(title, 14, 22);
                    
                    // Add date range
                    doc.setFontSize(12);
                    doc.text(`Date Range: ${startDate} to ${endDate}`, 14, 30);
                    
                    // Add table
                    doc.autoTable({
                        html: '#' + tableId,
                        startY: 35,
                        theme: 'grid',
                        headStyles: {
                            fillColor: [66, 66, 66],
                            textColor: 255,
                            fontStyle: 'bold'
                        },
                        alternateRowStyles: {
                            fillColor: [245, 245, 245]
                        },
                        margin: { top: 35 }
                    });
                    
                    // Add footer
                    const pageCount = doc.internal.getNumberOfPages();
                    for (let i = 1; i <= pageCount; i++) {
                        doc.setPage(i);
                        doc.setFontSize(10);
                        doc.text(`Generated on ${new Date().toLocaleString()} - Page ${i} of ${pageCount}`, 14, doc.internal.pageSize.height - 10);
                    }
                    
                    // Save PDF
                    doc.save(title.replace(/\s+/g, '_').toLowerCase() + '.pdf');
                } catch (error) {
                    console.error("Error generating PDF:", error);
                    alert("Error generating PDF. Please check the console for details.");
                }
            }
            
            /**
             * Format date to YYYY-MM-DD
             * @param {Date} date - Date object
             * @returns {string} Formatted date string
             */
            function formatDate(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                
                return `${year}-${month}-${day}`;
            }
            
            /**
             * Format date and time
             * @param {string} dateTimeStr - Date time string
             * @returns {string} Formatted date time string
             */
            function formatDateTime(dateTimeStr) {
                const date = new Date(dateTimeStr);
                
                return date.toLocaleString();
            }
            
            /**
             * Get date range from quick filter
             * @param {string} filter - Quick filter value
             * @returns {Object} Object with startDate and endDate
             */
            function getDateRangeFromFilter(filter) {
                const today = new Date();
                let startDate, endDate;
                
                switch (filter) {
                    case 'today':
                        startDate = formatDate(today);
                        endDate = formatDate(today);
                        break;
                        
                    case 'yesterday':
                        const yesterday = new Date(today);
                        yesterday.setDate(yesterday.getDate() - 1);
                        startDate = formatDate(yesterday);
                        endDate = formatDate(yesterday);
                        break;
                        
                    case 'this_week':
                        const thisWeekStart = new Date(today);
                        thisWeekStart.setDate(today.getDate() - today.getDay());
                        startDate = formatDate(thisWeekStart);
                        endDate = formatDate(today);
                        break;
                        
                    case 'last_week':
                        const lastWeekStart = new Date(today);
                        lastWeekStart.setDate(today.getDate() - today.getDay() - 7);
                        const lastWeekEnd = new Date(today);
                        lastWeekEnd.setDate(today.getDate() - today.getDay() - 1);
                        startDate = formatDate(lastWeekStart);
                        endDate = formatDate(lastWeekEnd);
                        break;
                        
                    case 'this_month':
                        const thisMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
                        startDate = formatDate(thisMonthStart);
                        endDate = formatDate(today);
                        break;
                        
                    case 'last_month':
                        const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                        const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
                        startDate = formatDate(lastMonthStart);
                        endDate = formatDate(lastMonthEnd);
                        break;
                }
                
                return { startDate, endDate };
            }
        });
    </script>
</body>
</html>
<?php
// Helper functions for reports page

function getAuctionStats($conn, $startDate, $endDate) {
    // Default values if query fails
    $defaultStats = [
        'total' => 0,
        'approved' => 0,
        'paused' => 0,
        'ended' => 0,
        'total_bids' => 0,
        'avg_bid_amount' => 0
    ];

    // Check if auctions table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'auctions'")->num_rows > 0;
    
    if (!$tableExists) {
        // Return default values if table doesn't exist
        return $defaultStats;
    }
    
    // Get auction stats using direct query instead of prepared statement to avoid errors
    $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended
        FROM auctions
        WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        // Query failed, return default values
        error_log("SQL Error in getAuctionStats: " . $conn->error);
        return $defaultStats;
    }
    
    $stats = $result->fetch_assoc();
    
    // Check if bids table exists
    $bidsTableExists = $conn->query("SHOW TABLES LIKE 'bids'")->num_rows > 0;
    
    if ($bidsTableExists) {
        // Get bid stats using direct query
        $sql = "SELECT 
            COUNT(*) as total_bids,
            AVG(amount) as avg_bid_amount
            FROM bids
            WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $bidStats = $result->fetch_assoc();
            $stats['total_bids'] = $bidStats['total_bids'];
            $stats['avg_bid_amount'] = $bidStats['avg_bid_amount'] ?: 0;
        } else {
            $stats['total_bids'] = 0;
            $stats['avg_bid_amount'] = 0;
        }
    } else {
        $stats['total_bids'] = 0;
        $stats['avg_bid_amount'] = 0;
    }
    
    return $stats;
}

function getRecentLoginLogs($conn, $limit = 10) {
    // Check if login_logs table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'login_logs'")->num_rows > 0;
    
    if (!$tableExists) {
        return [];
    }
    
    // Get recent login logs using direct query
    $sql = "SELECT l.*, u.email 
        FROM login_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.login_time DESC
        LIMIT $limit";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        error_log("SQL Error in getRecentLoginLogs: " . $conn->error);
        return [];
    }
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    return $logs;
}

// Note: logAdminAction() function is now used from includes/functions.php

function createSystemLogsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS system_logs (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED,
        action VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->query($sql);
}

// Check if a function exists before defining it
if (!function_exists('startSession')) {
    function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

function createReportsTables() {
    $conn = getDbConnection();

    // Create users table if it doesn't exist
    $usersTableExists = $conn->query("SHOW TABLES LIKE 'users'")->num_rows > 0;
    if (!$usersTableExists) {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            user_type ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }

    // Create auctions table if it doesn't exist
    $auctionsTableExists = $conn->query("SHOW TABLES LIKE 'auctions'")->num_rows > 0;
    if (!$auctionsTableExists) {
        $sql = "CREATE TABLE IF NOT EXISTS auctions (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_price DECIMAL(10, 2) NOT NULL,
            end_time TIMESTAMP NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'paused', 'ended') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }

    // Create bids table if it doesn't exist
    $bidsTableExists = $conn->query("SHOW TABLES LIKE 'bids'")->num_rows > 0;
    if (!$bidsTableExists) {
        $sql = "CREATE TABLE IF NOT EXISTS bids (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED,
            auction_id INT(11) UNSIGNED,
            amount DECIMAL(10, 2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }

    // Create login_logs table if it doesn't exist
    $loginLogsTableExists = $conn->query("SHOW TABLES LIKE 'login_logs'")->num_rows > 0;
    if (!$loginLogsTableExists) {
        $sql = "CREATE TABLE IF NOT EXISTS login_logs (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED,
            user_type ENUM('admin', 'user') NOT NULL DEFAULT 'user',
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255),
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('success', 'failed') NOT NULL
        )";
        $conn->query($sql);
    }

    // Create system_logs table if it doesn't exist
    $systemLogsTableExists = $conn->query("SHOW TABLES LIKE 'system_logs'")->num_rows > 0;
    if (!$systemLogsTableExists) {
        $sql = "CREATE TABLE IF NOT EXISTS system_logs (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) UNSIGNED,
            action VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->query($sql);
    }
}
?>
