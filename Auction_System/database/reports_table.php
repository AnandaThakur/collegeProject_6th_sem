<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

// Create necessary tables for reports and logs
function createReportsTables() {
    $conn = getDbConnection();
    
    // Create login_logs table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS login_logs (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED,
        user_type ENUM('admin', 'user', 'buyer', 'seller') NOT NULL DEFAULT 'user',
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255),
        login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('success', 'failed') NOT NULL DEFAULT 'success'
    )";
    
    if (!$conn->query($sql)) {
        error_log("Error creating login_logs table: " . $conn->error);
    }
    
    // Create system_logs table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS system_logs (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) UNSIGNED,
        action VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql)) {
        error_log("Error creating system_logs table: " . $conn->error);
    }
    
    // Create auction_logs table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS auction_logs (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        auction_id INT(11) UNSIGNED,
        user_id INT(11) UNSIGNED,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($sql) !== TRUE) {
        error_log("Error creating auction_logs table: " . $conn->error);
    }
    
    // Create daily_stats table for caching daily statistics
    $sql = "CREATE TABLE IF NOT EXISTS daily_stats (
        id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        stat_date DATE NOT NULL,
        total_auctions INT(11) UNSIGNED DEFAULT 0,
        approved_auctions INT(11) UNSIGNED DEFAULT 0,
        paused_auctions INT(11) UNSIGNED DEFAULT 0,
        ended_auctions INT(11) UNSIGNED DEFAULT 0,
        total_bids INT(11) UNSIGNED DEFAULT 0,
        avg_bid_amount DECIMAL(10,2) DEFAULT 0.00,
        total_users INT(11) UNSIGNED DEFAULT 0,
        new_users INT(11) UNSIGNED DEFAULT 0,
        total_logins INT(11) UNSIGNED DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (stat_date)
    )";
    
    if ($conn->query($sql) !== TRUE) {
        error_log("Error creating daily_stats table: " . $conn->error);
    }
    
    // Add function to log login attempts
    addLoginLoggingFunction();
    
    // Insert some sample data for testing if tables are empty
    insertSampleData($conn);
    
    return $conn;
}

// Change the addLoginLoggingFunction() function to not attempt to modify files
function addLoginLoggingFunction() {
    // Instead of modifying the functions.php file, we'll check if the function exists
    // and define it here if needed
    if (!function_exists('logLoginAttempt')) {
        // The function will be defined in this file instead
        // No file modification needed
    }
}

// Add the logLoginAttempt function directly in this file if it doesn't exist
if (!function_exists('logLoginAttempt')) {
    function logLoginAttempt($userId, $userType, $status = 'success') {
        $conn = getDbConnection();
        
        // Check if login_logs table exists
        $tableExists = $conn->query("SHOW TABLES LIKE 'login_logs'")->num_rows > 0;
        
        if (!$tableExists) {
            createReportsTables();
        }
        
        // Get IP address and user agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // Insert log entry using direct query to avoid prepared statement issues
        $userId = (int)$userId;
        $userType = $conn->real_escape_string($userType);
        $status = $conn->real_escape_string($status);
        $ipAddress = $conn->real_escape_string($ipAddress);
        $userAgent = $conn->real_escape_string($userAgent);
        
        $sql = "INSERT INTO login_logs (user_id, user_type, ip_address, user_agent, status) 
                VALUES ($userId, '$userType', '$ipAddress', '$userAgent', '$status')";
        
        if (!$conn->query($sql)) {
            error_log("Error logging login attempt: " . $conn->error);
        }
    }
}

// Log system action
if (!function_exists('logSystemAction')) {
    function logSystemAction($userId, $action, $details = '') {
        $conn = getDbConnection();
        
        // Check if system_logs table exists
        $tableExists = $conn->query("SHOW TABLES LIKE 'system_logs'")->num_rows > 0;
        
        if (!$tableExists) {
            createReportsTables();
        }
        
        // Get IP address
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Insert log entry using direct query to avoid prepared statement issues
        $userId = (int)$userId;
        $action = $conn->real_escape_string($action);
        $details = $conn->real_escape_string($details);
        $ipAddress = $conn->real_escape_string($ipAddress);
        
        $sql = "INSERT INTO system_logs (user_id, action, ip_address, details) 
                VALUES ($userId, '$action', '$ipAddress', '$details')";
        
        if (!$conn->query($sql)) {
            error_log("Error logging system action: " . $conn->error);
        }
    }
}

// Function to insert sample data for testing
function insertSampleData($conn) {
    // Check if login_logs table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM login_logs");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insert sample login logs
        $sql = "INSERT INTO login_logs (user_id, user_type, ip_address, user_agent, status, login_time) VALUES 
            (1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'success', NOW() - INTERVAL 1 DAY),
            (2, 'buyer', '192.168.1.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'success', NOW() - INTERVAL 2 DAY),
            (3, 'seller', '10.0.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)', 'failed', NOW() - INTERVAL 3 DAY),
            (1, 'admin', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'success', NOW())";
        
        $conn->query($sql);
    }
    
    // Check if system_logs table is empty
    $result = $conn->query("SELECT COUNT(*) as count FROM system_logs");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        // Insert sample system logs
        $sql = "INSERT INTO system_logs (user_id, action, ip_address, details, created_at) VALUES 
            (1, 'User Login', '127.0.0.1', 'Admin user logged in', NOW() - INTERVAL 1 DAY),
            (1, 'System Settings Updated', '127.0.0.1', 'Updated email settings', NOW() - INTERVAL 2 DAY),
            (1, 'User Approved', '127.0.0.1', 'Approved user ID: 2', NOW() - INTERVAL 3 DAY),
            (1, 'Auction Approved', '127.0.0.1', 'Approved auction ID: 1', NOW())";
        
        $conn->query($sql);
    }
    
    // Check if auctions table exists and is empty
    $tableExists = $conn->query("SHOW TABLES LIKE 'auctions'")->num_rows > 0;
    
    if ($tableExists) {
        $result = $conn->query("SELECT COUNT(*) as count FROM auctions");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Insert sample auctions
            $sql = "INSERT INTO auctions (title, description, status, created_at) VALUES 
                ('Sample Auction 1', 'This is a sample auction', 'approved', NOW() - INTERVAL 1 DAY),
                ('Sample Auction 2', 'This is another sample auction', 'pending', NOW() - INTERVAL 2 DAY),
                ('Sample Auction 3', 'This is a third sample auction', 'ended', NOW() - INTERVAL 3 DAY),
                ('Sample Auction 4', 'This is a fourth sample auction', 'paused', NOW())";
            
            $conn->query($sql);
        }
    }
}

// Create the tables
createReportsTables();
?>
