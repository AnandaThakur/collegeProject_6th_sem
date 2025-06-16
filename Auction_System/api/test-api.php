<?php
// Simple API test endpoint
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in the response

try {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Include database connection
    require_once '../config/database.php';
    
    // Get database connection
    $conn = getDbConnection();
    
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Check if tables exist
    $tablesExist = true;
    $requiredTables = ['users', 'auctions', 'bids', 'categories'];
    
    foreach ($requiredTables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows === 0) {
            $tablesExist = false;
            break;
        }
    }
    
    // Get session info
    $sessionInfo = [
        'session_id' => session_id(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'is_logged_in' => isset($_SESSION['user_id']),
        'is_admin' => isset($_SESSION['role']) && $_SESSION['role'] === 'admin'
    ];
    
    // Get database info
    $dbInfo = [
        'connection' => ($conn) ? 'successful' : 'failed',
        'tables_exist' => $tablesExist,
        'missing_tables' => []
    ];
    
    if (!$tablesExist) {
        foreach ($requiredTables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                $dbInfo['missing_tables'][] = $table;
            }
        }
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'API connection successful',
        'timestamp' => date('Y-m-d H:i:s'),
        'session' => $sessionInfo,
        'database' => $dbInfo,
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in test-api.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
