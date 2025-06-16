<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once '../config/database.php';
require_once '../includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../login.php');
}

// Debug log function
function debug_to_console($data) {
    $output = $data;
    if (is_array($output)) {
        $output = implode(',', $output);
    }
    echo "<script>console.log('Debug: " . addslashes($output) . "');</script>";
}

// Function to check if table exists
function tableExists($tableName) {
    $conn = getDbConnection();
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Create wallet tables if they don't exist
function ensureWalletTablesExist() {
    $conn = getDbConnection();
    
    // Check if wallet_balances table exists
    if (!tableExists('wallet_balances')) {
        $sql = "CREATE TABLE IF NOT EXISTS wallet_balances (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            balance DECIMAL(15,2) DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            die("Error creating wallet_balances table: " . $conn->error);
        }
    }
    
    // Check if wallet_transactions table exists
    if (!tableExists('wallet_transactions')) {
        $sql = "CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(50) NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            type ENUM('deposit', 'withdrawal', 'bid', 'win', 'refund', 'deduct') NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            status ENUM('pending', 'completed', 'failed', 'cancelled', 'rejected') DEFAULT 'pending',
            description TEXT NULL,
            admin_id INT UNSIGNED NULL,
            reference_id VARCHAR(100) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY (transaction_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        if (!$conn->query($sql)) {
            die("Error creating wallet_transactions table: " . $conn->error);
        }
    }
    
    return true;
}

// Ensure wallet tables exist
try {
    ensureWalletTablesExist();
    $tablesCreated = true;
} catch (Exception $e) {
    $errorMessage = "Error creating wallet tables: " . $e->getMessage();
    $tablesCreated = false;
}

// Get all users excluding admins
function getAllUsers($limit = 10, $offset = 0, $search = '') {
    $conn = getDbConnection();
    
    try {
        $query = "SELECT u.id, u.email, u.role, u.status FROM users u WHERE u.role != 'admin'";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $query .= " AND (u.email LIKE ? OR u.id = ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $search;
            $types .= "ss";
        }
        
        $query .= " ORDER BY u.id DESC LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            // Get wallet balance for each user
            $balanceQuery = "SELECT balance FROM wallet_balances WHERE user_id = ?";
            $balanceStmt = $conn->prepare($balanceQuery);
            $balanceStmt->bind_param("i", $row['id']);
            $balanceStmt->execute();
            $balanceResult = $balanceStmt->get_result();
            
            if ($balanceResult->num_rows > 0) {
                $row['balance'] = $balanceResult->fetch_assoc()['balance'];
            } else {
                $row['balance'] = 0.00;
            }
            
            // Get last transaction date
            $transactionQuery = "SELECT MAX(created_at) as last_transaction FROM wallet_transactions WHERE user_id = ?";
            $transactionStmt = $conn->prepare($transactionQuery);
            $transactionStmt->bind_param("i", $row['id']);
            $transactionStmt->execute();
            $transactionResult = $transactionStmt->get_result();
            
            if ($transactionResult->num_rows > 0) {
                $row['last_transaction'] = $transactionResult->fetch_assoc()['last_transaction'];
            } else {
                $row['last_transaction'] = null;
            }
            
            $users[] = $row;
        }
        
        return $users;
    } catch (Exception $e) {
        debug_to_console("Error in getAllUsers: " . $e->getMessage());
        return [];
    }
}

// Count total users excluding admins
function countTotalUsers($search = '') {
    $conn = getDbConnection();
    
    try {
        $query = "SELECT COUNT(*) as count FROM users WHERE role != 'admin'";
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $query .= " AND (email LIKE ? OR id = ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $search;
            $types .= "ss";
        }
        
        $stmt = $conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc()['count'];
    } catch (Exception $e) {
        debug_to_console("Error in countTotalUsers: " . $e->getMessage());
        return 0;
    }
}

// Get pending transactions
function getPendingTransactions() {
    $conn = getDbConnection();
    
    try {
        $query = "SELECT wt.*, u.email as user_email 
                FROM wallet_transactions wt
                JOIN users u ON wt.user_id = u.id
                WHERE wt.status = 'pending' AND u.role != 'admin'
                ORDER BY wt.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $transactions = [];
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        
        return $transactions;
    } catch (Exception $e) {
        debug_to_console("Error in getPendingTransactions: " . $e->getMessage());
        return [];
    }
}

// Get wallet statistics
function getWalletStatistics() {
    $conn = getDbConnection();
    
    try {
        // Get total wallet balance
        $query = "SELECT SUM(wb.balance) as total_balance 
                FROM wallet_balances wb
                JOIN users u ON wb.user_id = u.id
                WHERE u.role != 'admin'";
        $result = $conn->query($query);
        $totalBalance = $result->fetch_assoc()['total_balance'] ?? 0;
        
        // Get total transactions
        $query = "SELECT COUNT(*) as total_transactions 
                FROM wallet_transactions wt
                JOIN users u ON wt.user_id = u.id
                WHERE u.role != 'admin'";
        $result = $conn->query($query);
        $totalTransactions = $result->fetch_assoc()['total_transactions'] ?? 0;
        
        // Get total pending transactions
        $query = "SELECT COUNT(*) as total_pending 
                FROM wallet_transactions wt
                JOIN users u ON wt.user_id = u.id
                WHERE wt.status = 'pending' AND u.role != 'admin'";
        $result = $conn->query($query);
        $totalPending = $result->fetch_assoc()['total_pending'] ?? 0;
        
        // Get total deposits
        $query = "SELECT COUNT(*) as total_deposits 
                FROM wallet_transactions wt
                JOIN users u ON wt.user_id = u.id
                WHERE wt.type = 'deposit' AND wt.status = 'completed' AND u.role != 'admin'";
        $result = $conn->query($query);
        $totalDeposits = $result->fetch_assoc()['total_deposits'] ?? 0;
        
        // Get recent transactions
        $query = "SELECT wt.*, u.email as user_email 
                FROM wallet_transactions wt
                JOIN users u ON wt.user_id = u.id
                WHERE u.role != 'admin'
                ORDER BY wt.created_at DESC
                LIMIT 5";
        $result = $conn->query($query);
        
        $recentTransactions = [];
        while ($row = $result->fetch_assoc()) {
            $recentTransactions[] = $row;
        }
        
        return [
            'total_balance' => $totalBalance,
            'total_transactions' => $totalTransactions,
            'total_pending' => $totalPending,
            'total_deposits' => $totalDeposits,
            'recent_transactions' => $recentTransactions
        ];
    } catch (Exception $e) {
        debug_to_console("Error in getWalletStatistics: " . $e->getMessage());
        return [
            'total_balance' => 0,
            'total_transactions' => 0,
            'total_pending' => 0,
            'total_deposits' => 0,
            'recent_transactions' => []
        ];
    }
}

// Generate transaction ID
function generateTransactionId() {
    return 'TXN' . date('YmdHis') . rand(1000, 9999);
}

// Add funds to user wallet
function addFundsToWallet($userId, $amount, $description, $adminId) {
    $conn = getDbConnection();
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Check if user exists and is not admin
        $userQuery = "SELECT role FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows == 0) {
            throw new Exception("User not found");
        }
        
        $userRole = $userResult->fetch_assoc()['role'];
        if ($userRole == 'admin') {
            throw new Exception("Cannot add funds to admin accounts");
        }
        
        // Get current balance
        $balanceQuery = "SELECT balance FROM wallet_balances WHERE user_id = ? FOR UPDATE";
        $balanceStmt = $conn->prepare($balanceQuery);
        $balanceStmt->bind_param("i", $userId);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        
        if ($balanceResult->num_rows == 0) {
            // Create wallet balance record if it doesn't exist
            $currentBalance = 0.00;
            $createQuery = "INSERT INTO wallet_balances (user_id, balance) VALUES (?, 0.00)";
            $createStmt = $conn->prepare($createQuery);
            $createStmt->bind_param("i", $userId);
            $createStmt->execute();
        } else {
            $currentBalance = $balanceResult->fetch_assoc()['balance'];
        }
        
        // Calculate new balance
        $newBalance = $currentBalance + $amount;
        
        // Update wallet balance
        $updateQuery = "UPDATE wallet_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("di", $newBalance, $userId);
        $updateStmt->execute();
        
        // Create transaction record
        $transactionId = generateTransactionId();
        $transactionQuery = "INSERT INTO wallet_transactions 
                           (transaction_id, user_id, type, amount, status, description, admin_id, created_at) 
                           VALUES (?, ?, 'deposit', ?, 'completed', ?, ?, NOW())";
        $transactionStmt = $conn->prepare($transactionQuery);
        $transactionStmt->bind_param("sidsi", $transactionId, $userId, $amount, $description, $adminId);
        $transactionStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Funds added successfully',
            'new_balance' => $newBalance,
            'transaction_id' => $transactionId
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Approve transaction
function approveTransaction($transactionId, $adminId) {
    $conn = getDbConnection();
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get transaction details
        $transactionQuery = "SELECT wt.*, u.role 
                           FROM wallet_transactions wt
                           JOIN users u ON wt.user_id = u.id
                           WHERE wt.transaction_id = ? AND wt.status = 'pending'";
        $transactionStmt = $conn->prepare($transactionQuery);
        $transactionStmt->bind_param("s", $transactionId);
        $transactionStmt->execute();
        $transactionResult = $transactionStmt->get_result();
        
        if ($transactionResult->num_rows == 0) {
            throw new Exception("Transaction not found or already processed");
        }
        
        $transaction = $transactionResult->fetch_assoc();
        
        // Check if user is not admin
        if ($transaction['role'] == 'admin') {
            throw new Exception("Cannot process transactions for admin accounts");
        }
        
        $userId = $transaction['user_id'];
        $amount = $transaction['amount'];
        
        // Update transaction status
        $updateQuery = "UPDATE wallet_transactions 
                       SET status = 'completed', admin_id = ?, updated_at = NOW() 
                       WHERE transaction_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("is", $adminId, $transactionId);
        $updateStmt->execute();
        
        // Get current balance
        $balanceQuery = "SELECT balance FROM wallet_balances WHERE user_id = ? FOR UPDATE";
        $balanceStmt = $conn->prepare($balanceQuery);
        $balanceStmt->bind_param("i", $userId);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        
        if ($balanceResult->num_rows == 0) {
            // Create wallet balance record if it doesn't exist
            $currentBalance = 0.00;
            $createQuery = "INSERT INTO wallet_balances (user_id, balance) VALUES (?, 0.00)";
            $createStmt = $conn->prepare($createQuery);
            $createStmt->bind_param("i", $userId);
            $createStmt->execute();
        } else {
            $currentBalance = $balanceResult->fetch_assoc()['balance'];
        }
        
        // Calculate new balance
        $newBalance = $currentBalance + $amount;
        
        // Update wallet balance
        $updateBalanceQuery = "UPDATE wallet_balances SET balance = ?, updated_at = NOW() WHERE user_id = ?";
        $updateBalanceStmt = $conn->prepare($updateBalanceQuery);
        $updateBalanceStmt->bind_param("di", $newBalance, $userId);
        $updateBalanceStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Transaction approved successfully',
            'new_balance' => $newBalance
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Reject transaction
function rejectTransaction($transactionId, $adminId, $reason = '') {
    $conn = getDbConnection();
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get transaction details
        $transactionQuery = "SELECT wt.*, u.role 
                           FROM wallet_transactions wt
                           JOIN users u ON wt.user_id = u.id
                           WHERE wt.transaction_id = ? AND wt.status = 'pending'";
        $transactionStmt = $conn->prepare($transactionQuery);
        $transactionStmt->bind_param("s", $transactionId);
        $transactionStmt->execute();
        $transactionResult = $transactionStmt->get_result();
        
        if ($transactionResult->num_rows == 0) {
            throw new Exception("Transaction not found or already processed");
        }
        
        $transaction = $transactionResult->fetch_assoc();
        
        // Check if user is not admin
        if ($transaction['role'] == 'admin') {
            throw new Exception("Cannot process transactions for admin accounts");
        }
        
        // Update transaction status
        $updateQuery = "UPDATE wallet_transactions 
                       SET status = 'rejected', admin_id = ?, 
                       description = CONCAT(IFNULL(description, ''), ' (Rejected: ', ?, ')'), 
                       updated_at = NOW() 
                       WHERE transaction_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("iss", $adminId, $reason, $transactionId);
        $updateStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => 'Transaction rejected successfully'
        ];
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

// Get transaction type badge
function getTransactionTypeBadge($type) {
    switch ($type) {
        case 'deposit':
            return '<span class="badge bg-success">Deposit</span>';
        case 'withdrawal':
            return '<span class="badge bg-danger">Withdrawal</span>';
        case 'bid':
            return '<span class="badge bg-primary">Bid</span>';
        case 'win':
            return '<span class="badge bg-warning text-dark">Win</span>';
        case 'refund':
            return '<span class="badge bg-info">Refund</span>';
        case 'deduct':
            return '<span class="badge bg-danger">Deduct</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($type) . '</span>';
    }
}

// Get transaction status badge
function getTransactionStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'completed':
            return '<span class="badge bg-success">Completed</span>';
        case 'failed':
            return '<span class="badge bg-danger">Failed</span>';
        case 'cancelled':
            return '<span class="badge bg-secondary">Cancelled</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}



// Handle transaction approval/rejection
if (isset($_POST['action']) && isset($_POST['transaction_id'])) {
    $transactionId = sanitizeInput($_POST['transaction_id']);
    $adminId = $_SESSION['user_id'];
    
    if ($_POST['action'] === 'approve') {
        $result = approveTransaction($transactionId, $adminId);
        
        if ($result['success']) {
            $successMessage = "Transaction approved successfully.";
        } else {
            $errorMessage = $result['message'];
        }
    } elseif ($_POST['action'] === 'reject') {
        $reason = isset($_POST['reason']) ? sanitizeInput($_POST['reason']) : '';
        $result = rejectTransaction($transactionId, $adminId, $reason);
        
        if ($result['success']) {
            $successMessage = "Transaction rejected successfully.";
        } else {
            $errorMessage = $result['message'];
        }
    }
}

// Handle manual fund loading
if (isset($_POST['load_funds'])) {
    $userId = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $description = sanitizeInput($_POST['description']);
    
    if ($amount <= 0) {
        $errorMessage = "Amount must be greater than 0.";
    } else {
        $result = addFundsToWallet($userId, $amount, $description, $_SESSION['user_id']);
        
        if ($result['success']) {
            $successMessage = "Funds added successfully.";
        } else {
            $errorMessage = $result['message'];
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Get users with wallet balances (excluding admin users)
$users = getAllUsers($limit, $offset, $search);
$totalUsers = countTotalUsers($search);
$totalPages = ceil($totalUsers / $limit);

// Get pending transactions
$pendingTransactions = getPendingTransactions();

// Get wallet statistics
$stats = getWalletStatistics();

// Set page title for header
$pageTitle = "Wallet Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet Management - Admin Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- Wallet Management CSS -->
    <link rel="stylesheet" href="../assets/css/wallet-management.css">
    <style>
        /* Additional styles for wallet management */
        .transaction-amount.positive {
            color: green;
            font-weight: bold;
        }
        .transaction-amount.negative {
            color: red;
            font-weight: bold;
        }
        .balance-highlight {
            font-weight: bold;
        }
        .balance-highlight.updated {
            background-color: #e8f7e8;
            transition: background-color 1s;
        }
        .stats-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            flex: 1;
            min-width: 200px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
        }
        .stat-card-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .stat-card-info h5 {
            color: #6c757d;
            margin-bottom: 5px;
            font-size: 14px;
        }
        .stat-card-info h2 {
            color: #343a40;
            margin-bottom: 0;
            font-size: 24px;
        }
        .stat-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #6c757d;
        }
        .stat-card-footer {
            margin-top: 15px;
            font-size: 12px;
            color: #6c757d;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .section-header h4 {
            margin-bottom: 0;
        }
        .recent-section {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .view-all {
            font-size: 14px;
            color: #007bff;
            text-decoration: none;
        }
        .action-buttons {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <img src="../assets/img/logo.svg" alt="Logo" class="logo-icon">
                    <span>Admin Panel</span>
                </div>
            </div>
            <div class="sidebar-menu">
                <?php include_once '../includes/admin-sidebar.php'; ?>
            </div>
            <div class="sidebar-footer">
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="admin-content">
            <!-- Navbar -->
            <div class="admin-navbar">
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="navbar-right">
                    <div class="notifications">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?php echo count($pendingTransactions); ?></span>
                    </div>
                    <div class="admin-profile">
                        <i class="fas fa-user-circle"></i>
                        <span>Admin</span>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="content-header">
                    <h1>Wallet Management</h1>
                    <div class="btn-toolbar">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportToCSV()">
                                <i class="fas fa-file-export"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($successMessage)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $successMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $errorMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if (!$tablesCreated): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    There was an error creating the wallet tables. Please check the database connection and permissions.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Wallet Balance</h5>
                                <h2>Rs <?php echo number_format($stats['total_balance'] ?? 0, 2); ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <i class="fas fa-arrow-up"></i> Updated today
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Transactions</h5>
                                <h2><?php echo number_format($stats['total_transactions'] ?? 0); ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <i class="fas fa-arrow-up"></i> Updated today
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Pending Transactions</h5>
                                <h2><?php echo number_format($stats['total_pending'] ?? 0); ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <i class="fas fa-arrow-down"></i> Updated today
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card-content">
                            <div class="stat-card-info">
                                <h5>Total Deposits</h5>
                                <h2><?php echo number_format($stats['total_deposits'] ?? 0); ?></h2>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                        </div>
                        <div class="stat-card-footer">
                            <i class="fas fa-arrow-up"></i> Updated today
                        </div>
                    </div>
                </div>

                <!-- Pending Transactions -->
                <?php if (!empty($pendingTransactions)): ?>
                <div class="card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Transactions</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Transaction ID</th>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTransactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction['transaction_id']; ?></td>
                                        <td><?php echo htmlspecialchars($transaction['user_email']); ?></td>
                                        <td><?php echo getTransactionTypeBadge($transaction['type']); ?></td>
                                        <td class="transaction-amount <?php echo $transaction['amount'] >= 0 ? 'positive' : 'negative'; ?>">
                                            Rs <?php echo number_format($transaction['amount'], 2); ?>
                                        </td>
                                        <td><?php echo formatDate($transaction['created_at']); ?></td>
                                        <td>
                                            <button class="btn btn-success btn-sm" onclick="approveTransaction('<?php echo $transaction['transaction_id']; ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="rejectTransaction('<?php echo $transaction['transaction_id']; ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Search and Filters -->
                <div class="card mb-4 filter-card">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Search by user ID or email" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <a href="wallet-management.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Wallet Table -->
                <div class="recent-section">
                    <div class="section-header">
                        <h4>User Wallet Balances</h4>
                        <div class="dropdown">
                            <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-ellipsis-v"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink">
                                <li><a class="dropdown-item" href="#" onclick="refreshWalletData()">Refresh Data</a></li>
                                <li><a class="dropdown-item" href="#" onclick="exportToCSV()">Export to CSV</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="transaction-history.php">View All Transactions</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="walletTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>User ID</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Wallet Balance</th>
                                    <th>Last Transaction</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No users found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr id="user-row-<?php echo $user['id']; ?>">
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo ucfirst($user['role']); ?></td>
                                        <td><?php echo getUserStatusBadge($user['status']); ?></td>
                                        <td>
                                            <span id="balance-<?php echo $user['id']; ?>" class="balance-highlight">
                                                Rs <?php echo number_format($user['balance'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['last_transaction'] ? formatDate($user['last_transaction']) : 'No transactions'; ?>
                                        </td>
                                        <td class="action-buttons">
                                            <button class="btn btn-primary btn-sm" onclick="loadFunds(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['email']); ?>')">
                                                <i class="fas fa-plus-circle"></i> Load Funds
                                            </button>
                                            <a href="transaction-history.php?user_id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">
                                                <i class="fas fa-history"></i> History
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>

                <!-- Recent Transactions -->
                <div class="recent-section">
                    <div class="section-header">
                        <h4>Recent Transactions</h4>
                        <a href="transaction-history.php" class="view-all">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>User</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($stats['recent_transactions'])): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No recent transactions</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($stats['recent_transactions'] as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction['transaction_id']; ?></td>
                                        <td><?php echo htmlspecialchars($transaction['user_email']); ?></td>
                                        <td><?php echo getTransactionTypeBadge($transaction['type']); ?></td>
                                        <td class="transaction-amount <?php echo $transaction['amount'] >= 0 ? 'positive' : 'negative'; ?>">
                                            Rs <?php echo number_format($transaction['amount'], 2); ?>
                                        </td>
                                        <td><?php echo getTransactionStatusBadge($transaction['status']); ?></td>
                                        <td><?php echo formatDate($transaction['created_at']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Transaction Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">Approve Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>Are you sure you want to approve this transaction?</p>
                        <input type="hidden" name="transaction_id" id="approve_transaction_id">
                        <input type="hidden" name="action" value="approve">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Transaction Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectModalLabel">Reject Transaction</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <p>Are you sure you want to reject this transaction?</p>
                        <div class="mb-3">
                            <label for="reject_reason" class="form-label">Reason (optional)</label>
                            <textarea class="form-control" id="reject_reason" name="reason" rows="3"></textarea>
                        </div>
                        <input type="hidden" name="transaction_id" id="reject_transaction_id">
                        <input type="hidden" name="action" value="reject">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Load Funds Modal -->
    <div class="modal fade" id="loadFundsModal" tabindex="-1" aria-labelledby="loadFundsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loadFundsModalLabel">Load Funds</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="user_email" class="form-label">User</label>
                            <input type="text" class="form-control" id="user_email" disabled>
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount (Rs)</label>
                            <input type="number" class="form-control" id="amount" name="amount" min="1" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" value="Manual fund load by admin" required>
                        </div>
                        <input type="hidden" name="user_id" id="user_id">
                        <input type="hidden" name="load_funds" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Load Funds</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JavaScript -->
    <script>
        // Toggle sidebar
        document.querySelector('.menu-toggle').addEventListener('click', function() {
            document.querySelector('.admin-container').classList.toggle('sidebar-collapsed');
        });

        // Approve transaction
        function approveTransaction(transactionId) {
            document.getElementById('approve_transaction_id').value = transactionId;
            var approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
            approveModal.show();
        }

        // Reject transaction
        function rejectTransaction(transactionId) {
            document.getElementById('reject_transaction_id').value = transactionId;
            var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
            rejectModal.show();
        }

        // Load funds
        function loadFunds(userId, userEmail) {
            document.getElementById('user_id').value = userId;
            document.getElementById('user_email').value = userEmail;
            var loadFundsModal = new bootstrap.Modal(document.getElementById('loadFundsModal'));
            loadFundsModal.show();
        }

        // Refresh wallet data
        function refreshWalletData() {
            window.location.reload();
        }

        // Export to CSV
        function exportToCSV() {
            window.location.href = "../api/wallet-actions.php?action=export_wallets";
        }
    </script>
</body>
</html>
