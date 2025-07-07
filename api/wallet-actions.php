<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/functions.php';
require_once '../config/database.php';

// Check if user is logged in
startSession();
if (!isLoggedIn()) {
    jsonResponse(false, 'User not logged in');
}

// Get database connection
$conn = getDbConnection();

// Check if wallet tables exist
$result = $conn->query("SHOW TABLES LIKE 'wallet_balances'");
if ($result->num_rows == 0) {
    // Create wallet tables
    $conn->query("CREATE TABLE IF NOT EXISTS wallet_balances (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $conn->query("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(50) NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        type ENUM('deposit', 'withdrawal', 'bid', 'win', 'refund', 'deduct') NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'completed', 'failed', 'cancelled', 'reversed') NOT NULL DEFAULT 'pending',
        description TEXT,
        admin_id INT UNSIGNED,
        reference_id VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY (transaction_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Handle API requests
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    // If no JSON data, check POST
    $data = $_POST;
}

// Generate transaction ID
function generateTransactionId() {
    return 'TXN' . date('YmdHis') . rand(1000, 9999);
}

// Create notification function if it doesn't exist
if (!function_exists('createNotification')) {
    function createNotification($userId, $title, $message, $type = 'info', $source = 'system') {
        global $conn;
        
        // Check if notifications table exists
        $result = $conn->query("SHOW TABLES LIKE 'notifications'");
        if ($result->num_rows == 0) {
            // Create notifications table
            $conn->query("CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT 'info',
                source VARCHAR(50) NOT NULL DEFAULT 'system',
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, source) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $title, $message, $type, $source);
        return $stmt->execute();
    }
}

// Process the action
if (isset($data['action'])) {
    switch ($data['action']) {
        case 'create_pending_deposit':
            // Validate required fields
            if (!isset($data['user_id']) || !isset($data['amount']) || !isset($data['payment_method'])) {
                jsonResponse(false, 'Missing required fields');
            }
            
            $userId = intval($data['user_id']);
            $amount = floatval($data['amount']);
            $paymentMethod = $data['payment_method'];
            $description = $data['description'] ?? 'Deposit via ' . ucfirst($paymentMethod);
            
            // Validate amount
            if ($amount < 100) {
                jsonResponse(false, 'Minimum deposit amount is Rs 100');
            }
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                jsonResponse(false, 'User not found');
            }
            
            // Generate transaction ID
            $transactionId = generateTransactionId();
            
            // Create pending transaction
            $stmt = $conn->prepare("INSERT INTO wallet_transactions 
                                  (transaction_id, user_id, type, amount, status, description, created_at) 
                                  VALUES (?, ?, 'deposit', ?, 'pending', ?, NOW())");
            $stmt->bind_param("sids", $transactionId, $userId, $amount, $description);
            
            if ($stmt->execute()) {
                // Create notification for user
                $userNotification = "Your deposit request of Rs " . number_format($amount, 2) . " has been received and is pending approval";
                createNotification($userId, "Deposit Request Received", $userNotification, "info", "system");
                
                // Create notification for admin
                $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $adminStmt->execute();
                $adminResult = $adminStmt->get_result();
                
                if ($adminResult->num_rows > 0) {
                    $adminId = $adminResult->fetch_assoc()['id'];
                    $adminNotification = "New deposit request of Rs " . number_format($amount, 2) . " from user #" . $userId;
                    createNotification($adminId, "New Deposit Request", $adminNotification, "info", "system");
                }
                
                jsonResponse(true, 'Deposit request created successfully', [
                    'transaction_id' => $transactionId,
                    'amount' => $amount
                ]);
            } else {
                jsonResponse(false, 'Error creating deposit request: ' . $stmt->error);
            }
            break;
            
        case 'cancel_transaction':
            // Validate required fields
            if (!isset($data['transaction_id'])) {
                jsonResponse(false, 'Missing transaction ID');
            }
            
            $transactionId = $data['transaction_id'];
            $userId = $_SESSION['user_id'];
            
            // Check if transaction exists and belongs to the user
            $stmt = $conn->prepare("SELECT * FROM wallet_transactions 
                                  WHERE transaction_id = ? AND user_id = ? AND status = 'pending'");
            $stmt->bind_param("si", $transactionId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 0) {
                jsonResponse(false, 'Transaction not found or cannot be cancelled');
            }
            
            // Update transaction status
            $stmt = $conn->prepare("UPDATE wallet_transactions 
                                  SET status = 'cancelled', updated_at = NOW() 
                                  WHERE transaction_id = ?");
            $stmt->bind_param("s", $transactionId);
            
            if ($stmt->execute()) {
                // Create notification for user
                $userNotification = "Your transaction #" . $transactionId . " has been cancelled";
                createNotification($userId, "Transaction Cancelled", $userNotification, "info", "system");
                
                jsonResponse(true, 'Transaction cancelled successfully');
            } else {
                jsonResponse(false, 'Error cancelling transaction: ' . $stmt->error);
            }
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} else {
    jsonResponse(false, 'No action specified');
}

// Return JSON response
function jsonResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>
