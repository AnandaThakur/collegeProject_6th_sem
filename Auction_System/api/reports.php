<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/functions.php';
require_once '../config/database.php';
require_once '../database/reports_tables.php';

// Check if user is logged in and is admin
startSession();
if (!isLoggedIn() || !isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get database connection
$conn = getDbConnection();

// Ensure tables exist
createReportsTables();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'get_auction_summary':
            getAuctionSummary($conn);
            break;
        
        case 'get_login_logs':
            getLoginLogs($conn);
            break;
            
        case 'get_system_logs':
            getSystemLogs($conn);
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Function to get auction summary data
function getAuctionSummary($conn) {
    // Get filter parameters
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    
    // Check if auctions table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'auctions'")->num_rows > 0;
    
    if (!$tableExists) {
        // Return empty data if table doesn't exist
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'summary' => [
                    'total' => 0,
                    'approved' => 0,
                    'paused' => 0,
                    'ended' => 0,
                    'total_bids' => 0,
                    'avg_bid_amount' => 0
                ],
                'daily_data' => []
            ]
        ]);
        return;
    }
    
    // Get auction summary stats
    $sql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended
        FROM auctions
        WHERE DATE(created_at) BETWEEN ? AND ?";
    
    // Add keyword filter if provided
    if (!empty($keyword)) {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
    }
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($keyword)) {
        $keywordParam = "%$keyword%";
        $stmt->bind_param("ssss", $startDate, $endDate, $keywordParam, $keywordParam);
    } else {
        $stmt->bind_param("ss", $startDate, $endDate);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $summary = $result->fetch_assoc();
    
    // Check if bids table exists
    $bidsTableExists = $conn->query("SHOW TABLES LIKE 'bids'")->num_rows > 0;
    
    if ($bidsTableExists) {
        // Get bid stats
        $sql = "SELECT 
            COUNT(*) as total_bids,
            AVG(amount) as avg_bid_amount
            FROM bids
            WHERE DATE(created_at) BETWEEN ? AND ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $bidStats = $result->fetch_assoc();
        
        $summary['total_bids'] = $bidStats['total_bids'];
        $summary['avg_bid_amount'] = $bidStats['avg_bid_amount'] ?: 0;
    } else {
        $summary['total_bids'] = 0;
        $summary['avg_bid_amount'] = 0;
    }
    
    // Get daily data for the date range
    $dailyData = getDailyAuctionData($conn, $startDate, $endDate, $keyword);
    
    // Return the data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => $summary,
            'daily_data' => $dailyData
        ]
    ]);
}

// Function to get daily auction data
function getDailyAuctionData($conn, $startDate, $endDate, $keyword = '') {
    // Check if auctions table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'auctions'")->num_rows > 0;
    
    if (!$tableExists) {
        return [];
    }
    
    // Get daily auction data
    $sql = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
        SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended
        FROM auctions
        WHERE DATE(created_at) BETWEEN ? AND ?";
    
    // Add keyword filter if provided
    if (!empty($keyword)) {
        $sql .= " AND (title LIKE ? OR description LIKE ?)";
    }
    
    $sql .= " GROUP BY DATE(created_at) ORDER BY DATE(created_at)";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($keyword)) {
        $keywordParam = "%$keyword%";
        $stmt->bind_param("ssss", $startDate, $endDate, $keywordParam, $keywordParam);
    } else {
        $stmt->bind_param("ss", $startDate, $endDate);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $dailyData = $result->fetch_all(MYSQLI_ASSOC);
    
    // Check if bids table exists
    $bidsTableExists = $conn->query("SHOW TABLES LIKE 'bids'")->num_rows > 0;
    
    if ($bidsTableExists) {
        // Get daily bid data
        $sql = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_bids,
            AVG(amount) as avg_bid_amount
            FROM bids
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY DATE(created_at)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        $bidData = $result->fetch_all(MYSQLI_ASSOC);
        
        // Merge bid data with auction data
        $bidDataByDate = [];
        foreach ($bidData as $bid) {
            $bidDataByDate[$bid['date']] = $bid;
        }
        
        foreach ($dailyData as &$day) {
            $date = $day['date'];
            $day['total_bids'] = isset($bidDataByDate[$date]) ? $bidDataByDate[$date]['total_bids'] : 0;
            $day['avg_bid_amount'] = isset($bidDataByDate[$date]) ? $bidDataByDate[$date]['avg_bid_amount'] : 0;
        }
    } else {
        // Add empty bid data
        foreach ($dailyData as &$day) {
            $day['total_bids'] = 0;
            $day['avg_bid_amount'] = 0;
        }
    }
    
    return $dailyData;
}

// Function to get login logs
function getLoginLogs($conn) {
    // Get filter parameters
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
    
    // Check if login_logs table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'login_logs'")->num_rows > 0;
    
    if (!$tableExists) {
        // Return empty data if table doesn't exist
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        return;
    }
    
    // Get login logs
    $sql = "SELECT l.*, u.email 
        FROM login_logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE DATE(l.login_time) BETWEEN ? AND ?";
    
    // Add keyword filter if provided
    if (!empty($keyword)) {
        $sql .= " AND (u.email LIKE ? OR l.ip_address LIKE ? OR l.user_agent LIKE ?)";
    }
    
    $sql .= " ORDER BY l.login_time DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($keyword)) {
        $keywordParam = "%$keyword%";
        $stmt->bind_param("ssssi", $startDate, $endDate, $keywordParam, $keywordParam, $keywordParam, $limit);
    } else {
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    
    // Return the data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);
}

// Function to get system logs
function getSystemLogs($conn) {
    // Get filter parameters
    $startDate = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');
    $endDate = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d');
    $keyword = isset($_POST['keyword']) ? $_POST['keyword'] : '';
    $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 100;
    
    // Check if system_logs table exists
    $tableExists = $conn->query("SHOW TABLES LIKE 'system_logs'")->num_rows > 0;
    
    if (!$tableExists) {
        // Return empty data if table doesn't exist
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => []
        ]);
        return;
    }
    
    // Get system logs
    $sql = "SELECT s.*, u.email 
        FROM system_logs s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE DATE(s.created_at) BETWEEN ? AND ?";
    
    // Add keyword filter if provided
    if (!empty($keyword)) {
        $sql .= " AND (u.email LIKE ? OR s.action LIKE ? OR s.details LIKE ? OR s.ip_address LIKE ?)";
    }
    
    $sql .= " ORDER BY s.created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($keyword)) {
        $keywordParam = "%$keyword%";
        $stmt->bind_param("sssssi", $startDate, $endDate, $keywordParam, $keywordParam, $keywordParam, $keywordParam, $limit);
    } else {
        $stmt->bind_param("ssi", $startDate, $endDate, $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    
    // Return the data
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $logs
    ]);
}
?>
